<?php
require_once '../includes/config-render.php';
require_once '../includes/auth.php';
requireLogin();

// Fetch all questions
$stmt = $conn->query("SELECT * FROM questions ORDER BY created_at DESC");
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions - Quiz Battle</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="text-2xl font-bold text-gray-800">Quiz Battle</a>
                    <a href="questions.php" class="text-blue-600 font-medium">Questions</a>
                    <a href="episodes.php" class="text-gray-600 hover:text-gray-800">Episodes</a>
                </div>
                <div class="flex items-center">
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Questions</h1>
            <a href="add_question.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                ➕ Add Question
            </a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Question</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Theme</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Level</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Answer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars(substr($q['question'], 0, 60)) ?>...</td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($q['theme']) ?></td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-1 text-xs rounded <?= $q['level'] === 'hard' ? 'bg-red-100 text-red-800' : ($q['level'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                                    <?= ucfirst($q['level']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars(substr($q['answer'], 0, 30)) ?></td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-1 text-xs rounded <?= $q['availability'] === 'available' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= ucfirst($q['availability']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($questions)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                No questions yet. <a href="add_question.php" class="text-blue-600 hover:underline">Add your first question!</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
