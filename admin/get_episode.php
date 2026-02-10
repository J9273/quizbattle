<?php
/**
 * Get Episode Details API
 * Returns episode information and teams
 */

require_once '../includes/config-render.php';

header('Content-Type: application/json');

$episode_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$episode_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Episode ID required'
    ]);
    exit;
}

try {
    // Get episode details
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        echo json_encode([
            'success' => false,
            'error' => 'Episode not found'
        ]);
        exit;
    }
    
    // Get teams for this episode
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY position ASC, points DESC");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'episode' => $episode,
        'teams' => $teams
    ]);
    
} catch (PDOException $e) {
    error_log("Get episode error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
