<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

requireLogin();

$episode_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch episode
$stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
$stmt->bind_param("i", $episode_id);
$stmt->execute();
$episode = $stmt->get_result()->fetch_assoc();

if (!$episode) {
    header("Location: episodes.php");
    exit;
}

// Fetch teams
$stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY position ASC, points DESC");
$stmt->bind_param("i", $episode_id);
$stmt->execute();
$teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch questions
$questions = $conn->query("SELECT id, question, theme, level, answer FROM questions WHERE availability = 'available' ORDER BY theme, level")->fetch_all(MYSQLI_ASSOC);

// Fetch points config
$points_config = [];
$points_result = $conn->query("SELECT level, points FROM points_config");
while ($row = $points_result->fetch_assoc()) {
    $points_config[$row['level']] = $row['points'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scoring Mode - <?= htmlspecialchars($episode['episode_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../js/websocket-client.js"></script>
</head>
<body class="bg-gray-100">
    
    <!-- WebSocket Status Bar -->
    <div id="ws-status-bar" class="bg-red-500 text-white px-4 py-2 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div id="ws-indicator" class="w-2 h-2 rounded-full bg-white animate-pulse"></div>
            <span id="ws-status-text">Connecting...</span>
        </div>
        <button onclick="wsClient.requestSync()" class="text-sm hover:underline">Sync</button>
    </div>

    <div class="container mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Scoring Mode</h1>
                    <p class="text-gray-600"><?= htmlspecialchars($episode['episode_name']) ?></p>
                </div>
                <div class="flex gap-3">
                    <a href="quiz_display_ws.php?id=<?= $episode_id ?>" target="_blank" 
                       class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                        🎮 Open Live Display
                    </a>
                    <a href="view_episode.php?id=<?= $episode_id ?>" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                        ← Back
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Scoring Panel -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Quick Score by Question -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Award Points by Question</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Question:</label>
                            <select id="question-select" class="w-full p-3 border rounded" onchange="updateQuestionPreview()">
                                <option value="">-- Select a Question --</option>
                                <?php foreach ($questions as $q): ?>
                                    <option value="<?= $q['id'] ?>" 
                                            data-theme="<?= htmlspecialchars($q['theme']) ?>"
                                            data-level="<?= $q['level'] ?>"
                                            data-answer="<?= htmlspecialchars($q['answer']) ?>"
                                            data-points="<?= $points_config[$q['level']] ?? 1 ?>">
                                        [<?= strtoupper($q['level']) ?> - <?= $points_config[$q['level']] ?? 1 ?>pts] <?= htmlspecialchars($q['theme']) ?>: <?= htmlspecialchars(substr($q['question'], 0, 80)) ?>...
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Question Preview -->
                        <div id="question-preview" class="hidden bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                            <div class="flex items-center justify-between mb-2">
                                <span id="preview-theme" class="inline-block bg-blue-200 text-blue-800 px-3 py-1 rounded-full text-sm font-medium"></span>
                                <span id="preview-points" class="bg-green-500 text-white px-4 py-1 rounded-full font-bold"></span>
                            </div>
                            <p id="preview-question" class="text-gray-800 font-medium mb-2"></p>
                            <div class="mt-3 pt-3 border-t">
                                <span class="text-sm text-gray-600">Answer: </span>
                                <span id="preview-answer" class="font-bold text-gray-800"></span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Correct Team:</label>
                            <select id="team-select" class="w-full p-3 border rounded">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?= $team['id'] ?>">
                                        <?= htmlspecialchars($team['team_name']) ?> (Current: <?= $team['points'] ?> pts)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button onclick="awardPoints()" 
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-lg text-lg transition-all">
                            🏆 Award Points (Real-time)
                        </button>
                    </div>
                </div>

                <!-- Manual Points Adjustment -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Manual Points Adjustment</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Team:</label>
                            <select id="manual-team-select" class="w-full p-3 border rounded">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?= $team['id'] ?>">
                                        <?= htmlspecialchars($team['team_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Points:</label>
                            <input type="number" id="manual-points" class="w-full p-3 border rounded" placeholder="Enter points">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Action:</label>
                            <select id="manual-action" class="w-full p-3 border rounded">
                                <option value="add">Add Points</option>
                                <option value="subtract">Subtract Points</option>
                                <option value="set">Set Points</option>
                            </select>
                        </div>

                        <button onclick="manualAdjustPoints()" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg">
                            Apply Manual Adjustment
                        </button>
                    </div>
                </div>

                <!-- Broadcast Controls -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Broadcast Controls</h2>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <button onclick="broadcastQuestion()" 
                                class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg">
                            📺 Show on Display
                        </button>
                        
                        <button onclick="broadcastAnswer()" 
                                class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 rounded-lg">
                            👁️ Reveal Answer
                        </button>
                        
                        <button onclick="calculateRankings()" 
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg col-span-2">
                                🏆 Update Rankings
                        </button>
                    </div>
                </div>

            </div>

            <!-- Live Leaderboard -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-4">
                    <h2 class="text-xl font-bold mb-4">Live Leaderboard</h2>
                    
                    <div id="leaderboard" class="space-y-3">
                        <?php foreach ($teams as $team): ?>
                            <div class="leaderboard-item p-4 border-l-4 border-gray-300 bg-gray-50 rounded transition-all"
                                 data-team-id="<?= $team['id'] ?>">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <div class="position-badge w-8 h-8 bg-gray-400 text-white rounded-full flex items-center justify-center font-bold">
                                            <?= $team['position'] ?: '-' ?>
                                        </div>
                                        <span class="font-bold text-gray-800"><?= htmlspecialchars($team['team_name']) ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="team-points text-2xl font-bold text-gray-800" data-team-id="<?= $team['id'] ?>">
                                        <?= $team['points'] ?>
                                    </span>
                                    <span class="text-gray-600"> pts</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Stats -->
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-bold mb-2">Episode Stats</h3>
                        <div class="text-sm text-gray-600 space-y-1">
                            <div>Total Points: <span id="total-points" class="font-bold">
                                <?= array_sum(array_column($teams, 'points')) ?>
                            </span></div>
                            <div>Teams: <?= count($teams) ?></div>
                            <div>Status: <span class="text-green-600 font-bold"><?= ucfirst($episode['status']) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-8 right-8 bg-green-500 text-white px-6 py-4 rounded-lg shadow-xl hidden">
        <div id="toast-text" class="font-bold"></div>
    </div>

    <script>
        let wsClient;
        const episodeId = <?= $episode_id ?>;
        const pointsConfig = <?= json_encode($points_config) ?>;

        // Initialize WebSocket
        async function initWebSocket() {
            wsClient = new QuizWebSocketClient('ws://localhost:8080');
            
            wsClient.on('connected', () => {
                updateWSStatus('connected', 'Live - Connected ✓');
                wsClient.joinEpisode(episodeId, 'admin');
            });

            wsClient.on('disconnected', () => {
                updateWSStatus('disconnected', 'Disconnected - Retrying...');
            });

            wsClient.on('episode_state', (data) => {
                console.log('Episode state:', data);
                updateLeaderboard(data.teams);
            });

            wsClient.on('score_updated', (data) => {
                updateTeamDisplay(data.team_data);
                showToast(`${data.team_data.team_name} score updated!`);
            });

            wsClient.on('points_awarded', (data) => {
                updateTeamDisplay(data.team_data);
                showToast(`🎉 ${data.points} points awarded to ${data.team_data.team_name}!`);
            });

            wsClient.on('rankings_updated', (data) => {
                updateLeaderboard(data.teams);
                showToast('Rankings updated!');
            });

            await wsClient.connect();
        }

        function updateWSStatus(status, text) {
            const statusBar = document.getElementById('ws-status-bar');
            const statusText = document.getElementById('ws-status-text');
            const colors = {
                connected: 'bg-green-500',
                disconnected: 'bg-red-500',
                connecting: 'bg-yellow-500'
            };
            statusBar.className = `${colors[status]} text-white px-4 py-2 flex items-center justify-between`;
            statusText.textContent = text;
        }

        function updateQuestionPreview() {
            const select = document.getElementById('question-select');
            const option = select.options[select.selectedIndex];
            const preview = document.getElementById('question-preview');
            
            if (!option.value) {
                preview.classList.add('hidden');
                return;
            }
            
            document.getElementById('preview-theme').textContent = option.dataset.theme;
            document.getElementById('preview-points').textContent = option.dataset.points + ' POINTS';
            document.getElementById('preview-question').textContent = option.text.substring(option.text.indexOf(':') + 2);
            document.getElementById('preview-answer').textContent = option.dataset.answer;
            
            preview.classList.remove('hidden');
        }

        function awardPoints() {
            const questionId = document.getElementById('question-select').value;
            const teamId = document.getElementById('team-select').value;
            
            if (!questionId || !teamId) {
                alert('Please select both a question and a team!');
                return;
            }
            
            wsClient.awardPoints(parseInt(teamId), parseInt(questionId));
        }

        function manualAdjustPoints() {
            const teamId = document.getElementById('manual-team-select').value;
            const points = document.getElementById('manual-points').value;
            const action = document.getElementById('manual-action').value;
            
            if (!teamId || !points) {
                alert('Please fill in all fields!');
                return;
            }
            
            wsClient.updateScore(parseInt(teamId), parseInt(points), action);
        }

        function broadcastQuestion() {
            const questionId = document.getElementById('question-select').value;
            if (!questionId) {
                alert('Please select a question first!');
                return;
            }
            wsClient.showQuestion(parseInt(questionId));
            showToast('Question broadcasted to display!');
        }

        function broadcastAnswer() {
            wsClient.revealAnswer(true);
            showToast('Answer revealed on display!');
        }

        function calculateRankings() {
            wsClient.calculateRankings();
        }

        function updateTeamDisplay(teamData) {
            // Update points displays
            const pointsSpans = document.querySelectorAll(`.team-points[data-team-id="${teamData.id}"]`);
            pointsSpans.forEach(span => {
                span.textContent = teamData.points;
                span.parentElement.classList.add('animate-pulse');
                setTimeout(() => span.parentElement.classList.remove('animate-pulse'), 1000);
            });
            
            // Update total
            updateTotalPoints();
        }

        function updateLeaderboard(teams) {
            teams.sort((a, b) => b.points - a.points || a.team_name.localeCompare(b.team_name));
            
            teams.forEach((team, index) => {
                const item = document.querySelector(`.leaderboard-item[data-team-id="${team.id}"]`);
                if (!item) return;
                
                const position = index + 1;
                const badge = item.querySelector('.position-badge');
                const points = item.querySelector('.team-points');
                
                badge.textContent = position;
                points.textContent = team.points;
                
                // Update styling based on position
                const colors = ['border-yellow-400 bg-yellow-50', 'border-gray-400 bg-gray-50', 'border-orange-400 bg-orange-50'];
                item.className = `leaderboard-item p-4 border-l-4 rounded transition-all ${colors[index] || 'border-gray-300 bg-gray-50'}`;
                
                badge.className = `position-badge w-8 h-8 rounded-full flex items-center justify-center font-bold text-white ${
                    position === 1 ? 'bg-yellow-500' : position === 2 ? 'bg-gray-400' : position === 3 ? 'bg-orange-500' : 'bg-gray-400'
                }`;
            });
            
            updateTotalPoints();
        }

        function updateTotalPoints() {
            const points = Array.from(document.querySelectorAll('.team-points'))
                .map(el => parseInt(el.textContent) || 0)
                .reduce((sum, p) => sum + p, 0);
            document.getElementById('total-points').textContent = points;
        }

        function showToast(message) {
            const toast = document.getElementById('toast');
            const text = document.getElementById('toast-text');
            text.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 3000);
        }

        // Initialize
        window.addEventListener('DOMContentLoaded', initWebSocket);
        window.addEventListener('beforeunload', () => wsClient?.disconnect());
    </script>
</body>
</html>
