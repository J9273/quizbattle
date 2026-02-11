<?php
/**
 * Get Multiple Choice Results API
 * Returns answer breakdown and statistics for a question
 */

require_once '../../includes/config-render.php';

header('Content-Type: application/json');

$episode_id = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;
$question_id = isset($_GET['question_id']) ? (int)$_GET['question_id'] : 0;

if (!$episode_id || !$question_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID and Question ID required']);
    exit;
}

try {
    // Get question details
    $stmt = $conn->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'error' => 'Question not found']);
        exit;
    }
    
    $correct_choice = strtoupper($question['correct_choice']);
    
    // Get all answers for this question in this episode
    $stmt = $conn->prepare("
        SELECT mca.*, t.team_name, t.points
        FROM multiple_choice_answers mca
        JOIN teams t ON mca.team_id = t.id
        WHERE mca.episode_id = ? AND mca.question_id = ?
        ORDER BY mca.answered_at ASC
    ");
    $stmt->execute([$episode_id, $question_id]);
    $answers = $stmt->fetchAll();
    
    // Calculate breakdown
    $breakdown = [
        'A' => ['count' => 0, 'percentage' => 0, 'teams' => []],
        'B' => ['count' => 0, 'percentage' => 0, 'teams' => []],
        'C' => ['count' => 0, 'percentage' => 0, 'teams' => []],
        'D' => ['count' => 0, 'percentage' => 0, 'teams' => []]
    ];
    
    $correct_teams = [];
    $total_answers = count($answers);
    
    foreach ($answers as $answer) {
        $choice = strtoupper($answer['selected_choice']);
        $breakdown[$choice]['count']++;
        $breakdown[$choice]['teams'][] = $answer['team_name'];
        
        if ($answer['is_correct']) {
            $correct_teams[] = $answer['team_name'];
        }
    }
    
    // Calculate percentages
    foreach ($breakdown as $choice => $data) {
        if ($total_answers > 0) {
            $breakdown[$choice]['percentage'] = round(($data['count'] / $total_answers) * 100);
        }
    }
    
    // Get all teams to see who hasn't answered
    $stmt = $conn->prepare("
        SELECT t.id, t.team_name
        FROM teams t
        WHERE t.episode_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM multiple_choice_answers mca
            WHERE mca.team_id = t.id 
            AND mca.episode_id = ? 
            AND mca.question_id = ?
        )
    ");
    $stmt->execute([$episode_id, $episode_id, $question_id]);
    $no_answer_teams = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'total_answers' => $total_answers,
        'breakdown' => $breakdown,
        'correct_choice' => $correct_choice,
        'correct_teams' => $correct_teams,
        'no_answer_teams' => array_column($no_answer_teams, 'team_name'),
        'answers' => $answers // Full details if needed
    ]);
    
} catch (PDOException $e) {
    error_log("Get MC results error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
