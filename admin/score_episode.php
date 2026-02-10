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
    
    // Get teams
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY points DESC");
    $stmt->execute([$episode_id]);
    $teams = $stmt->fetchAll();
    
    // Get available questions with points
    $stmt = $conn->query("
        SELECT q.*, pc.points 
        FROM questions q
        LEFT JOIN points_config pc ON q.level = pc.level
        WHERE q.availability = 'available'
        ORDER BY q.theme, q.level
    ");
    $questions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Score episode error: " . $e->getMessage());
    header("Location: episodes.php");
    exit;
}

// Handle scoring actions via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        
        if ($action === 'award_points') {
            $team_id = (int)$_POST['team_id'];
            $points = (int)$_POST['points'];
            
            $stmt = $conn->prepare("UPDATE teams SET points = points + ? WHERE id = ? AND episode_id = ?");
            $stmt->execute([$points, $team_id, $episode_id]);
            
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'set_points') {
            $team_id = (int)$_POST['team_id'];
            $points = (int)$_POST['points'];
            
            $stmt = $conn->prepare("UPDATE teams SET points = ? WHERE id = ? AND episode_id = ?");
            $stmt->execute([$points, $team_id, $episode_id]);
            
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'get_scores') {
            $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY points DESC");
            $stmt->execute([$episode_id]);
            $teams = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'teams' => $teams]);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Score Episode - <?= htmlspecialchars($episode['episode_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    
    <!-- Top Bar -->
    <nav class="bg-white shadow-lg mb-4 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($episode['episode_name']) ?></h1>
                    <p class="text-sm text-gray-600">Scoring Mode • Episode #<?= $episode['id'] ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <button onclick="refreshScores()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                        🔄 Refresh
                    </button>
                    <a href="view_episode.php?id=<?= $episode['id'] ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded text-sm">
                        ✕ Exit
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left: Question Selector -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Question Browser -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">📝 Select Question</h2>
                    
                    <select id="question-select" class="w-full p-3 border-2 border-gray-300 rounded-lg mb-4" onchange="showQuestion()">
                        <option value="">-- Select a question --</option>
                        <?php foreach ($questions as $q): ?>
                            <option value="<?= $q['id'] ?>" 
                                    data-question="<?= htmlspecialchars($q['question']) ?>"
                                    data-answer="<?= htmlspecialchars($q['answer']) ?>"
                                    data-theme="<?= htmlspecialchars($q['theme']) ?>"
                                    data-level="<?= $q['level'] ?>"
                                    data-points="<?= $q['points'] ?>">
                                [<?= strtoupper($q['level']) ?>] <?= htmlspecialchars($q['theme']) ?>: <?= htmlspecialchars(substr($q['question'], 0, 60)) ?>...
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Question Display -->
                    <div id="question-display" class="hidden bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <span id="q-theme" class="bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-bold"></span>
                            <span id="q-level" class="bg-green-500 text-white px-3 py-1 rounded-full text-sm font-bold"></span>
                            <span id="q-points" class="bg-yellow-500 text-white px-3 py-1 rounded-full text-sm font-bold"></span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-4" id="q-text"></h3>
                        <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded">
                            <p class="text-sm font-bold text-yellow-800">ANSWER:</p>
                            <p class="text-lg font-bold text-yellow-900" id="q-answer"></p>
                        </div>
                    </div>
                </div>

                <!-- Quick Award -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">⚡ Quick Award Points</h3>
                    
                    <div id="no-question-selected" class="text-gray-500 text-center py-4">
                        Select a question above to award points
                    </div>

                    <div id="award-section" class="hidden grid grid-cols-2 gap-3">
                        <?php foreach ($teams as $team): ?>
                            <button onclick="awardQuestionPoints(<?= $team['id'] ?>)" 
                                    class="team-award-btn bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition-all">
                                <div class="text-sm">Award to</div>
                                <div class="font-bold"><?= htmlspecialchars($team['team_name']) ?></div>
                                <div class="text-sm" id="points-display-<?= $team['id'] ?>">0 pts</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Manual Points -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">🎯 Manual Point Adjustment</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($teams as $team): ?>
                            <div class="border-2 border-gray-200 rounded-lg p-4">
                                <h4 class="font-bold text-gray-800 mb-2"><?= htmlspecialchars($team['team_name']) ?></h4>
                                <div class="flex gap-2">
                                    <button onclick="adjustPoints(<?= $team['id'] ?>, -5)" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">-5</button>
                                    <button onclick="adjustPoints(<?= $team['id'] ?>, -1)" class="bg-red-400 hover:bg-red-500 text-white px-3 py-1 rounded">-1</button>
                                    <button onclick="adjustPoints(<?= $team['id'] ?>, 1)" class="bg-green-400 hover:bg-green-500 text-white px-3 py-1 rounded">+1</button>
                                    <button onclick="adjustPoints(<?= $team['id'] ?>, 5)" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">+5</button>
                                    <button onclick="adjustPoints(<?= $team['id'] ?>, 10)" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded">+10</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Live Scoreboard -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-lg p-6 sticky top-20">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">🏆 Live Scoreboard</h2>
                    
                    <div id="scoreboard" class="space-y-3">
                        <?php foreach ($teams as $index => $team): ?>
                            <?php
                            $position = $index + 1;
                            $medal = $position === 1 ? '🥇' : ($position === 2 ? '🥈' : ($position === 3 ? '🥉' : ''));
                            ?>
                            <div class="team-score border-2 border-gray-200 rounded-lg p-3 transition-all" data-team-id="<?= $team['id'] ?>">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl font-bold text-gray-400">
                                            <?= $medal ?: $position ?>
                                        </span>
                                        <div>
                                            <div class="font-bold text-gray-800"><?= htmlspecialchars($team['team_name']) ?></div>
                                            <div class="text-xs text-gray-500">Team #<?= $team['id'] ?></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-gray-800 team-points"><?= $team['points'] ?></div>
                                        <div class="text-xs text-gray-600">points</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="text-sm text-gray-600 text-center">
                            Auto-refreshes every 3 seconds
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentQuestionPoints = 0;
        let autoRefreshInterval;

        // Show selected question
        function showQuestion() {
            const select = document.getElementById('question-select');
            const option = select.options[select.selectedIndex];
            
            if (!option.value) {
                document.getElementById('question-display').classList.add('hidden');
                document.getElementById('award-section').classList.add('hidden');
                document.getElementById('no-question-selected').classList.remove('hidden');
                return;
            }
            
            const question = option.dataset.question;
            const answer = option.dataset.answer;
            const theme = option.dataset.theme;
            const level = option.dataset.level;
            const points = parseInt(option.dataset.points);
            
            currentQuestionPoints = points;
            
            document.getElementById('q-theme').textContent = theme;
            document.getElementById('q-level').textContent = level.toUpperCase();
            document.getElementById('q-points').textContent = points + ' points';
            document.getElementById('q-text').textContent = question;
            document.getElementById('q-answer').textContent = answer;
            
            document.getElementById('question-display').classList.remove('hidden');
            document.getElementById('award-section').classList.remove('hidden');
            document.getElementById('no-question-selected').classList.add('hidden');
            
            // Update button labels
            document.querySelectorAll('[id^="points-display-"]').forEach(el => {
                el.textContent = points + ' pts';
            });
        }

        // Award question points to team
        async function awardQuestionPoints(teamId) {
            if (currentQuestionPoints === 0) {
                alert('Please select a question first');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'award_points');
            formData.append('team_id', teamId);
            formData.append('points', currentQuestionPoints);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    await refreshScores();
                    flashTeam(teamId);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Adjust points manually
        async function adjustPoints(teamId, points) {
            const formData = new FormData();
            formData.append('action', 'award_points');
            formData.append('team_id', teamId);
            formData.append('points', points);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    await refreshScores();
                    flashTeam(teamId);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Refresh scores
        async function refreshScores() {
            const formData = new FormData();
            formData.append('action', 'get_scores');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    updateScoreboard(data.teams);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Update scoreboard
        function updateScoreboard(teams) {
            teams.sort((a, b) => b.points - a.points);
            
            teams.forEach((team, index) => {
                const teamEl = document.querySelector(`[data-team-id="${team.id}"]`);
                if (teamEl) {
                    const pointsEl = teamEl.querySelector('.team-points');
                    const oldPoints = parseInt(pointsEl.textContent);
                    const newPoints = team.points;
                    
                    if (oldPoints !== newPoints) {
                        pointsEl.textContent = newPoints;
                        flashTeam(team.id);
                    }
                }
            });
            
            // Re-sort DOM elements
            const scoreboard = document.getElementById('scoreboard');
            const teamEls = Array.from(scoreboard.querySelectorAll('.team-score'));
            teamEls.sort((a, b) => {
                const aPoints = parseInt(a.querySelector('.team-points').textContent);
                const bPoints = parseInt(b.querySelector('.team-points').textContent);
                return bPoints - aPoints;
            });
            teamEls.forEach(el => scoreboard.appendChild(el));
        }

        // Flash team when points change
        function flashTeam(teamId) {
            const teamEl = document.querySelector(`[data-team-id="${teamId}"]`);
            if (teamEl) {
                teamEl.classList.add('bg-green-100', 'border-green-500');
                setTimeout(() => {
                    teamEl.classList.remove('bg-green-100', 'border-green-500');
                }, 1000);
            }
        }

        // Auto-refresh scores every 3 seconds
        autoRefreshInterval = setInterval(refreshScores, 3000);

        // Stop auto-refresh when leaving page
        window.addEventListener('beforeunload', () => {
            clearInterval(autoRefreshInterval);
        });
    </script>
</body>
</html>
