<?php
require_once '../includes/bootstrap.php';
session_start();
require_once '../includes/config-render.php';
require_once '../includes/auth.php';

requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $episode_name = $_POST['episode_name'] ?? '';
    $episode_date = $_POST['episode_date'] ?? '';
    $quiz_format = $_POST['quiz_format'] ?? 'cutthroat';
  //  $number_of_teams = (int)($_POST['number_of_teams'] ?? 0);
  //  $team_names = $_POST['team_names'] ?? [];
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($episode_name)) {
        $error = "Episode name is required";
    } elseif (empty($episode_date)) {
        $error = "Episode date is required";
    } elseif (!in_array($quiz_format, ['cutthroat', 'multiple_choice'])) {
        $error = "Invalid quiz format";
 //   } elseif ($number_of_teams < 2) {
 //       $error = "At least 2 teams are required";
 //   } elseif (count(array_filter($team_names)) < $number_of_teams) {
 //       $error = "Please provide names for all {$number_of_teams} teams";
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Insert episode
            $stmt = $conn->prepare("
                INSERT INTO quiz_episodes (episode_name, episode_date, quiz_format, status) 
                VALUES (?, ?, ?, ?, ?) 
                RETURNING id
            ");
            $stmt->execute([$episode_name, $episode_date, $quiz_format, $status]);
            $episode = $stmt->fetch();
            $episode_id = $episode['id'];
            
            // Insert teams
            $stmt = $conn->prepare("
                INSERT INTO teams (episode_id, team_name, points, position) 
                VALUES (?, ?, 0, ?)
            ");
            
            $position = 1;
            foreach ($team_names as $team_name) {
                $team_name = trim($team_name);
                if (!empty($team_name)) {
                    $stmt->execute([$episode_id, $team_name, $position]);
                    $position++;
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $format_label = $quiz_format === 'cutthroat' ? 'CutThroat' : 'Multiple Choice';
            $success = "Episode '{$episode_name}' created successfully as {$format_label} format with {$number_of_teams} teams!";
            
            // Clear form
            $_POST = [];
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error creating episode: " . $e->getMessage();
            error_log("Add episode error: " . $e->getMessage());
        }
    }
}

// Set default date to today
$default_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Episode - Quiz Battle</title>
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
        
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Create New Episode</h1>
            <p class="text-gray-600 mt-2">Set up a new quiz episode with teams</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <div class="flex items-center justify-between">
                    <p class="font-bold"><?= htmlspecialchars($success) ?></p>
                    <a href="episodes.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                        View All Episodes
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <form method="POST" id="episode-form" class="space-y-6">
                
                <!-- Episode Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Episode Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           name="episode_name" 
                           required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="e.g., Season 1 Episode 5, Friday Night Quiz, Team Tournament"
                           value="<?= htmlspecialchars($_POST['episode_name'] ?? '') ?>">
                </div>

                <!-- Episode Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Episode Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" 
                           name="episode_date" 
                           required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?= htmlspecialchars($_POST['episode_date'] ?? $default_date) ?>">
                </div>

                <!-- Quiz Format Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        Quiz Format <span class="text-red-500">*</span>
                    </label>
                    <p class="text-sm text-gray-600 mb-4">Choose how players will answer questions</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- CutThroat -->
                        <label class="relative cursor-pointer">
                            <input type="radio" name="quiz_format" value="cutthroat" 
                                   <?= ($_POST['quiz_format'] ?? 'cutthroat') === 'cutthroat' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="border-2 border-gray-300 rounded-lg p-6 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all hover:border-blue-300">
                                <div class="text-center">
                                    <div class="text-5xl mb-3">🎯</div>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">CutThroat</h3>
                                    <div class="space-y-2 text-sm text-gray-600">
                                        <p>• Players buzz in with text answers</p>
                                        <p>• First correct answer wins</p>
                                        <p>• Fast-paced and competitive</p>
                                        <p>• Perfect for trivia experts</p>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <!-- Multiple Choice -->
                        <label class="relative cursor-pointer">
                            <input type="radio" name="quiz_format" value="multiple_choice"
                                   <?= ($_POST['quiz_format'] ?? '') === 'multiple_choice' ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="border-2 border-gray-300 rounded-lg p-6 peer-checked:border-blue-500 peer-checked:bg-blue-50 transition-all hover:border-blue-300">
                                <div class="text-center">
                                    <div class="text-5xl mb-3">✓</div>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">Multiple Choice</h3>
                                    <div class="space-y-2 text-sm text-gray-600">
                                        <p>• Players select A, B, C, or D</p>
                                        <p>• Everyone can answer</p>
                                        <p>• All correct answers get points</p>
                                        <p>• Family-friendly and inclusive</p>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="status" 
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= ($_POST['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="archived" <?= ($_POST['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                  <!-- Submit Buttons -->
                <div class="flex gap-4 pt-4">
                    <button type="submit" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-all">
                        🎮 Create Episode
                    </button>
                    <a href="episodes.php" 
                       class="flex-1 text-center bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg transition-all">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Quick fill options
        const quickFillOptions = {
            numbered: (index) => `Team ${index + 1}`,
            colors: ['Red Team', 'Blue Team', 'Green Team', 'Yellow Team', 'Orange Team', 'Purple Team', 'Pink Team', 'Black Team', 'White Team', 'Gray Team'],
            animals: ['Lions', 'Tigers', 'Bears', 'Eagles', 'Sharks', 'Wolves', 'Panthers', 'Falcons', 'Dragons', 'Phoenix', 'Cobras', 'Vipers']
        };

        // Generate team input fields
        function generateTeamFields() {
            const numTeams = parseInt(document.getElementById('number_of_teams').value) || 0;
            const container = document.getElementById('team-inputs');
            
            if (numTeams < 2 || numTeams > 20) {
                container.innerHTML = '<p class="text-red-600 text-sm">Please enter a number between 2 and 20</p>';
                return;
            }
            
            container.innerHTML = '';
            
            for (let i = 0; i < numTeams; i++) {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-3';
                div.innerHTML = `
                    <span class="text-gray-600 font-bold min-w-[60px]">Team ${i + 1}:</span>
                    <input type="text" 
                           name="team_names[]" 
                           required
                           class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter team name">
                `;
                container.appendChild(div);
            }
        }

        // Quick fill team names
        function quickFillTeams(type) {
            const inputs = document.querySelectorAll('input[name="team_names[]"]');
            
            inputs.forEach((input, index) => {
                if (type === 'numbered') {
                    input.value = quickFillOptions.numbered(index);
                } else if (quickFillOptions[type] && quickFillOptions[type][index]) {
                    input.value = quickFillOptions[type][index];
                }
            });
        }

        // Clear all team names
        function clearTeamNames() {
            const inputs = document.querySelectorAll('input[name="team_names[]"]');
            inputs.forEach(input => input.value = '');
            inputs[0]?.focus();
        }

        // Generate initial team fields
        document.addEventListener('DOMContentLoaded', function() {
            generateTeamFields();
        });
    </script>
</body>
</html>
