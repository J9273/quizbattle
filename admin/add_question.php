<?php
require_once '../includes/bootstrap.php';
session_start();
require_once '../includes/config-render.php';
require_once '../includes/auth.php';

requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = $_POST['question'] ?? '';
    $theme = $_POST['theme'] ?? '';
    $level = $_POST['level'] ?? '';
    $question_format = $_POST['question_format'] ?? 'cutthroat';
    $availability = $_POST['availability'] ?? 'available';
    
    // CutThroat fields
    $answer = $_POST['answer'] ?? '';
    
    // Multiple Choice fields
    $choice_a = $_POST['choice_a'] ?? '';
    $choice_b = $_POST['choice_b'] ?? '';
    $choice_c = $_POST['choice_c'] ?? '';
    $choice_d = $_POST['choice_d'] ?? '';
    $correct_choice = $_POST['correct_choice'] ?? '';
    
    // Validation
    if (empty($question) || empty($theme) || empty($level)) {
        $error = "Question, theme, and level are required";
    } elseif ($question_format === 'cutthroat' && empty($answer)) {
        $error = "Answer is required for CutThroat format";
    } elseif ($question_format === 'multiple_choice' && (empty($choice_a) || empty($choice_b) || empty($choice_c) || empty($correct_choice))) {
        $error = "At least 3 choices (A, B, C) and correct answer are required for Multiple Choice format";
    } elseif ($question_format === 'both' && (empty($answer) || empty($choice_a) || empty($choice_b) || empty($choice_c) || empty($correct_choice))) {
        $error = "Both answer text and multiple choice options are required for 'Both' format";
    } else 		
	{
		
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
				
        try {
            $stmt = $conn->prepare("
                INSERT INTO questions (
                    question, theme, level, answer, question_format,
                    choice_a, choice_b, choice_c, choice_d, correct_choice, availability
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $question, 
                $theme, 
                $level, 
                $answer,
                $question_format,
                $choice_a ?: null,
                $choice_b ?: null,
                $choice_c ?: null,
                $choice_d ?: null,
                $correct_choice ?: null,
                $availability
            ]);
            
            $success = "Question added successfully!";
            
            // Clear form
            $_POST = [];
            
        } catch (PDOException $e) {
            $error = "Error adding question: " . $e->getMessage();
            error_log("Add question error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Question - Quiz Battle</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    
    <!-- Navigation -->
    <nav class="bg-white shadow-lg mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="text-2xl font-bold text-gray-800">Quiz Battle</a>
                    <a href="questions.php" class="text-gray-600 hover:text-gray-800">Questions</a>
                    <a href="episodes.php" class="text-gray-600 hover:text-gray-800">Episodes</a>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-700 mr-4"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Add New Question</h1>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-8">
            <form method="POST" id="question-form" class="space-y-6">
                
                <!-- Question Format Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        Question Format <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="relative flex flex-col p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-blue-500 transition-colors">
                            <input type="radio" name="question_format" value="cutthroat" 
                                   <?= ($_POST['question_format'] ?? 'cutthroat') === 'cutthroat' ? 'checked' : '' ?>
                                   onchange="updateFormFields()" class="sr-only">
                            <div class="format-card">
                                <div class="text-3xl mb-2">🎯</div>
                                <div class="font-bold text-gray-800">CutThroat</div>
                                <div class="text-xs text-gray-600 mt-1">Buzz in with text answer</div>
                            </div>
                        </label>

                        <label class="relative flex flex-col p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-blue-500 transition-colors">
                            <input type="radio" name="question_format" value="multiple_choice"
                                   <?= ($_POST['question_format'] ?? '') === 'multiple_choice' ? 'checked' : '' ?>
                                   onchange="updateFormFields()" class="sr-only">
                            <div class="format-card">
                                <div class="text-3xl mb-2">✓</div>
                                <div class="font-bold text-gray-800">Multiple Choice</div>
                                <div class="text-xs text-gray-600 mt-1">Select from A/B/C/D</div>
                            </div>
                        </label>

                        <label class="relative flex flex-col p-4 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-blue-500 transition-colors">
                            <input type="radio" name="question_format" value="both"
                                   <?= ($_POST['question_format'] ?? '') === 'both' ? 'checked' : '' ?>
                                   onchange="updateFormFields()" class="sr-only">
                            <div class="format-card">
                                <div class="text-3xl mb-2">🎲</div>
                                <div class="font-bold text-gray-800">Both</div>
                                <div class="text-xs text-gray-600 mt-1">Works in either format</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Basic Fields -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Question <span class="text-red-500">*</span>
                    </label>
                    <textarea name="question" rows="3" required
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter the quiz question"><?= htmlspecialchars($_POST['question'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Theme <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="theme" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., Geography, History, Science"
                               value="<?= htmlspecialchars($_POST['theme'] ?? '') ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Difficulty Level <span class="text-red-500">*</span>
                        </label>
                        <select name="level" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Select level</option>
                            <option value="easy" <?= ($_POST['level'] ?? '') === 'easy' ? 'selected' : '' ?>>Easy (1 point)</option>
                            <option value="medium" <?= ($_POST['level'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium (5 points)</option>
                            <option value="hard" <?= ($_POST['level'] ?? '') === 'hard' ? 'selected' : '' ?>>Hard (10 points)</option>
                        </select>
                    </div>
                </div>

                <!-- CutThroat Answer Field -->
                <div id="cutthroat-fields">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded mb-4">
                        <h3 class="font-bold text-blue-800 mb-2">🎯 CutThroat Format</h3>
                        <p class="text-sm text-blue-700">Players will type their answer and buzz in</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Answer <span class="text-red-500" id="answer-required">*</span>
                        </label>
                        <input type="text" name="answer" id="answer-field"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter the correct answer"
                               value="<?= htmlspecialchars($_POST['answer'] ?? '') ?>">
                    </div>
                </div>

                <!-- Multiple Choice Fields -->
                <div id="mc-fields" class="hidden">
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded mb-4">
                        <h3 class="font-bold text-green-800 mb-2">✓ Multiple Choice Format</h3>
                        <p class="text-sm text-green-700">Players will select from these options (at least 3 required, 4th is optional)</p>
                    </div>

                    <div class="space-y-3">
                        <div class="flex gap-3 items-center">
                            <span class="font-bold text-gray-700 w-8">A:</span>
                            <input type="text" name="choice_a" id="choice_a"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="First choice"
                                   value="<?= htmlspecialchars($_POST['choice_a'] ?? '') ?>">
                            <label class="flex items-center">
                                <input type="radio" name="correct_choice" value="A" 
                                       <?= ($_POST['correct_choice'] ?? '') === 'A' ? 'checked' : '' ?>
                                       class="w-5 h-5 text-green-600">
                                <span class="ml-2 text-sm text-gray-600">Correct</span>
                            </label>
                        </div>

                        <div class="flex gap-3 items-center">
                            <span class="font-bold text-gray-700 w-8">B:</span>
                            <input type="text" name="choice_b" id="choice_b"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="Second choice"
                                   value="<?= htmlspecialchars($_POST['choice_b'] ?? '') ?>">
                            <label class="flex items-center">
                                <input type="radio" name="correct_choice" value="B"
                                       <?= ($_POST['correct_choice'] ?? '') === 'B' ? 'checked' : '' ?>
                                       class="w-5 h-5 text-green-600">
                                <span class="ml-2 text-sm text-gray-600">Correct</span>
                            </label>
                        </div>

                        <div class="flex gap-3 items-center">
                            <span class="font-bold text-gray-700 w-8">C:</span>
                            <input type="text" name="choice_c" id="choice_c"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="Third choice"
                                   value="<?= htmlspecialchars($_POST['choice_c'] ?? '') ?>">
                            <label class="flex items-center">
                                <input type="radio" name="correct_choice" value="C"
                                       <?= ($_POST['correct_choice'] ?? '') === 'C' ? 'checked' : '' ?>
                                       class="w-5 h-5 text-green-600">
                                <span class="ml-2 text-sm text-gray-600">Correct</span>
                            </label>
                        </div>

                        <div class="flex gap-3 items-center">
                            <span class="font-bold text-gray-700 w-8">D:</span>
                            <input type="text" name="choice_d" id="choice_d"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="Fourth choice (optional)"
                                   value="<?= htmlspecialchars($_POST['choice_d'] ?? '') ?>">
                            <label class="flex items-center">
                                <input type="radio" name="correct_choice" value="D"
                                       <?= ($_POST['correct_choice'] ?? '') === 'D' ? 'checked' : '' ?>
                                       class="w-5 h-5 text-green-600">
                                <span class="ml-2 text-sm text-gray-600">Correct</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Availability -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Availability
                    </label>
                    <select name="availability"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="available" <?= ($_POST['availability'] ?? 'available') === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="unavailable" <?= ($_POST['availability'] ?? '') === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                    </select>
                </div>

                <!-- Submit Buttons -->
                <div class="flex gap-4 pt-4">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                        Add Question
                    </button>
                    <a href="questions.php" class="flex-1 text-center bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateFormFields() {
            const format = document.querySelector('input[name="question_format"]:checked').value;
            const cutthroatFields = document.getElementById('cutthroat-fields');
            const mcFields = document.getElementById('mc-fields');
            const answerField = document.getElementById('answer-field');
            const answerRequired = document.getElementById('answer-required');
            
            // Update visual selection
            document.querySelectorAll('input[name="question_format"]').forEach(radio => {
                const card = radio.closest('label');
                if (radio.checked) {
                    card.classList.add('border-blue-500', 'bg-blue-50');
                    card.classList.remove('border-gray-300');
                } else {
                    card.classList.remove('border-blue-500', 'bg-blue-50');
                    card.classList.add('border-gray-300');
                }
            });
            
            // Show/hide fields based on format
            if (format === 'cutthroat') {
                cutthroatFields.classList.remove('hidden');
                mcFields.classList.add('hidden');
                answerField.required = true;
                answerRequired.classList.remove('hidden');
                
                // Clear MC required
                document.getElementById('choice_a').required = false;
                document.getElementById('choice_b').required = false;
                document.getElementById('choice_c').required = false;
                
            } else if (format === 'multiple_choice') {
                cutthroatFields.classList.add('hidden');
                mcFields.classList.remove('hidden');
                answerField.required = false;
                answerRequired.classList.add('hidden');
                
                // Set MC required
                document.getElementById('choice_a').required = true;
                document.getElementById('choice_b').required = true;
                document.getElementById('choice_c').required = true;
                
            } else { // both
                cutthroatFields.classList.remove('hidden');
                mcFields.classList.remove('hidden');
                answerField.required = true;
                answerRequired.classList.remove('hidden');
                
                // Set MC required
                document.getElementById('choice_a').required = true;
                document.getElementById('choice_b').required = true;
                document.getElementById('choice_c').required = true;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', updateFormFields);
    </script>

    <style>
        input[type="radio"]:checked + .format-card {
            /* Additional styling handled by JavaScript */
        }
    </style>
</body>
</html>
