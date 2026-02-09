<?php
require_once '../../includes/config-render.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->query("
        SELECT q.id, q.question, q.theme, q.level, q.answer, pc.points 
        FROM questions q
        LEFT JOIN points_config pc ON q.level = pc.level
        WHERE q.availability = 'available'
        ORDER BY q.theme, q.level
    ");
    
    $questions = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load questions'
    ]);
}
