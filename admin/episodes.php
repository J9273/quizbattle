<?php
require_once '../includes/config-render.php';
require_once '../includes/auth.php';
requireLogin();

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
    <nav class="bg-white shadow-lg mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="text-2xl font-bold text-gray-800">Quiz Battle</a>
                    <a href="questions.php" class="text-gray-600 hover:text-gray-800">Questions</a>
                    <a href="episodes.php" class="text-blue-600 font-medium">Episodes</a>
                </div>
                <div class="flex items-center">
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Quiz Episodes</h1>
            <a href="add_episode.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                🎮 Create Episode
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($episodes as $episode): ?>
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($episode['episode_name']) ?></h3>
                    <p class="text-gray-600 mb-4">📅 <?= date('M d, Y', strtotime($episode['episode_date'])) ?></p>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-gray-600">👥 <?= $episode['number_of_teams'] ?> teams</span>
                        <span class="px-3 py-1 text-xs rounded-full <?= $episode['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst($episode['status']) ?>
                        </span>
                    </div>
                    <a href="view_episode.php?id=<?= $episode['id'] ?>" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded">
                        View Details
                    </a>
                </div>
            <?php endforeach; ?>

            <?php if (empty($episodes)): ?>
                <div class="col-span-3 text-center py-12">
                    <p class="text-gray-500 mb-4">No episodes created yet.</p>
                    <a href="add_episode.php" class="text-blue-600 hover:underline">Create your first episode!</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
