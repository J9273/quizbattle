<?php
/**
 * Submit Multiple Choice Answer API
 * Records a team's multiple choice answer
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
$selected_choice = isset($input['selected_choice']) ? strtoupper(trim($input['selected_choice'])) : '';

// Validation
if (!$episode_id || !$team_id || !$question_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if (!in_array($selected_choice, ['A', 'B', 'C', 'D'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid choice (must be A, B, C, or D)']);
    exit;
}

try {
    // Verify team belongs to episode
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ? AND episode_id = ?");
    $stmt->execute([$team_id, $episode_id]);
    $team = $stmt->fetch();
    
    if (!$team) {
        echo json_encode(['success' => false, 'error' => 'Team not found']);
        exit;
    }
    
    // Get question and check correct answer
    $stmt = $conn->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'error' => 'Question not found']);
        exit;
    }
    
    $correct_choice = strtoupper($question['correct_choice']);
    $is_correct = ($selected_choice === $correct_choice);
    
    // Get points for this question
    $stmt = $conn->prepare("SELECT points FROM points_config WHERE level = ?");
    $stmt->execute([$question['level']]);
    $points_config = $stmt->fetch();
    $points = $points_config ? $points_config['points'] : 0;
    
    // Store the answer
    $stmt = $conn->prepare("
        INSERT INTO multiple_choice_answers (episode_id, team_id, question_id, selected_choice, is_correct, answered_at)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (episode_id, team_id, question_id) 
        DO UPDATE SET 
            selected_choice = EXCLUDED.selected_choice,
            is_correct = EXCLUDED.is_correct,
            answered_at = CURRENT_TIMESTAMP
        RETURNING id
    ");
    $stmt->execute([$episode_id, $team_id, $question_id, $selected_choice, $is_correct ? 't' : 'f']);
    $answer = $stmt->fetch();
    
    // Award points immediately if correct
    $points_awarded = 0;
    if ($is_correct) {
        $stmt = $conn->prepare("UPDATE teams SET points = points + ? WHERE id = ?");
        $stmt->execute([$points, $team_id]);
        $points_awarded = $points;
    }
    
    echo json_encode([
        'success' => true,
        'answer_id' => $answer['id'],
        'is_correct' => $is_correct,
        'correct_choice' => $correct_choice,
        'points_awarded' => $points_awarded,
        'message' => 'Answer submitted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Submit MC answer error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
