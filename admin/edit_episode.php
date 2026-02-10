<?php
require_once '../includes/bootstrap.php';
session_start();
require_once '../includes/config-render.php';
require_once '../includes/auth.php';

requireLogin();

$episode_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$episode_id) {
    header("Location: episodes.php");
    exit;
}

$success = '';
$error = '';

// Get episode and teams
try {
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        header("Location: episodes.php");
        exit;
    }
    
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY position ASC");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Edit episode error: " . $e->getMessage());
    header("Location: episodes.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $episode_name = $_POST['episode_name'] ?? '';
    $episode_date = $_POST['episode_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $team_updates = $_POST['teams'] ?? [];
    
    if (empty($episode_name) || empty($episode_date)) {
        $error = "Episode name and date are required";
    } else {
        try {
            $conn->beginTransaction();
            
            // Update episode
            $stmt = $conn->prepare("
                UPDATE quiz_episodes 
                SET episode_name = ?, episode_date = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$episode_name, $episode_date, $status, $episode_id]);
            
            // Update teams
            foreach ($team_updates as $team_id => $team_data) {
                $team_name = trim($team_data['name']);
                $team_points = (int)$team_data['points'];
                
                if (!empty($team_name)) {
                    $stmt = $conn->prepare("UPDATE teams SET team_name = ?, points = ? WHERE id = ? AND episode_id = ?");
                    $stmt->execute([$team_name, $team_points, $team_id, $episode_id]);
                }
            }
            
            // Add new teams if provided
            if (isset($_POST['new_teams'])) {
                $max_position = count($teams);
                foreach ($_POST['new_teams'] as $new_team_name) {
                    $new_team_name = trim($new_team_name);
                    if (!empty($new_team_name)) {
                        $max_position++;
                        $stmt = $conn->prepare("INSERT INTO teams (episode_id, team_name, points, position) VALUES (?, ?, 0, ?)");
                        $stmt->execute([$episode_id, $new_team_name, $max_position]);
                    }
                }
                
                // Update number of teams
                $stmt = $conn->prepare("UPDATE quiz_episodes SET number_of_teams = (SELECT COUNT(*) FROM teams WHERE episode_id = ?) WHERE id = ?");
                $stmt->execute([$episode_id, $episode_id]);
            }
            
            $conn->commit();
            
            $success = "Episode updated successfully!";
            
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
            $stmt->execute([$episode_id]);
            $episode = $stmt->fetch();
            
            $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY position ASC");
            $stmt->execute([$episode_id]);
            $teams = $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error updating episode: " . $e->getMessage();
            error_log("Update episode error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Episode - <?= htmlspecialchars($episode['episode_name']) ?></title>
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
                    <a href="episodes.php" class="text-blue-600 font-medium">Episodes</a>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-700 mr-4"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4">
        
        <!-- Back Button -->
        <div class="mb-6">
            <a href="view_episode.php?id=<?= $episode_id ?>" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
                ← Back to Episode
            </a>
        </div>

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Edit Episode</h1>
            <p class="text-gray-600 mt-2">Update episode details and manage teams</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <p class="font-bold"><?= htmlspecialchars($success) ?></p>
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
            <form method="POST" class="space-y-6">
                
                <!-- Episode Details -->
                <div class="border-b border-gray-200 pb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Episode Details</h2>
                    
                    <div class="space-y-4">
                        <!-- Episode Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Episode Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="episode_name" 
                                   required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="<?= htmlspecialchars($episode['episode_name']) ?>">
                        </div>

                        <!-- Episode Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Episode Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   name="episode_date" 
                                   required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="<?= $episode['episode_date'] ?>">
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select name="status" 
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="active" <?= $episode['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="completed" <?= $episode['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="archived" <?= $episode['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>

                        <!-- Episode Info -->
                        <div class="bg-gray-50 p-4 rounded">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Episode ID:</span>
                                    <span class="font-bold text-gray-800 ml-2">#<?= $episode['id'] ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Number of Teams:</span>
                                    <span class="font-bold text-gray-800 ml-2"><?= $episode['number_of_teams'] ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Created:</span>
                                    <span class="font-bold text-gray-800 ml-2"><?= date('M j, Y', strtotime($episode['created_at'])) ?></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Last Updated:</span>
                                    <span class="font-bold text-gray-800 ml-2"><?= date('M j, Y', strtotime($episode['updated_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Existing Teams -->
                <div class="border-b border-gray-200 pb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Existing Teams</h2>
                    
                    <div class="space-y-3">
                        <?php foreach ($teams as $index => $team): ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Team <?= $index + 1 ?> Name
                                        </label>
                                        <input type="text" 
                                               name="teams[<?= $team['id'] ?>][name]" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                               value="<?= htmlspecialchars($team['team_name']) ?>">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Current Points
                                        </label>
                                        <input type="number" 
                                               name="teams[<?= $team['id'] ?>][points]" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                               value="<?= $team['points'] ?>">
                                    </div>
                                </div>
                                <div class="mt-2 text-xs text-gray-500">
                                    Team ID: #<?= $team['id'] ?> • Position: <?= $team['position'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Add New Teams -->
                <div>
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Teams (Optional)</h2>
                    
                    <div id="new-teams-container" class="space-y-3 mb-4">
                        <!-- New team inputs will be added here -->
                    </div>
                    
                    <button type="button" 
                            onclick="addNewTeamField()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                        ➕ Add Another Team
                    </button>
                </div>

                <!-- Submit Buttons -->
                <div class="flex gap-4 pt-6">
                    <button type="submit" 
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                        💾 Save Changes
                    </button>
                    <a href="view_episode.php?id=<?= $episode_id ?>" 
                       class="flex-1 text-center bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Warning -->
        <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded">
            <h3 class="font-bold text-yellow-800 mb-2">⚠️ Important Notes</h3>
            <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                <li>Changing team names will update them everywhere</li>
                <li>You can manually adjust points here (useful for corrections)</li>
                <li>Adding new teams will update the team count automatically</li>
                <li>Deleting teams must be done from the database directly</li>
                <li>Changing status to "Completed" or "Archived" affects visibility</li>
            </ul>
        </div>
    </div>

    <script>
        let newTeamCount = 0;

        function addNewTeamField() {
            const container = document.getElementById('new-teams-container');
            const div = document.createElement('div');
            div.className = 'flex items-center gap-3';
            div.innerHTML = `
                <span class="text-gray-600 font-bold min-w-[100px]">New Team ${++newTeamCount}:</span>
                <input type="text" 
                       name="new_teams[]" 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter team name">
                <button type="button" 
                        onclick="this.parentElement.remove(); newTeamCount--;"
                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">
                    ✕
                </button>
            `;
            container.appendChild(div);
        }
    </script>
</body>
</html>
