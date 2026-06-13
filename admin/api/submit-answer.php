<?php
/**
 * Submit Answer API
 * Handles both CutThroat (buzz) and Multiple Choice answers
 */
require_once '../../includes/config-render.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
error_log("Submit answer input: " . json_encode($input)); // ← temporary
$episode_id  = isset($input['episode_id'])  ? (int)$input['episode_id']           : 0;
$team_id     = isset($input['team_id'])     ? (int)$input['team_id']              : 0;
$question_id = isset($input['question_id']) ? (int)$input['question_id']          : 0;
$user_answer = isset($input['answer'])      ? trim($input['answer'])              : '';

if (!$episode_id || !$team_id || !$question_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    // Step 1 — Check episode state: is there an active question?
    $stmt = $conn->prepare("
        SELECT es.current_question_id, es.answer_revealed
        FROM episode_state es
        WHERE es.episode_id = ?
    ");
    $stmt->execute([$episode_id]);
    $state = $stmt->fetch();

    if (!$state || !$state['current_question_id']) {
        echo json_encode(['success' => false, 'error' => 'No active question for this episode']);
        exit;
    }

    if ($state['current_question_id'] != $question_id) {
        echo json_encode(['success' => false, 'error' => 'Question is no longer active']);
        exit;
    }

    // Step 2 — Get the question
    $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();

    if (!$question) {
        echo json_encode(['success' => false, 'error' => 'Question not found']);
        exit;
    }

    // Step 3 — Verify team belongs to episode
    $stmt = $conn->prepare("SELECT * FROM quiz_teams WHERE id = ? AND episode_id = ?");
    $stmt->execute([$team_id, $episode_id]);
    $team = $stmt->fetch();

    if (!$team) {
      //  echo json_encode(['success' => false, 'error' => 'Team not found']);
         echo json_encode([
        'success' => false, 
        'error' => 'Answer cannot be empty',
        'received' => $input  // ← temporary, shows full payload
    ]);
        
        exit;
    }

    if (empty($user_answer)) {
       // echo json_encode(['success' => false, 'error' => 'Answer cannot be empty']);
        exit;
    }

    // Step 4 — Get points for this question level
    $stmt = $conn->prepare("SELECT points FROM quiz_points_config WHERE level = ?");
    $stmt->execute([$question['level']]);
    $points_config = $stmt->fetch();
    $points = $points_config ? (int)$points_config['points'] : 0;

    // Step 5 — Compare answer and insert into correct table
    $question_format = strtolower($question['question_format']);

    if ($question_format === 'multiple_choice' || $question_format === 'both') {
        // Multiple Choice — compare selected choice (A/B/C/D)
        $selected_choice = strtoupper(trim($user_answer));

        if (!in_array($selected_choice, ['A', 'B', 'C', 'D'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid choice (must be A, B, C, or D)']);
            exit;
        }

        $correct_choice = strtoupper($question['correct_choice']);
        $is_correct = ($selected_choice === $correct_choice) ? 1 : 0;

        // Check if anyone already answered correctly (first correct answer only gets points)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as correct_count 
            FROM multiple_choice_answers 
            WHERE episode_id = ? AND question_id = ? AND is_correct = 1
        ");
        $stmt->execute([$episode_id, $question_id]);
        $result = $stmt->fetch();
        $someone_already_correct = ($result['correct_count'] > 0);

        $points_awarded = ($is_correct && !$someone_already_correct) ? $points : 0;

        // Insert or update answer
        $stmt = $conn->prepare("
            INSERT INTO multiple_choice_answers 
                (episode_id, team_id, question_id, selected_choice, is_correct, points_awarded, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                selected_choice  = VALUES(selected_choice),
                is_correct       = VALUES(is_correct),
                points_awarded   = VALUES(points_awarded),
                submitted_at     = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$episode_id, $team_id, $question_id, $selected_choice, $is_correct, $points_awarded]);

        // Award points if first correct answer
        if ($points_awarded > 0) {
            $stmt = $conn->prepare("UPDATE quiz_teams SET points = points + ? WHERE id = ?");
            $stmt->execute([$points_awarded, $team_id]);
            $message = 'Correct! You got it first! +' . $points_awarded . ' points';
        } elseif ($is_correct) {
            $message = 'Correct, but someone beat you to it! No points awarded.';
        } else {
            $message = 'Incorrect answer. The correct answer was ' . $correct_choice;
        }

        echo json_encode([
            'success'        => true,
            'is_correct'     => (bool)$is_correct,
            'correct_choice' => $correct_choice,
            'points_awarded' => $points_awarded,
            'was_first'      => ($is_correct && !$someone_already_correct),
            'message'        => $message
        ]);

    } else {
        // CutThroat — compare text answer, insert into quiz_buzzes
        $correct_answer  = strtolower(trim($question['answer']));
        $submitted_answer = strtolower($user_answer);
        $is_correct = ($submitted_answer === $correct_answer) ? 1 : 0;
        $points_awarded = $is_correct ? $points : 0;

        $stmt = $conn->prepare("
            INSERT INTO quiz_buzzes 
                (episode_id, team_id, question_id, buzzed_at, is_correct, points_awarded)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_correct     = VALUES(is_correct),
                points_awarded = VALUES(points_awarded),
                buzzed_at      = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$episode_id, $team_id, $question_id, $is_correct, $points_awarded]);
        $buzz_id = $conn->lastInsertId();

        // Award points if correct
        if ($points_awarded > 0) {
            $stmt = $conn->prepare("UPDATE quiz_teams SET points = points + ? WHERE id = ?");
            $stmt->execute([$points_awarded, $team_id]);
        }

        echo json_encode([
            'success'        => true,
            'buzz_id'        => $buzz_id,
            'is_correct'     => (bool)$is_correct,
            'points_awarded' => $points_awarded,
            'message'        => $is_correct ? 'Correct! +' . $points_awarded . ' points' : 'Incorrect answer'
        ]);
    }

} catch (PDOException $e) {
    error_log("Submit answer error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . $e->getMessage()
    ]);
}
