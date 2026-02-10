<?php
require_once '../includes/bootstrap.php';
session_start();
require_once '../includes/config-render.php';
require_once '../includes/auth.php';

requireLogin();

// Show delete success message if set
$delete_success = '';
if (isset($_SESSION['delete_success'])) {
    $delete_success = $_SESSION['delete_success'];
    unset($_SESSION['delete_success']);
}

// Fetch all episodes
$stmt = $conn->query("SELECT * FROM quiz_episodes ORDER BY created_at DESC");
$episodes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Episodes - Quiz Battle</title>
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Delete Success Message -->
        <?php if ($delete_success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <p class="font-bold"><?= htmlspecialchars($delete_success) ?></p>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Quiz Episodes</h1>
            <a href="add_episode.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-bold">
                🎮 Create Episode
            </a>
        </div>

        <!-- Episodes Grid -->
        <?php if (empty($episodes)): ?>
            <div class="bg-white rounded-lg shadow-lg p-12 text-center">
                <div class="text-6xl mb-4">🎮</div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">No Episodes Yet</h2>
                <p class="text-gray-600 mb-6">Create your first quiz episode to get started!</p>
                <a href="add_episode.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold">
                    Create First Episode
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($episodes as $episode): ?>
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                        <!-- Episode Header -->
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                            <h3 class="text-xl font-bold text-white mb-1">
                                <?= htmlspecialchars($episode['episode_name']) ?>
                            </h3>
                            <p class="text-blue-100 text-sm">
                                Episode #<?= $episode['id'] ?>
                            </p>
                        </div>

                        <!-- Episode Details -->
                        <div class="p-4">
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">📅 Date:</span>
                                    <span class="font-medium text-gray-800">
                                        <?= date('M j, Y', strtotime($episode['episode_date'])) ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">👥 Teams:</span>
                                    <span class="font-medium text-gray-800">
                                        <?= $episode['number_of_teams'] ?> teams
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">Status:</span>
                                    <span class="px-3 py-1 text-xs rounded-full font-bold <?= $episode['status'] === 'active' ? 'bg-green-100 text-green-800' : ($episode['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                        <?= ucfirst($episode['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <a href="view_episode.php?id=<?= $episode['id'] ?>" 
                               class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded transition-colors">
                                View Details →
                            </a>
                        </div>

                        <!-- Footer -->
                        <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">
                                    Created <?= date('M j', strtotime($episode['created_at'])) ?>
                                </span>
                                <div class="flex gap-2">
                                    <a href="score_episode.php?id=<?= $episode['id'] ?>" 
                                       class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded">
                                        Score
                                    </a>
                                    <a href="edit_episode.php?id=<?= $episode['id'] ?>" 
                                       class="text-xs bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded">
                                        Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <?php if (!empty($episodes)): ?>
            <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">📊 Statistics</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php
                    $total_episodes = count($episodes);
                    $active_episodes = count(array_filter($episodes, fn($e) => $e['status'] === 'active'));
                    $completed_episodes = count(array_filter($episodes, fn($e) => $e['status'] === 'completed'));
                    $total_teams = array_sum(array_column($episodes, 'number_of_teams'));
                    ?>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600"><?= $total_episodes ?></div>
                        <div class="text-sm text-gray-600">Total Episodes</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600"><?= $active_episodes ?></div>
                        <div class="text-sm text-gray-600">Active</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-600"><?= $completed_episodes ?></div>
                        <div class="text-sm text-gray-600">Completed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-orange-600"><?= $total_teams ?></div>
                        <div class="text-sm text-gray-600">Total Teams</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
