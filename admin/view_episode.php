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

// Get episode details
try {
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        header("Location: episodes.php");
        exit;
    }
    
    // Get teams for this episode
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY position ASC, points DESC");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll();
    
    // Get question count (if you want to show available questions)
    $stmt = $conn->query("SELECT COUNT(*) FROM questions WHERE availability = 'available'");
    $available_questions = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("View episode error: " . $e->getMessage());
    header("Location: episodes.php");
    exit;
}

// Calculate total points
$total_points = array_sum(array_column($teams, 'points'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($episode['episode_name']) ?> - Quiz Battle</title>
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
        
        <!-- Back Button -->
        <div class="mb-6">
            <a href="episodes.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
                ← Back to Episodes
            </a>
        </div>

        <!-- Episode Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <?= htmlspecialchars($episode['episode_name']) ?>
                    </h1>
                    <div class="flex items-center gap-4 text-gray-600">
                        <span>📅 <?= date('F j, Y', strtotime($episode['episode_date'])) ?></span>
                        <span>•</span>
                        <span>👥 <?= $episode['number_of_teams'] ?> teams</span>
                        <span>•</span>
                        <span class="px-3 py-1 rounded-full text-sm font-bold <?= $episode['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst($episode['status']) ?>
                        </span>
                    </div>
                    <div class="mt-3 text-sm text-gray-500">
                        <span>Episode ID: <strong class="text-gray-800">#<?= $episode['id'] ?></strong></span>
                        <span class="mx-2">•</span>
                        <span>Created: <?= date('M j, Y', strtotime($episode['created_at'])) ?></span>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <a href="edit_episode.php?id=<?= $episode['id'] ?>" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        ✏️ Edit
                    </a>
                    <a href="score_episode.php?id=<?= $episode['id'] ?>" 
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                        🎮 Score This Episode
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Column: Scoreboard -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">🏆 Team Scoreboard</h2>
                    
                    <?php if (empty($teams)): ?>
                        <p class="text-gray-500 text-center py-8">No teams registered yet.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($teams as $index => $team): ?>
                                <?php
                                $position = $index + 1;
                                $medal = $position === 1 ? '🥇' : ($position === 2 ? '🥈' : ($position === 3 ? '🥉' : ''));
                                $bgColor = $position === 1 ? 'bg-yellow-50 border-yellow-300' : 
                                           ($position === 2 ? 'bg-gray-50 border-gray-300' : 
                                           ($position === 3 ? 'bg-orange-50 border-orange-300' : 'bg-white border-gray-200'));
                                ?>
                                <div class="border-2 <?= $bgColor ?> rounded-lg p-4 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <span class="text-3xl font-bold text-gray-400 min-w-[40px]">
                                            <?= $medal ?: $position ?>
                                        </span>
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-800">
                                                <?= htmlspecialchars($team['team_name']) ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">Team ID: #<?= $team['id'] ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-3xl font-bold text-gray-800">
                                            <?= $team['points'] ?>
                                        </div>
                                        <div class="text-sm text-gray-600">points</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Statistics -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div class="text-2xl font-bold text-gray-800"><?= count($teams) ?></div>
                                    <div class="text-sm text-gray-600">Total Teams</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-gray-800"><?= $total_points ?></div>
                                    <div class="text-sm text-gray-600">Total Points</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-gray-800">
                                        <?= count($teams) > 0 ? round($total_points / count($teams), 1) : 0 ?>
                                    </div>
                                    <div class="text-sm text-gray-600">Avg Points</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Quick Info & Actions -->
            <div class="space-y-6">
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="score_episode.php?id=<?= $episode['id'] ?>" 
                           class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded text-center">
                            🎮 Start Scoring
                        </a>
                        
                        <a href="/public/host.html?episode=<?= $episode['id'] ?>" 
                           target="_blank"
                           class="block w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded text-center">
                            🎯 Open Host Panel
                        </a>
                        
                        <a href="/public/player.html" 
                           target="_blank"
                           class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded text-center">
                            📱 Player Join Page
                        </a>
                        
                        <button onclick="copyEpisodeId()" 
                                class="block w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded text-center">
                                📋 Copy Episode ID
                        </button>
                    </div>
                </div>

                <!-- Episode Info -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Episode Info</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Episode ID:</span>
                            <span class="font-bold text-gray-800" id="episode-id-display"><?= $episode['id'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span class="font-bold text-gray-800"><?= ucfirst($episode['status']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date:</span>
                            <span class="font-bold text-gray-800">
                                <?= date('M j, Y', strtotime($episode['episode_date'])) ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Teams:</span>
                            <span class="font-bold text-gray-800"><?= $episode['number_of_teams'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Questions Available:</span>
                            <span class="font-bold text-gray-800"><?= $available_questions ?></span>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <h4 class="font-bold text-blue-800 mb-2">📱 For Players</h4>
                    <p class="text-sm text-blue-700 mb-2">Share this with your teams:</p>
                    <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
                        <li>Open player page</li>
                        <li>Enter Episode ID: <strong><?= $episode['id'] ?></strong></li>
                        <li>Enter team name</li>
                        <li>Start playing!</li>
                    </ol>
                </div>

                <!-- Danger Zone -->
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <h4 class="font-bold text-red-800 mb-2">⚠️ Danger Zone</h4>
                    <button onclick="confirmDelete()" 
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-sm">
                        Delete Episode
                    </button>
                    <p class="text-xs text-red-700 mt-2">This will delete all teams and scores!</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyEpisodeId() {
            const episodeId = '<?= $episode['id'] ?>';
            navigator.clipboard.writeText(episodeId).then(() => {
                alert('Episode ID copied to clipboard: ' + episodeId);
            }).catch(err => {
                alert('Episode ID: ' + episodeId);
            });
        }

        function confirmDelete() {
            if (confirm('Are you sure you want to delete this episode?\n\nThis will permanently delete:\n- The episode\n- All teams\n- All scores\n\nThis action cannot be undone!')) {
                if (confirm('Really delete? This is your last chance!')) {
                    window.location.href = 'delete_episode.php?id=<?= $episode['id'] ?>';
                }
            }
        }
    </script>
</body>
</html>
