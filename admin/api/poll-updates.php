<?php
/**
 * Poll Updates API
 * Returns current episode state for a team
 * This is polled every 2-3 seconds by players
 */

require_once '../../includes/config-render.php';

header('Content-Type: application/json');

$episode_id = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

if (!$episode_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID required']);
    exit;
}

try {
    $response = [
        'success' => true,
        'timestamp' => time()
    ];
    
    // Get episode details
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        echo json_encode(['success' => false, 'error' => 'Episode not found']);
        exit;
    }
    
    $response['episode'] = [
        'id' => $episode['id'],
        'name' => $episode['episode_name'],
        'status' => $episode['status']
    ];
    
    // Get current question (from session/cache table if you implement one)
    // For now, we'll use a simple approach with a session table
    // You can create this table: CREATE TABLE episode_state (episode_id INT PRIMARY KEY, current_question_id INT, answer_revealed BOOLEAN)
    
    // Try to get current question from episode_state table
    $current_question = null;
    $answer_revealed = false;
    
    try {
        $stmt = $conn->prepare("SELECT current_question_id, answer_revealed FROM episode_state WHERE episode_id = ?");
        $stmt->execute([$episode_id]);
        $state = $stmt->fetch();
        
        if ($state && $state['current_question_id']) {
            // Get question details
            $stmt = $conn->prepare("
                SELECT q.*, pc.points 
                FROM questions q
                LEFT JOIN points_config pc ON q.level = pc.level
                WHERE q.id = ?
            ");
            $stmt->execute([$state['current_question_id']]);
            $question = $stmt->fetch();
            
            if ($question) {
                $current_question = [
                    'id' => $question['id'],
                    'question' => $question['question'],
                    'theme' => $question['theme'],
                    'level' => $question['level'],
                    'points' => $question['points']
                ];
                
                // Include answer only if revealed
                if ($state['answer_revealed']) {
                    $current_question['answer'] = $question['answer'];
                    $answer_revealed = true;
                }
            }
        }
    } catch (PDOException $e) {
        // episode_state table doesn't exist yet, that's okay
        // The host will need to create it or we'll add it in a migration
    }
    
    $response['current_question'] = $current_question;
    $response['answer_revealed'] = $answer_revealed;
    
    // Get scores (all teams)
    $stmt = $conn->prepare("
        SELECT id, team_name, points, position 
        FROM teams 
        WHERE episode_id = ? 
        ORDER BY points DESC, team_name ASC
    ");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll();
    
    $response['scores'] = $teams;
    
    // Get own team info if team_id provided
    if ($team_id) {
        $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ? AND episode_id = ?");
        $stmt->execute([$team_id, $episode_id]);
        $own_team = $stmt->fetch();
        
        if ($own_team) {
            $response['own_team'] = [
                'id' => $own_team['id'],
                'name' => $own_team['team_name'],
                'points' => $own_team['points'],
                'position' => $own_team['position']
            ];
        }
    }
    
    // Check for recent activity (last 10 seconds)
    // This helps reduce unnecessary updates
    $response['has_updates'] = true; // For now, always true
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Poll updates error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
