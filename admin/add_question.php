<?php
require_once '../includes/config-render.php';
require_once '../includes/auth.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = $_POST['question'] ?? '';
    $theme = $_POST['theme'] ?? '';
    $level = $_POST['level'] ?? '';
    $answer = $_POST['answer'] ?? '';
    $availability = $_POST['availability'] ?? 'available';
    
    if (!empty($question) && !empty($theme) && !empty($level) && !empty($answer)) {
        try {
            $stmt = $conn->prepare("INSERT INTO questions (question, theme, level, answer, availability) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$question, $theme, $level, $answer, $availability]);
            $success = "Question added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding question: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all fields";
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
    <nav class="bg-white shadow-lg mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="text-2xl font-bold text-gray-800">Quiz Battle</a>
                    <a href="questions.php" class="text-gray-600 hover:text-gray-800">Questions</a>
                    <a href="episodes.php" class="text-gray-600 hover:text-gray-800">Episodes</a>
                </div>
                <div class="flex items-center">
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-3xl mx-auto px-4">
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
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Question</label>
                    <textarea name="question" rows="3" required
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter the quiz question"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Theme</label>
                    <input type="text" name="theme" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Geography, History, Science">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Difficulty Level</label>
                    <select name="level" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select level</option>
                        <option value="easy">Easy (1 point)</option>
                        <option value="medium">Medium (5 points)</option>
                        <option value="hard">Hard (10 points)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Answer</label>
                    <input type="text" name="answer" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter the correct answer">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Availability</label>
                    <select name="availability" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                        Add Question
                    </button>
                    <a href="questions.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
