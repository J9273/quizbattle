<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

requireLogin();

$episode_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch episode details
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

// Fetch available questions
$questions_result = $conn->query("SELECT id, question, theme, level, answer FROM questions WHERE availability = 'available' ORDER BY theme, level");
$questions = $questions_result->fetch_all(MYSQLI_ASSOC);

// Fetch points configuration
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
    <title>Live Quiz Display - <?= htmlspecialchars($episode['episode_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../js/websocket-client.js"></script>
    <style>
        @keyframes slideIn {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .slide-in { animation: slideIn 0.5s ease-out; }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        .bounce { animation: bounce 1s ease-in-out infinite; }
        .pulse { animation: pulse 1s ease-in-out infinite; }
        
        .answer-hidden { filter: blur(10px); user-select: none; }
        .answer-revealed { filter: blur(0); transition: filter 0.5s ease; }
        
        .ws-connected { background-color: #10b981; }
        .ws-disconnected { background-color: #ef4444; }
        .ws-connecting { background-color: #f59e0b; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-900 via-purple-900 to-pink-900 min-h-screen text-white">
    
    <!-- WebSocket Status Indicator -->
    <div id="ws-status-bar" class="fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-6 py-2 ws-disconnected">
        <div class="flex items-center gap-3">
            <div id="ws-indicator" class="w-3 h-3 rounded-full bg-white animate-pulse"></div>
            <span id="ws-status-text" class="font-semibold">Connecting to live server...</span>
        </div>
        <div class="flex items-center gap-3">
            <span id="ws-clients" class="text-sm opacity-75">0 viewers</span>
            <button onclick="wsClient.requestSync()" class="text-sm opacity-75 hover:opacity-100">
                🔄 Sync
            </button>
        </div>
    </div>

    <!-- Control Panel (Hidden by default, toggle with 'C' key) -->
    <div id="control-panel" class="fixed bottom-0 left-0 right-0 bg-black bg-opacity-90 p-4 z-40 hidden">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center gap-4 mb-4">
                <h3 class="text-lg font-bold">Quiz Control Panel</h3>
                <button onclick="toggleControlPanel()" class="ml-auto text-sm opacity-75 hover:opacity-100">
                    Close (C)
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Question Selector -->
                <div>
                    <label class="block text-sm mb-2">Select Question:</label>
                    <select id="question-selector" class="w-full p-2 bg-gray-800 rounded" onchange="showQuestionFromControl()">
                        <option value="">-- Select Question --</option>
                        <?php foreach ($questions as $q): ?>
                            <option value="<?= $q['id'] ?>" 
                                    data-theme="<?= htmlspecialchars($q['theme']) ?>"
                                    data-level="<?= $q['level'] ?>"
                                    data-answer="<?= htmlspecialchars($q['answer']) ?>"
                                    data-points="<?= $points_config[$q['level']] ?? 1 ?>">
                                [<?= strtoupper($q['level']) ?>] <?= htmlspecialchars(substr($q['question'], 0, 60)) ?>...
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Answer Control -->
                <div>
                    <label class="block text-sm mb-2">Answer Control:</label>
                    <button onclick="toggleAnswer()" class="w-full p-2 bg-blue-600 hover:bg-blue-700 rounded">
                        Toggle Answer (Space)
                    </button>
                </div>
                
                <!-- Clear Display -->
                <div>
                    <label class="block text-sm mb-2">Display Control:</label>
                    <button onclick="clearDisplay()" class="w-full p-2 bg-red-600 hover:bg-red-700 rounded">
                        Clear Display (Esc)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Display Area -->
    <div class="pt-16 pb-8 px-8 min-h-screen flex flex-col">
        
        <!-- Question Display -->
        <div id="question-container" class="flex-1 flex items-center justify-center mb-8 hidden">
            <div class="max-w-5xl w-full">
                <!-- Theme Badge -->
                <div id="theme-badge" class="slide-in inline-block bg-yellow-500 text-black px-6 py-2 rounded-full text-xl font-bold mb-6">
                    THEME
                </div>
                
                <!-- Question Text -->
                <div id="question-text" class="slide-in text-5xl md:text-6xl font-bold mb-8 leading-tight">
                    Question will appear here...
                </div>
                
                <!-- Points Badge -->
                <div id="points-badge" class="bounce inline-block bg-green-500 px-8 py-4 rounded-full text-4xl font-bold mb-8">
                    🏆 <span id="question-points">0</span> POINTS
                </div>
                
                <!-- Answer (Initially Hidden) -->
                <div id="answer-container" class="hidden">
                    <div class="text-3xl font-bold text-yellow-300 mb-4">ANSWER:</div>
                    <div id="answer-text" class="text-4xl font-bold answer-hidden bg-gray-800 bg-opacity-50 p-6 rounded-lg">
                        Hidden Answer
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Buttons for Quick Scoring -->
        <div id="team-buttons" class="hidden mb-8">
            <div class="text-2xl font-bold mb-4 text-center">Award Points to:</div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 max-w-5xl mx-auto">
                <?php foreach ($teams as $index => $team): ?>
                    <button onclick="awardPointsToTeam(<?= $team['id'] ?>)" 
                            class="team-btn bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 p-6 rounded-lg text-center transition-all transform hover:scale-105"
                            data-team-id="<?= $team['id'] ?>"
                            data-hotkey="<?= ($index + 1) ?>">
                        <div class="text-3xl font-bold mb-2"><?= htmlspecialchars($team['team_name']) ?></div>
                        <div class="text-xl opacity-75"><span class="team-points" data-team-id="<?= $team['id'] ?>"><?= $team['points'] ?></span> pts</div>
                        <div class="text-sm opacity-50 mt-2">Press <?= ($index + 1) ?></div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="bg-black bg-opacity-40 rounded-2xl p-6 max-w-5xl mx-auto w-full">
            <h2 class="text-3xl font-bold mb-6 text-center">🏆 LEADERBOARD</h2>
            <div id="leaderboard" class="space-y-3">
                <?php foreach ($teams as $team): ?>
                    <div class="leaderboard-item flex items-center justify-between bg-gradient-to-r from-gray-800 to-gray-900 p-4 rounded-lg"
                         data-team-id="<?= $team['id'] ?>">
                        <div class="flex items-center gap-4">
                            <div class="position-badge text-2xl font-bold w-12 h-12 rounded-full bg-yellow-500 text-black flex items-center justify-center">
                                <?= $team['position'] ?: '-' ?>
                            </div>
                            <div class="text-2xl font-bold"><?= htmlspecialchars($team['team_name']) ?></div>
                        </div>
                        <div class="text-3xl font-bold team-points-leaderboard" data-team-id="<?= $team['id'] ?>">
                            <?= $team['points'] ?> pts
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Notification Toast -->
    <div id="notification-toast" class="fixed top-20 right-8 bg-green-500 text-white px-6 py-4 rounded-lg shadow-xl hidden z-50">
        <div id="notification-text" class="font-bold"></div>
    </div>

    <script>
        // WebSocket Client
        let wsClient;
        let currentQuestion = null;
        let answerRevealed = false;
        const episodeId = <?= $episode_id ?>;

        // Initialize WebSocket connection
        async function initWebSocket() {
            wsClient = new QuizWebSocketClient('ws://localhost:8080');
            
            // Connection events
            wsClient.on('connected', () => {
                updateWSStatus('connected', 'Live - Connected');
                wsClient.joinEpisode(episodeId, 'display');
            });

            wsClient.on('disconnected', () => {
                updateWSStatus('disconnected', 'Disconnected');
            });

            wsClient.on('error', () => {
                updateWSStatus('error', 'Connection Error');
            });

            // Episode events
            wsClient.on('episode_state', (data) => {
                console.log('Episode state received:', data);
                updateLeaderboard(data.teams);
            });

            wsClient.on('score_updated', (data) => {
                updateTeamScore(data.team_data);
                showNotification(`${data.team_data.team_name} scored ${data.points_changed} points!`);
            });

            wsClient.on('points_awarded', (data) => {
                updateTeamScore(data.team_data);
                showNotification(`🎉 ${data.team_data.team_name} awarded ${data.points} points!`);
                
                // Flash the team button
                const teamBtn = document.querySelector(`.team-btn[data-team-id="${data.team_id}"]`);
                if (teamBtn) {
                    teamBtn.classList.add('pulse');
                    setTimeout(() => teamBtn.classList.remove('pulse'), 2000);
                }
            });

            wsClient.on('rankings_updated', (data) => {
                updateLeaderboard(data.teams);
                showNotification('Rankings updated!');
            });

            wsClient.on('question_displayed', (data) => {
                displayQuestion(data.question);
            });

            wsClient.on('answer_revealed', (data) => {
                if (data.revealed) {
                    revealAnswer();
                } else {
                    hideAnswer();
                }
            });

            wsClient.on('client_joined', (data) => {
                console.log('Client joined:', data);
            });

            // Connect
            try {
                await wsClient.connect();
            } catch (error) {
                console.error('Failed to connect:', error);
                updateWSStatus('error', 'Failed to connect');
            }
        }

        // Update WebSocket status indicator
        function updateWSStatus(status, text) {
            const statusBar = document.getElementById('ws-status-bar');
            const statusText = document.getElementById('ws-status-text');
            const indicator = document.getElementById('ws-indicator');
            
            statusBar.className = `fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-6 py-2 ws-${status}`;
            statusText.textContent = text;
            
            if (status === 'connected') {
                indicator.classList.remove('animate-pulse');
            } else {
                indicator.classList.add('animate-pulse');
            }
        }

        // Display question
        function displayQuestion(question) {
            currentQuestion = question;
            answerRevealed = false;
            
            const container = document.getElementById('question-container');
            const themeBadge = document.getElementById('theme-badge');
            const questionText = document.getElementById('question-text');
            const pointsBadge = document.getElementById('points-badge');
            const questionPoints = document.getElementById('question-points');
            const answerContainer = document.getElementById('answer-container');
            const answerText = document.getElementById('answer-text');
            const teamButtons = document.getElementById('team-buttons');
            
            // Update content
            themeBadge.textContent = question.theme.toUpperCase();
            questionText.textContent = question.question;
            questionPoints.textContent = question.points;
            answerText.textContent = question.answer;
            
            // Show question, hide answer
            container.classList.remove('hidden');
            answerContainer.classList.add('hidden');
            answerText.classList.add('answer-hidden');
            answerText.classList.remove('answer-revealed');
            teamButtons.classList.remove('hidden');
            
            // Re-trigger animations
            themeBadge.classList.remove('slide-in');
            questionText.classList.remove('slide-in');
            void themeBadge.offsetWidth; // Trigger reflow
            themeBadge.classList.add('slide-in');
            questionText.classList.add('slide-in');
        }

        // Show question from control panel
        function showQuestionFromControl() {
            const selector = document.getElementById('question-selector');
            const option = selector.options[selector.selectedIndex];
            
            if (!option.value) return;
            
            const question = {
                id: parseInt(option.value),
                theme: option.dataset.theme,
                level: option.dataset.level,
                question: option.text.substring(option.text.indexOf(']') + 2),
                answer: option.dataset.answer,
                points: parseInt(option.dataset.points)
            };
            
            // Broadcast to all connected clients
            wsClient.showQuestion(question.id);
            
            // Display locally
            displayQuestion(question);
        }

        // Toggle answer visibility
        function toggleAnswer() {
            answerRevealed = !answerRevealed;
            
            if (answerRevealed) {
                revealAnswer();
            } else {
                hideAnswer();
            }
            
            // Broadcast to all clients
            wsClient.revealAnswer(answerRevealed);
        }

        function revealAnswer() {
            const answerContainer = document.getElementById('answer-container');
            const answerText = document.getElementById('answer-text');
            
            answerContainer.classList.remove('hidden');
            answerText.classList.remove('answer-hidden');
            answerText.classList.add('answer-revealed');
            answerRevealed = true;
        }

        function hideAnswer() {
            const answerText = document.getElementById('answer-text');
            answerText.classList.add('answer-hidden');
            answerText.classList.remove('answer-revealed');
            answerRevealed = false;
        }

        // Award points to team
        function awardPointsToTeam(teamId) {
            if (!currentQuestion) {
                showNotification('No question selected!', 'error');
                return;
            }
            
            wsClient.awardPoints(teamId, currentQuestion.id);
        }

        // Update team score in real-time
        function updateTeamScore(teamData) {
            // Update in team buttons
            const teamPointsSpans = document.querySelectorAll(`.team-points[data-team-id="${teamData.id}"]`);
            teamPointsSpans.forEach(span => {
                span.textContent = teamData.points;
            });
            
            // Update in leaderboard
            const leaderboardPoints = document.querySelector(`.team-points-leaderboard[data-team-id="${teamData.id}"]`);
            if (leaderboardPoints) {
                leaderboardPoints.textContent = `${teamData.points} pts`;
            }
        }

        // Update full leaderboard
        function updateLeaderboard(teams) {
            teams.sort((a, b) => {
                if (b.points !== a.points) return b.points - a.points;
                return a.team_name.localeCompare(b.team_name);
            });
            
            teams.forEach((team, index) => {
                const position = index + 1;
                const leaderboardItem = document.querySelector(`.leaderboard-item[data-team-id="${team.id}"]`);
                
                if (leaderboardItem) {
                    const positionBadge = leaderboardItem.querySelector('.position-badge');
                    const pointsDisplay = leaderboardItem.querySelector('.team-points-leaderboard');
                    
                    if (positionBadge) positionBadge.textContent = position;
                    if (pointsDisplay) pointsDisplay.textContent = `${team.points} pts`;
                }
            });
        }

        // Clear display
        function clearDisplay() {
            const container = document.getElementById('question-container');
            const teamButtons = document.getElementById('team-buttons');
            container.classList.add('hidden');
            teamButtons.classList.add('hidden');
            currentQuestion = null;
            answerRevealed = false;
        }

        // Show notification
        function showNotification(text, type = 'success') {
            const toast = document.getElementById('notification-toast');
            const toastText = document.getElementById('notification-text');
            
            toast.className = `fixed top-20 right-8 px-6 py-4 rounded-lg shadow-xl z-50 ${type === 'error' ? 'bg-red-500' : 'bg-green-500'} text-white`;
            toastText.textContent = text;
            toast.classList.remove('hidden');
            
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        // Toggle control panel
        function toggleControlPanel() {
            const panel = document.getElementById('control-panel');
            panel.classList.toggle('hidden');
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Space - Toggle answer
            if (e.code === 'Space' && currentQuestion) {
                e.preventDefault();
                toggleAnswer();
            }
            
            // Escape - Clear display
            if (e.code === 'Escape') {
                e.preventDefault();
                clearDisplay();
            }
            
            // C - Toggle control panel
            if (e.code === 'KeyC' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                toggleControlPanel();
            }
            
            // Number keys 1-9 - Award to team
            if (e.key >= '1' && e.key <= '9' && currentQuestion) {
                e.preventDefault();
                const teamIndex = parseInt(e.key) - 1;
                const teamBtn = document.querySelectorAll('.team-btn')[teamIndex];
                if (teamBtn) {
                    const teamId = parseInt(teamBtn.dataset.teamId);
                    awardPointsToTeam(teamId);
                }
            }
        });

        // Initialize on page load
        window.addEventListener('DOMContentLoaded', () => {
            initWebSocket();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (wsClient) {
                wsClient.disconnect();
            }
        });
    </script>
</body>
</html>
