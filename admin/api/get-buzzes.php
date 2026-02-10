<?php
/**
 * Get Buzzes API
 * Returns all buzzes (answers) for current question
 * Used by host to see who buzzed in
 */

require_once '../../includes/config-render.php';
require_once '../../includes/auth.php';

requireLogin(); // Only logged-in admins can see buzzes

header('Content-Type: application/json');

$episode_id = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;
$question_id = isset($_GET['question_id']) ? (int)$_GET['question_id'] : 0;

if (!$episode_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID required']);
    exit;
}

try {
    if ($question_id) {
        // Get buzzes for specific question
        $stmt = $conn->prepare("
            SELECT 
                b.id,
                b.answer,
                b.buzzed_at,
                t.id as team_id,
                t.team_name,
                t.points,
                q.id as question_id,
                q.question,
                q.answer as correct_answer
            FROM buzzes b
            JOIN teams t ON b.team_id = t.id
            JOIN questions q ON b.question_id = q.id
            WHERE b.episode_id = ? AND b.question_id = ?
            ORDER BY b.buzzed_at ASC
        ");
        $stmt->execute([$episode_id, $question_id]);
    } else {
        // Get all buzzes for episode (recent first)
        $stmt = $conn->prepare("
            SELECT 
                b.id,
                b.answer,
                b.buzzed_at,
                t.id as team_id,
                t.team_name,
                t.points,
                q.id as question_id,
                q.question,
                q.answer as correct_answer
            FROM buzzes b
            JOIN teams t ON b.team_id = t.id
            JOIN questions q ON b.question_id = q.id
            WHERE b.episode_id = ?
            ORDER BY b.buzzed_at DESC
            LIMIT 50
        ");
        $stmt->execute([$episode_id]);
    }
    
    $buzzes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'buzzes' => $buzzes,
        'count' => count($buzzes)
    ]);
    
} catch (PDOException $e) {
    // Buzzes table might not exist yet
    if (strpos($e->getMessage(), 'buzzes') !== false || strpos($e->getMessage(), 'relation') !== false) {
        echo json_encode([
            'success' => true,
            'buzzes' => [],
            'count' => 0,
            'message' => 'No buzzes yet'
        ]);
    } else {
        error_log("Get buzzes error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Database error'
        ]);
    }
}
