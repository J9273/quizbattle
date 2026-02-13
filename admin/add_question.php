// Add this code right after line 36 (after validation, before the try block):

// For multiple choice questions, set answer to the text of the correct choice
if ($question_format === 'multiple_choice' && empty($answer)) {
    $choices = [
        'A' => $choice_a,
        'B' => $choice_b,
        'C' => $choice_c,
        'D' => $choice_d
    ];
    $answer = $choices[$correct_choice] ?? '';
}

// Also change line 51 from:
$answer ?: null,

// To:
$answer,
