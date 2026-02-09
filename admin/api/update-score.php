<?php
/**
 * Update Score API
 * Updates team score via API call
 */

require_once '../../includes/config-render.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$episode_id = isset($input['episode_id']) ? (int)$input['episode_id'] : 0;
$team_id = isset($input['team_id']) ? (int)$input['team_id'] : 0;
$points = isset($input['points']) ? (int)$input['points'] : 0;
$action = isset($input['action']) ? $input['action'] : 'add'; // add, subtract, set

if (!$episode_id || !$team_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Episode ID and Team ID required'
    ]);
    exit;
}

try {
    // Update score based on action
    if ($action === 'add') {
        $stmt = $conn->prepare("UPDATE teams SET points = points + ? WHERE id = ? AND episode_id = ?");
        $stmt->execute([$points, $team_id, $episode_id]);
    } elseif ($action === 'subtract') {
        $stmt = $conn->prepare("UPDATE teams SET points = points - ? WHERE id = ? AND episode_id = ?");
        $stmt->execute([$points, $team_id, $episode_id]);
    } else { // set
        $stmt = $conn->prepare("UPDATE teams SET points = ? WHERE id = ? AND episode_id = ?");
        $stmt->execute([$points, $team_id, $episode_id]);
    }
    
    // Get updated team data
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'team' => $team,
        'action' => $action,
        'points_changed' => $points
    ]);
    
} catch (PDOException $e) {
    error_log("Update score error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
