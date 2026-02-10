<?php
/**
 * Submit Answer API
 * Records a team's answer (buzz in)
 */

require_once '../../includes/config-render.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$episode_id = isset($input['episode_id']) ? (int)$input['episode_id'] : 0;
$team_id = isset($input['team_id']) ? (int)$input['team_id'] : 0;
$question_id = isset($input['question_id']) ? (int)$input['question_id'] : 0;
$answer = isset($input['answer']) ? trim($input['answer']) : '';

// Validation
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
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ? AND episode_id = ?");
    $stmt->execute([$team_id, $episode_id]);
    $team = $stmt->fetch();
    
    if (!$team) {
        echo json_encode(['success' => false, 'error' => 'Team not found']);
        exit;
    }
    
    // Verify question exists
    $stmt = $conn->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'error' => 'Question not found']);
        exit;
    }
    
    // Store the answer in buzzes table
    // First, check if buzzes table exists, if not create it
    try {
        $stmt = $conn->prepare("
            INSERT INTO buzzes (episode_id, team_id, question_id, answer, buzzed_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $stmt->execute([$episode_id, $team_id, $question_id, $answer]);
        $buzz = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'buzz_id' => $buzz['id'],
            'message' => 'Answer submitted successfully'
        ]);
        
    } catch (PDOException $e) {
        // Buzzes table doesn't exist, create it
        if (strpos($e->getMessage(), 'buzzes') !== false) {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS buzzes (
                    id SERIAL PRIMARY KEY,
                    episode_id INTEGER NOT NULL REFERENCES quiz_episodes(id) ON DELETE CASCADE,
                    team_id INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
                    question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
                    answer TEXT NOT NULL,
                    buzzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(episode_id, team_id, question_id)
                )
            ");
            
            // Try again
            $stmt = $conn->prepare("
                INSERT INTO buzzes (episode_id, team_id, question_id, answer, buzzed_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (episode_id, team_id, question_id) 
                DO UPDATE SET answer = EXCLUDED.answer, buzzed_at = CURRENT_TIMESTAMP
                RETURNING id
            ");
            $stmt->execute([$episode_id, $team_id, $question_id, $answer]);
            $buzz = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'buzz_id' => $buzz['id'],
                'message' => 'Answer submitted successfully'
            ]);
        } else {
            throw $e;
        }
    }
    
} catch (PDOException $e) {
    error_log("Submit answer error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
