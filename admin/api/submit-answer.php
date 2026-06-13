<?php
/**
 * Submit Answer API
 * Records a team's answer (buzz in)
 */
require_once '../../includes/config-render.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$episode_id = isset($input['episode_id']) ? (int)$input['episode_id'] : 0;
$team_id    = isset($input['team_id'])    ? (int)$input['team_id']    : 0;
$question_id = isset($input['question_id']) ? (int)$input['question_id'] : 0;
$answer     = isset($input['answer'])     ? trim($input['answer'])     : '';

if (!$episode_id || !$team_id || !$question_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if (empty($answer)) {
    echo json_encode(['success' => false, 'error' => 'Answer cannot be empty']);
    exit;
}

try {
    // Verify team belongs to episode
    $stmt = $conn->prepare("SELECT * FROM quiz_teams WHERE id = ? AND episode_id = ?");
    $stmt->execute([$team_id, $episode_id]);
    $team = $stmt->fetch();

    if (!$team) {
        echo json_encode(['success' => false, 'error' => 'Team not found']);
        exit;
    }

    // Verify question exists
    $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();

    if (!$question) {
        echo json_encode(['success' => false, 'error' => 'Question not found']);
        exit;
    }

    // Insert buzz — update answer if team already buzzed on this question
    $stmt = $conn->prepare("
        INSERT INTO quiz_buzzes (episode_id, team_id, question_id, answer, buzzed_at)
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE 
            answer = VALUES(answer),
            buzzed_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$episode_id, $team_id, $question_id, $answer]);
    $buzz_id = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'buzz_id' => $buzz_id,
        'message' => 'Answer submitted successfully'
    ]);

} catch (PDOException $e) {
    error_log("Submit answer error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
