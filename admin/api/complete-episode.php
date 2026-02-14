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
    
    // Delete all teams for this episode (cascades to buzzes and mc_answers due to foreign keys)
    $stmt = $conn->prepare("DELETE FROM teams WHERE episode_id = ?");
    $stmt->execute([$episode_id]);
    $teams_deleted = $stmt->rowCount();
    
    // Clear episode state
    $stmt = $conn->prepare("DELETE FROM episode_state WHERE episode_id = ?");
    $stmt->execute([$episode_id]);
    
    // Mark episode as completed
    $stmt = $conn->prepare("UPDATE quiz_episodes SET status = 'completed' WHERE id = ?");
    $stmt->execute([$episode_id]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Episode completed successfully',
        'teams_deleted' => $teams_deleted
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
