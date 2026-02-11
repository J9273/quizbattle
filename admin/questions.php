<?php
require_once '../includes/bootstrap.php';
session_start();
require_once '../includes/config-render.php';
require_once '../includes/auth.php';

requireLogin();

$success = '';
$error = '';
$upload_results = null;

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload error";
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $error = "File too large (max 5MB)";
    } elseif (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['csv', 'txt'])) {
        $error = "Invalid file type. Please upload a CSV file";
    } else {
        // Process CSV
        $handle = fopen($file['tmp_name'], 'r');
        
        if ($handle === false) {
            $error = "Could not open CSV file";
        } else {
            $imported = 0;
            $skipped = 0;
            $errors = [];
            $row_number = 0;
            
            // Skip header row if exists
            $has_header = isset($_POST['has_header']) && $_POST['has_header'] === '1';
            if ($has_header) {
                fgetcsv($handle);
            }
            
            try {
                $conn->beginTransaction();
                
                while (($data = fgetcsv($handle)) !== false) {
                    $row_number++;
                    
                    // Expect: question, theme, level, answer, availability
                    if (count($data) < 4) {
                        $skipped++;
                        $errors[] = "Row $row_number: Not enough columns (need at least 4)";
                        continue;
                    }
                    
                    $question = trim($data[0]);
                    $theme = trim($data[1]);
                    $level = strtolower(trim($data[2]));
                    $answer = trim($data[3]);
                    $availability = isset($data[4]) ? strtolower(trim($data[4])) : 'available';
                    
                    // Validate
                    if (empty($question) || empty($theme) || empty($level) || empty($answer)) {
                        $skipped++;
                        $errors[] = "Row $row_number: Missing required fields";
                        continue;
                    }
                    
                    if (!in_array($level, ['easy', 'medium', 'hard'])) {
                        $skipped++;
                        $errors[] = "Row $row_number: Invalid level '$level' (must be easy, medium, or hard)";
                        continue;
                    }
                    
                    if (!in_array($availability, ['available', 'unavailable'])) {
                        $availability = 'available';
                    }
                    
                    // Insert question
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO questions (question, theme, level, answer, availability)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$question, $theme, $level, $answer, $availability]);
                        $imported++;
                    } catch (PDOException $e) {
                        $skipped++;
                        $errors[] = "Row $row_number: Database error - " . $e->getMessage();
                    }
                }
                
                $conn->commit();
                
                $upload_results = [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors
                ];
                
                if ($imported > 0) {
                    $success = "Successfully imported $imported question(s)!";
                }
                if ($skipped > 0) {
                    $error = "Skipped $skipped row(s) due to errors";
                }
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Import failed: " . $e->getMessage();
            }
            
            fclose($handle);
        }
    }
}

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
    
    <!-- Navigation -->
    <nav class="bg-white shadow-lg mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="text-2xl font-bold text-gray-800">Quiz Battle</a>
                    <a href="questions.php" class="text-blue-600 font-medium">Questions</a>
                    <a href="episodes.php" class="text-gray-600 hover:text-gray-800">Episodes</a>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-700 mr-4"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <p class="font-bold"><?= htmlspecialchars($success) ?></p>
                <?php if ($upload_results && !empty($upload_results['errors'])): ?>
                    <details class="mt-2">
                        <summary class="cursor-pointer text-sm">View errors (<?= count($upload_results['errors']) ?>)</summary>
                        <ul class="mt-2 text-sm list-disc list-inside">
                            <?php foreach (array_slice($upload_results['errors'], 0, 10) as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($upload_results['errors']) > 10): ?>
                                <li>... and <?= count($upload_results['errors']) - 10 ?> more</li>
                            <?php endif; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($error && !$success): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Header with Actions -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Questions</h1>
            <div class="flex gap-3">
                <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" 
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-bold">
                    📤 Import CSV
                </button>
                <a href="add_question.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-bold">
                    ➕ Add Question
                </a>
            </div>
        </div>

        <!-- Questions Table -->
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
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars(substr($q['question'], 0, 80)) ?>
                                <?= strlen($q['question']) > 80 ? '...' : '' ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($q['theme']) ?></td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-1 text-xs rounded <?= $q['level'] === 'hard' ? 'bg-red-100 text-red-800' : ($q['level'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                                    <?= ucfirst($q['level']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?= htmlspecialchars(substr($q['answer'], 0, 40)) ?>
                                <?= strlen($q['answer']) > 40 ? '...' : '' ?>
                            </td>
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

        <!-- Stats -->
        <?php if (!empty($questions)): ?>
            <div class="mt-6 bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📊 Statistics</h2>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <?php
                    $total = count($questions);
                    $easy = count(array_filter($questions, fn($q) => $q['level'] === 'easy'));
                    $medium = count(array_filter($questions, fn($q) => $q['level'] === 'medium'));
                    $hard = count(array_filter($questions, fn($q) => $q['level'] === 'hard'));
                    $available = count(array_filter($questions, fn($q) => $q['availability'] === 'available'));
                    ?>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600"><?= $total ?></div>
                        <div class="text-sm text-gray-600">Total</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600"><?= $easy ?></div>
                        <div class="text-sm text-gray-600">Easy</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-yellow-600"><?= $medium ?></div>
                        <div class="text-sm text-gray-600">Medium</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-red-600"><?= $hard ?></div>
                        <div class="text-sm text-gray-600">Hard</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-600"><?= $available ?></div>
                        <div class="text-sm text-gray-600">Available</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- CSV Upload Modal -->
    <div id="upload-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full p-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Import Questions from CSV</h2>
                <button onclick="document.getElementById('upload-modal').classList.add('hidden')" 
                        class="text-gray-500 hover:text-gray-700 text-2xl">
                    ✕
                </button>
            </div>

            <!-- Instructions -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
                <h3 class="font-bold text-blue-800 mb-2">📋 CSV Format</h3>
                <p class="text-sm text-blue-700 mb-2">Your CSV file should have these columns (in order):</p>
                <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                    <li><strong>Question</strong> - The question text</li>
                    <li><strong>Theme</strong> - Category (e.g., Geography, History)</li>
                    <li><strong>Level</strong> - Difficulty: easy, medium, or hard</li>
                    <li><strong>Answer</strong> - The correct answer</li>
                    <li><strong>Availability</strong> - Optional: available or unavailable (default: available)</li>
                </ol>
            </div>

            <!-- Example -->
            <div class="bg-gray-50 border border-gray-200 p-4 mb-6 rounded">
                <h4 class="font-bold text-gray-700 mb-2">Example CSV:</h4>
                <pre class="text-xs font-mono text-gray-700 overflow-x-auto">Question,Theme,Level,Answer,Availability
What is the capital of France?,Geography,easy,Paris,available
Who painted the Mona Lisa?,Art,medium,Leonardo da Vinci,available
What is the speed of light?,Science,hard,299792458 m/s,available</pre>
            </div>

            <!-- Upload Form -->
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Select CSV File
                    </label>
                    <input type="file" 
                           name="csv_file" 
                           accept=".csv,.txt"
                           required
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Maximum file size: 5MB</p>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" 
                           name="has_header" 
                           id="has_header" 
                           value="1"
                           checked
                           class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                    <label for="has_header" class="ml-2 text-sm text-gray-700">
                        First row is header (skip it)
                    </label>
                </div>

                <div class="flex gap-4">
                    <button type="submit" 
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                        📤 Upload & Import
                    </button>
                    <button type="button" 
                            onclick="document.getElementById('upload-modal').classList.add('hidden')"
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg">
                        Cancel
                    </button>
                </div>
            </form>

            <!-- Download Template -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <a href="#" 
                   onclick="downloadTemplate(); return false;"
                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    📥 Download CSV Template
                </a>
            </div>
        </div>
    </div>

    <script>
        function downloadTemplate() {
            const csv = `Question,Theme,Level,Answer,Availability
What is the capital of France?,Geography,easy,Paris,available
Who painted the Mona Lisa?,Art,medium,Leonardo da Vinci,available
What is the speed of light?,Science,hard,299792458 m/s,available
What year did World War II end?,History,medium,1945,available
What is the largest ocean on Earth?,Geography,easy,Pacific Ocean,available`;
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'quiz_questions_template.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
