<?php
/**
 * Export Questions as CSV
 */
require_once '../includes/bootstrap.php';
session_start();
require_once '../includes/config-render.php';
require_once '../includes/auth.php';

requireLogin();

try {
    // Fetch all questions
    $stmt = $conn->query("
        SELECT 
            id,
            question,
            theme,
            level,
            answer,
            question_format,
            choice_a,
            choice_b,
            choice_c,
            choice_d,
            correct_choice,
            availability,
            created_at,
            updated_at
        FROM questions
        ORDER BY theme, level, id
    ");
    
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers to trigger download
    $filename = 'questions_backup_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header row
    fputcsv($output, [
        'ID',
        'Question',
        'Theme',
        'Level',
        'Answer',
        'Format',
        'Choice A',
        'Choice B',
        'Choice C',
        'Choice D',
        'Correct Choice',
        'Availability',
        'Created At',
        'Updated At'
    ]);
    
    // Write each question row
    foreach ($questions as $q) {
        fputcsv($output, [
            $q['id'],
            $q['question'],
            $q['theme'],
            $q['level'],
            $q['answer'],
            $q['question_format'],
            $q['choice_a'],
            $q['choice_b'],
            $q['choice_c'],
            $q['choice_d'],
            $q['correct_choice'],
            $q['availability'],
            $q['created_at'],
            $q['updated_at']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    error_log("Export questions error: " . $e->getMessage());
    http_response_code(500);
    echo "Error exporting questions: " . $e->getMessage();
}
?>
