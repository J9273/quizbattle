<?php
/**
 * Polling API Endpoint - Alternative to WebSocket for Free Tier
 * This file provides real-time-like updates via HTTP polling
 * 
 * Usage: Client polls this endpoint every 2-3 seconds
 */

require_once '../includes/config-render.php';
require_once '../includes/auth.php';

// Must be logged in
requireLogin();

header('Content-Type: application/json');

$episode_id = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;
$last_update = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;

if (!$episode_id) {
    echo json_encode(['error' => 'No episode ID provided']);
    exit;
}

try {
    $response = [
        'success' => true,
        'timestamp' => time(),
        'updates' => []
    ];
    
    // Get episode data
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        echo json_encode(['error' => 'Episode not found']);
        exit;
    }
    
    $response['episode'] = $episode;
    
    // Get teams data
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY position ASC, points DESC");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll();
    
    $response['teams'] = $teams;
    
    // Check if there are any updates since last poll
    // This is a simple implementation - you can make it more sophisticated
    if ($last_update > 0) {
        // Check if any team scores changed
        $stmt = $conn->prepare("
            SELECT COUNT(*) as changed 
            FROM teams 
            WHERE episode_id = ? 
            AND EXTRACT(EPOCH FROM (NOW() - created_at)) < ?
        ");
        $time_window = time() - $last_update;
        $stmt->execute([$episode_id, $time_window]);
        $result = $stmt->fetch();
        
        if ($result['changed'] > 0) {
            $response['has_updates'] = true;
        } else {
            $response['has_updates'] = false;
        }
    }
    
    // Get latest activity (optional - for activity feed)
    // This could be score changes, question displays, etc.
    // For now, we'll just include team data
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
