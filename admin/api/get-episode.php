<?php
/**
 * Get Episode API
 * Retrieves episode information by ID
 */
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
    // Query the episode
    $stmt = $pdo->prepare("SELECT * FROM quiz_episodes WHERE id = $1");
    $stmt->execute([$episode_id]);
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
        'error' => 'Database error occurred'
    ]);
}
?>
