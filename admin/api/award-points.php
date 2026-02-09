<?php
/**
 * Award Points API
 * Awards points to a team based on question difficulty
 */

require_once '../../includes/config-render.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$episode_id = isset($input['episode_id']) ? (int)$input['episode_id'] : 0;
$team_id = isset($input['team_id']) ? (int)$input['team_id'] : 0;
$question_id = isset($input['question_id']) ? (int)$input['question_id'] : 0;

if (!$episode_id || !$team_id || !$question_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Episode ID, Team ID, and Question ID required'
    ]);
    exit;
}

try {
    // Get question details
    $stmt = $conn->prepare("SELECT level FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'error' => 'Question not found']);
        exit;
    }
    
    // Get points for this level
    $stmt = $conn->prepare("SELECT points FROM points_config WHERE level = ?");
    $stmt->execute([$question['level']]);
    $config = $stmt->fetch();
    $points = $config ? $config['points'] : 1;
    
    // Award points
    $stmt = $conn->prepare("UPDATE teams SET points = points + ? WHERE id = ? AND episode_id = ?");
    $stmt->execute([$points, $team_id, $episode_id]);
    
    // Get updated team
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'team' => $team,
        'points_awarded' => $points,
        'level' => $question['level']
    ]);
    
} catch (PDOException $e) {
    error_log("Award points error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
