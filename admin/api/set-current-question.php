<?php
/**
 * Set Current Question API
 * Host uses this to display a question to all players
 */

require_once '../../includes/config-render.php';
require_once '../../includes/auth.php';

requireLogin(); // Only logged-in admins can set questions

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$episode_id = isset($input['episode_id']) ? (int)$input['episode_id'] : 0;
$question_id = isset($input['question_id']) ? (int)$input['question_id'] : 0;
$action = isset($input['action']) ? $input['action'] : 'display'; // display, reveal, clear

if (!$episode_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID required']);
    exit;
}

try {
    // Ensure episode_state table exists
    $conn->exec("
        CREATE TABLE IF NOT EXISTS episode_state (
            episode_id INTEGER PRIMARY KEY REFERENCES quiz_episodes(id) ON DELETE CASCADE,
            current_question_id INTEGER REFERENCES questions(id) ON DELETE SET NULL,
            answer_revealed BOOLEAN DEFAULT FALSE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    if ($action === 'display') {
        // Display a new question
        if (!$question_id) {
            echo json_encode(['success' => false, 'error' => 'Question ID required']);
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
        
        // Set current question
        $stmt = $conn->prepare("
            INSERT INTO episode_state (episode_id, current_question_id, answer_revealed, updated_at)
            VALUES (?, ?, FALSE, CURRENT_TIMESTAMP)
            ON CONFLICT (episode_id) 
            DO UPDATE SET 
                current_question_id = EXCLUDED.current_question_id,
                answer_revealed = FALSE,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$episode_id, $question_id]);
        
        echo json_encode([
            'success' => true,
            'action' => 'display',
            'question_id' => $question_id,
            'message' => 'Question displayed to all players'
        ]);
        
    } elseif ($action === 'reveal') {
        // Reveal the answer
        $stmt = $conn->prepare("
            UPDATE episode_state 
            SET answer_revealed = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE episode_id = ?
        ");
        $stmt->execute([$episode_id]);
        
        echo json_encode([
            'success' => true,
            'action' => 'reveal',
            'message' => 'Answer revealed to all players'
        ]);
        
    } elseif ($action === 'clear') {
        // Clear current question
        $stmt = $conn->prepare("
            UPDATE episode_state 
            SET current_question_id = NULL, answer_revealed = FALSE, updated_at = CURRENT_TIMESTAMP
            WHERE episode_id = ?
        ");
        $stmt->execute([$episode_id]);
        
        echo json_encode([
            'success' => true,
            'action' => 'clear',
            'message' => 'Question cleared'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Set question error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
