<?php
require_once '../../includes/config-render.php';
header('Content-Type: application/json');

// Get episode_id from query parameter (optional - if not provided, return all available questions)
$episode_id = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;

try {
    // If episode_id is provided, get the episode's quiz format and filter questions accordingly
    if ($episode_id > 0) {
        // Get episode format
        $stmt = $conn->prepare("SELECT quiz_format FROM quiz_episodes WHERE id = ?");
        $stmt->execute([$episode_id]);
        $episode = $stmt->fetch();
        
        if (!$episode) {
            echo json_encode([
                'success' => false,
                'error' => 'Episode not found'
            ]);
            exit;
        }
        
        $episode_format = $episode['quiz_format'];
        
        // Get questions that match the episode format
        // If episode is 'cutthroat', show cutthroat and both
        // If episode is 'multiple_choice', show multiple_choice and both
        $stmt = $conn->prepare("
            SELECT q.id, q.question, q.theme, q.level, q.answer, q.question_format,
                   q.choice_a, q.choice_b, q.choice_c, q.choice_d, q.correct_choice,
                   pc.points 
            FROM questions q
            LEFT JOIN points_config pc ON q.level = pc.level
            WHERE q.availability = 'available'
            AND (q.question_format = ? OR q.question_format = 'both')
            ORDER BY q.theme, q.level
        ");
        $stmt->execute([$episode_format]);
        
    } else {
        // No episode_id provided - return all available questions
        $stmt = $conn->query("
            SELECT q.id, q.question, q.theme, q.level, q.answer, q.question_format,
                   q.choice_a, q.choice_b, q.choice_c, q.choice_d, q.correct_choice,
                   pc.points 
            FROM questions q
            LEFT JOIN points_config pc ON q.level = pc.level
            WHERE q.availability = 'available'
            ORDER BY q.theme, q.level
        ");
    }
    
    $questions = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
    
} catch (PDOException $e) {
    error_log("Get questions error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load questions'
    ]);
}
?>
