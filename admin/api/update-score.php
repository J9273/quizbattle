<?php
require_once '../../includes/bootstrap.php';
session_start();
require_once '../../includes/config-render.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// CRITICAL: Require admin login
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$episode_id = isset($input['episode_id']) ? (int)$input['episode_id'] : 0;
$team_id = isset($input['team_id']) ? (int)$input['team_id'] : 0;
$points = isset($input['points']) ? (int)$input['points'] : 0;
$action = isset($input['action']) ? $input['action'] : 'add';

if (!$episode_id || !$team_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID and Team ID required']);
    exit;
}

if (!in_array($action, ['add', 'subtract', 'set'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

if ($points < 0 || $points > 1000) {
    echo json_encode(['success' => false, 'error' => 'Points must be between 0 and 1000']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ? AND episode_id = ?");
    $stmt->execute([$team_id, $episode_id]);
    $team = $stmt->fetch();
    
    if (!$team) {
        echo json_encode(['success' => false, 'error' => 'Team not found']);
        exit;
    }
    
    if ($action === 'add') {
        $stmt = $conn->prepare("UPDATE teams SET points = points + ? WHERE id = ? AND episode_id = ?");
        $stmt->execute([$points, $team_id, $episode_id]);
    } elseif ($action === 'subtract') {
        $stmt = $conn->prepare("UPDATE teams SET points = GREATEST(0, points - ?) WHERE id = ? AND episode_id = ?");
        $stmt->execute([$points, $team_id, $episode_id]);
    } else {
        $stmt = $conn->prepare("UPDATE teams SET points = ? WHERE id = ? AND episode_id = ?");
        $stmt->execute([$points, $team_id, $episode_id]);
    }
    
    error_log("SCORE CHANGE: Admin changed team $team_id score by $points ($action) in episode $episode_id");
    
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
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>