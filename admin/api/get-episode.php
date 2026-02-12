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
    // Check if $pdo exists
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Query the episode - try named parameters instead of $1
    $stmt = $pdo->prepare("SELECT * FROM quiz_episodes WHERE id = :id");
    $stmt->bindParam(':id', $episode_id, PDO::PARAM_INT);
    $stmt->execute();
    $episode = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($episode) {
        echo json_encode([
            'success' => true,
            'episode' => $episode
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Episode not found'
        ]);
    }
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
