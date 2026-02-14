<?php
/**
 * Get Episodes API
 * Returns list of active episodes for player selection
 */
require_once '../../includes/config-render.php';
header('Content-Type: application/json');

try {
    // Get all active episodes, ordered by date (most recent first)
    $stmt = $conn->query("
        SELECT id, episode_name, episode_date, quiz_format, status
        FROM quiz_episodes 
        WHERE status = 'active'
        ORDER BY episode_date DESC, id DESC
    ");
    
    $episodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'episodes' => $episodes
    ]);
    
} catch (PDOException $e) {
    error_log("Get episodes error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load episodes'
    ]);
}
?>
