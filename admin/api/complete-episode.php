<?php
/**
 * Complete Episode API
 * Marks episode as completed and removes all teams/scores
 */
require_once '../../includes/config-render.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$episode_id = isset($input['episode_id']) ? (int)$input['episode_id'] : 0;

if (!$episode_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID required']);
    exit;
}

try {
    // Verify episode exists
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        echo json_encode(['success' => false, 'error' => 'Episode not found']);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // DO NOT delete teams - keep them for the podium display and records
    // Just mark the episode as completed
    $stmt = $conn->prepare("UPDATE quiz_episodes SET status = 'completed' WHERE id = ?");
    $stmt->execute([$episode_id]);
    
    // Get team count for response
    $stmt = $conn->prepare("SELECT COUNT(*) as team_count FROM teams WHERE episode_id = ?");
    $stmt->execute([$episode_id]);
    $result = $stmt->fetch();
    $team_count = $result['team_count'];
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Episode completed successfully',
        'team_count' => $team_count,
        'redirect_url' => "/public/podium.html?episode_id={$episode_id}"
    ]);
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Complete episode error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
