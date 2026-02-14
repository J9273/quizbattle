<?php
/**
 * Get Episode Results API
 * Returns final standings for completed episodes
 */
require_once '../../includes/config-render.php';
header('Content-Type: application/json');

$episode_id = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;

if (!$episode_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID required']);
    exit;
}

try {
    // Get episode details
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        echo json_encode(['success' => false, 'error' => 'Episode not found']);
        exit;
    }
    
    // Get all teams ordered by points (highest first)
    $stmt = $conn->prepare("
        SELECT id, team_name, points, position 
        FROM teams 
        WHERE episode_id = ? 
        ORDER BY points DESC, team_name ASC
    ");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'episode' => [
            'id' => $episode['id'],
            'episode_name' => $episode['episode_name'],
            'episode_date' => $episode['episode_date'],
            'status' => $episode['status']
        ],
        'teams' => $teams
    ]);
    
} catch (PDOException $e) {
    error_log("Get episode results error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
