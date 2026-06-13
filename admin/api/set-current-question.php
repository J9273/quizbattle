<?php
/**
 * Set Current Question API
 * Host uses this to display a question to all players
 */
require_once '../../includes/config-render.php';
require_once '../../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$episode_id  = isset($input['episode_id'])  ? (int)$input['episode_id']  : 0;
$question_id = isset($input['question_id']) ? (int)$input['question_id'] : 0;
$action      = isset($input['action'])      ? $input['action']           : 'display';

if (!$episode_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID required']);
    exit;
}

try {
    // Ensure episode_state table exists
    $conn->exec("
        CREATE TABLE IF NOT EXISTS episode_state (
            episode_id INT PRIMARY KEY,
            current_question_id INT DEFAULT NULL,
            answer_revealed TINYINT(1) DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (episode_id) REFERENCES quiz_episodes(id) ON DELETE CASCADE,
            FOREIGN KEY (current_question_id) REFERENCES quiz_questions(id) ON DELETE SET NULL
        )
    ");

    if ($action === 'display') {

        if (!$question_id) {
            echo json_encode(['success' => false, 'error' => 'Question ID required']);
            exit;
        }

        // Test 1 - verify question exists
        $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch();

        if (!$question) {
            echo json_encode(['success' => false, 'error' => 'Question not found: ' . $question_id]);
            exit;
        }

        // Test 2 - insert into episode_state
        $stmt = $conn->prepare("
            INSERT INTO episode_state (episode_id, current_question_id, answer_revealed, updated_at)
            VALUES (?, ?, 0, CURRENT_TIMESTAMP) AS new_vals
            ON DUPLICATE KEY UPDATE
                current_question_id = new_vals.current_question_id,
                answer_revealed = 0,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$episode_id, $question_id]);

        echo json_encode([
            'success'     => true,
            'action'      => 'display',
            'question_id' => $question_id,
            'message'     => 'Question displayed to all players'
        ]);

    } elseif ($action === 'reveal') {

        $stmt = $conn->prepare("
            UPDATE episode_state 
            SET answer_revealed = 1, updated_at = CURRENT_TIMESTAMP
            WHERE episode_id = ?
        ");
        $stmt->execute([$episode_id]);

        echo json_encode([
            'success' => true,
            'action'  => 'reveal',
            'message' => 'Answer revealed to all players'
        ]);

    } elseif ($action === 'clear') {

        $stmt = $conn->prepare("
            UPDATE episode_state 
            SET current_question_id = NULL, answer_revealed = 0, updated_at = CURRENT_TIMESTAMP
            WHERE episode_id = ?
        ");
        $stmt->execute([$episode_id]);

        echo json_encode([
            'success' => true,
            'action'  => 'clear',
            'message' => 'Question cleared'
        ]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    error_log("Set question error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
