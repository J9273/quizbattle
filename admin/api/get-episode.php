<?php
/**
 * Get Episode API - Debug Version
 * Test basic functionality first
 */

// Set headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Test 1: Can we even output?
try {
    // Test without requiring config-render.php first
    $episode_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$episode_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Episode ID required', 'debug' => 'validation failed']);
        exit;
    }
    
    // Test 2: Try to include the config
    $config_path = '../../includes/config-render.php';
    if (!file_exists($config_path)) {
        echo json_encode(['success' => false, 'error' => 'Config file not found at: ' . $config_path]);
        exit;
    }
    
    require_once $config_path;
    
    // Test 3: Check if $pdo exists
    if (!isset($pdo)) {
        echo json_encode(['success' => false, 'error' => 'Database connection ($pdo) not available after requiring config']);
        exit;
    }
    
    // Test 4: Try the query
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
            'error' => 'Episode not found with ID: ' . $episode_id
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
