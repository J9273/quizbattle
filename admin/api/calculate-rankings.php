<?php
/**
 * Calculate Rankings API
 * Recalculates team positions based on points
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
    echo json_encode([
        'success' => false,
        'error' => 'Episode ID required'
    ]);
    exit;
}

try {
    // Get all teams ordered by points
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY points DESC, team_name ASC");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll();
    
    // Update positions
    $position = 1;
    foreach ($teams as $team) {
        $stmt = $conn->prepare("UPDATE teams SET position = ? WHERE id = ?");
        $stmt->execute([$position, $team['id']]);
        $position++;
    }
    
    // Get updated teams
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY position ASC");
    $stmt->execute([$episode_id]);
    $updated_teams = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'teams' => $updated_teams
    ]);
    
} catch (PDOException $e) {
    error_log("Calculate rankings error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
