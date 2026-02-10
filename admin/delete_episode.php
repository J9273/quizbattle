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

// Get episode details before deletion
try {
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        header("Location: episodes.php");
        exit;
    }
    
    // Get team count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM teams WHERE episode_id = ?");
    $stmt->execute([$episode_id]);
    $team_count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Delete episode error: " . $e->getMessage());
    header("Location: episodes.php");
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $confirm_text = $_POST['confirm_text'] ?? '';
    
    if ($confirm_text === 'DELETE') {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Delete teams first (foreign key constraint)
            $stmt = $conn->prepare("DELETE FROM teams WHERE episode_id = ?");
            $stmt->execute([$episode_id]);
            $deleted_teams = $stmt->rowCount();
            
            // Delete episode
            $stmt = $conn->prepare("DELETE FROM quiz_episodes WHERE id = ?");
            $stmt->execute([$episode_id]);
            
            // Commit transaction
            $conn->commit();
            
            // Redirect with success message
            $_SESSION['delete_success'] = "Episode '{$episode['episode_name']}' deleted successfully! ({$deleted_teams} teams removed)";
            header("Location: episodes.php");
            exit;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error deleting episode: " . $e->getMessage();
            error_log("Delete episode error: " . $e->getMessage());
        }
    } else {
        $error = "You must type 'DELETE' exactly to confirm deletion";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Episode - <?= htmlspecialchars($episode['episode_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    
    <!-- Navigation -->
    <nav class="bg-white shadow-lg mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="text-2xl font-bold text-gray-800">Quiz Battle</a>
                    <a href="episodes.php" class="text-blue-600 font-medium">Episodes</a>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-700 mr-4"><?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-2xl mx-auto px-4">
        
        <!-- Back Button -->
        <div class="mb-6">
            <a href="view_episode.php?id=<?= $episode_id ?>" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
                ← Back to Episode
            </a>
        </div>

        <!-- Warning Card -->
        <div class="bg-white rounded-lg shadow-2xl p-8 border-4 border-red-500">
            
            <!-- Danger Icon -->
            <div class="text-center mb-6">
                <div class="text-6xl mb-4">⚠️</div>
                <h1 class="text-3xl font-bold text-red-600">Delete Episode</h1>
                <p class="text-gray-600 mt-2">This action cannot be undone!</p>
            </div>

            <!-- Episode Info -->
            <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded mb-6">
                <h2 class="font-bold text-red-800 mb-3">You are about to delete:</h2>
                <div class="space-y-2 text-red-700">
                    <div class="flex justify-between">
                        <span class="font-medium">Episode Name:</span>
                        <span class="font-bold"><?= htmlspecialchars($episode['episode_name']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Episode ID:</span>
                        <span class="font-bold">#<?= $episode['id'] ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Episode Date:</span>
                        <span class="font-bold"><?= date('M j, Y', strtotime($episode['episode_date'])) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Number of Teams:</span>
                        <span class="font-bold"><?= $team_count ?> teams</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Status:</span>
                        <span class="font-bold"><?= ucfirst($episode['status']) ?></span>
                    </div>
                </div>
            </div>

            <!-- What Will Be Deleted -->
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded mb-6">
                <h3 class="font-bold text-yellow-800 mb-3">⚡ What will be permanently deleted:</h3>
                <ul class="text-yellow-700 space-y-2">
                    <li class="flex items-start gap-2">
                        <span class="font-bold">✕</span>
                        <span>The episode record</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="font-bold">✕</span>
                        <span><strong><?= $team_count ?> teams</strong> and all their data</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="font-bold">✕</span>
                        <span>All points and scores</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="font-bold">✕</span>
                        <span>All team rankings and positions</span>
                    </li>
                </ul>
                <p class="mt-4 text-sm text-yellow-800 font-bold">
                    💾 Questions are NOT deleted - they can be reused in other episodes
                </p>
            </div>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <p class="font-bold"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- Confirmation Form -->
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Type <span class="text-red-600 font-mono bg-red-100 px-2 py-1 rounded">DELETE</span> to confirm:
                    </label>
                    <input type="text" 
                           name="confirm_text" 
                           required
                           autocomplete="off"
                           class="w-full px-4 py-3 border-2 border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 font-mono text-lg"
                           placeholder="Type DELETE here">
                    <p class="text-xs text-gray-600 mt-2">Must match exactly (case-sensitive)</p>
                </div>

                <input type="hidden" name="confirm_delete" value="1">

                <!-- Buttons -->
                <div class="flex gap-4">
                    <button type="submit" 
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-lg transition-all">
                        🗑️ Yes, Delete Forever
                    </button>
                    <a href="view_episode.php?id=<?= $episode_id ?>" 
                       class="flex-1 text-center bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-6 rounded-lg transition-all">
                        ← Cancel, Keep Episode
                    </a>
                </div>
            </form>

            <!-- Final Warning -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-center text-sm text-gray-600">
                    ⚠️ This action is <strong class="text-red-600">PERMANENT</strong> and <strong class="text-red-600">CANNOT BE UNDONE</strong>
                </p>
            </div>
        </div>

        <!-- Alternative Options -->
        <div class="mt-8 bg-blue-50 border-l-4 border-blue-500 p-6 rounded">
            <h3 class="font-bold text-blue-800 mb-3">💡 Consider these alternatives:</h3>
            <ul class="text-blue-700 space-y-2 text-sm">
                <li>• <strong>Archive</strong> the episode instead of deleting it</li>
                <li>• Change status to "Completed" to hide from active list</li>
                <li>• Reset team scores to 0 instead of deleting</li>
                <li>• Export the data before deletion (if needed for records)</li>
            </ul>
            <div class="mt-4">
                <a href="edit_episode.php?id=<?= $episode_id ?>" 
                   class="text-blue-600 hover:text-blue-800 font-bold">
                    → Edit episode instead
                </a>
            </div>
        </div>
    </div>
</body>
</html>
