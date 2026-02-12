<?php
/**
 * Get Episode API
 * Retrieves episode information by ID
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../includes/config-render.php';
header('Content-Type: application/json');

// Only accept GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'GET method required']);
    exit;
}

// Get episode ID from query parameter
$episode_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validation
if (!$episode_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Episode ID required']);
    exit;
}

try {
    // Check if $conn exists
    if (!isset($conn)) {
        throw new Exception('Database connection not available');
    }
    
    // Query the episode
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Episode not found'
        ]);
        exit;
    }
    
    // Get teams for this episode
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY points DESC");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'episode' => $episode,
        'teams' => $teams
    ]);
} catch (PDOException $e) {
    error_log("Database error in get-episode.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get-episode.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
