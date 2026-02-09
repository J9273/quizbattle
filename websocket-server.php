<?php
/**
 * Simplified WebSocket Server for Quiz Battle
 * Handles player buzzes and host controls
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config-render.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class QuizWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $episodes;
    protected $conn;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->episodes = []; // episode_id => ['host' => conn, 'players' => [conn, conn]]
        $this->connectDB();
        echo "Quiz WebSocket Server started\n";
    }

    private function connectDB() {
        $database_url = getenv('DATABASE_URL');
        if ($database_url) {
            $db = parse_url($database_url);
            $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/');
            $this->conn = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->episodeId = null;
        $conn->clientType = null; // 'host' or 'player'
        $conn->teamName = null;
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }

        echo "Received: {$data['type']} from {$from->resourceId}\n";

        switch ($data['type']) {
            case 'join_as_host':
                $this->handleJoinAsHost($from, $data);
                break;
            
            case 'join_as_player':
                $this->handleJoinAsPlayer($from, $data);
                break;
            
            case 'display_question':
                $this->handleDisplayQuestion($from, $data);
                break;
            
            case 'buzz_answer':
                $this->handleBuzzAnswer($from, $data);
                break;
            
            case 'reveal_answer':
                $this->handleRevealAnswer($from, $data);
                break;
            
            case 'award_points':
                $this->handleAwardPoints($from, $data);
                break;
            
            case 'get_scores':
                $this->handleGetScores($from, $data);
                break;
        }
    }

    private function handleJoinAsHost($conn, $data) {
        $episodeId = (int)$data['episode_id'];
        
        if (!isset($this->episodes[$episodeId])) {
            $this->episodes[$episodeId] = ['host' => null, 'players' => []];
        }
        
        $this->episodes[$episodeId]['host'] = $conn;
        $conn->episodeId = $episodeId;
        $conn->clientType = 'host';
        
        // Send current state
        $scores = $this->getEpisodeScores($episodeId);
        $conn->send(json_encode([
            'type' => 'host_connected',
            'episode_id' => $episodeId,
            'scores' => $scores,
            'player_count' => count($this->episodes[$episodeId]['players'])
        ]));
        
        echo "Host joined episode {$episodeId}\n";
    }

    private function handleJoinAsPlayer($conn, $data) {
        $episodeId = (int)$data['episode_id'];
        $teamName = $data['team_name'];
        
        if (!isset($this->episodes[$episodeId])) {
            $this->episodes[$episodeId] = ['host' => null, 'players' => []];
        }
        
        // Check if team exists in database, create if not
        $stmt = $this->conn->prepare("SELECT id, points FROM teams WHERE episode_id = ? AND team_name = ?");
        $stmt->execute([$episodeId, $teamName]);
        $team = $stmt->fetch();
        
        if (!$team) {
            // Create new team
            $stmt = $this->conn->prepare("INSERT INTO teams (episode_id, team_name, points) VALUES (?, ?, 0) RETURNING id, points");
            $stmt->execute([$episodeId, $teamName]);
            $team = $stmt->fetch();
        }
        
        $this->episodes[$episodeId]['players'][] = $conn;
        $conn->episodeId = $episodeId;
        $conn->clientType = 'player';
        $conn->teamName = $teamName;
        $conn->teamId = $team['id'];
        
        // Send connection confirmation
        $conn->send(json_encode([
            'type' => 'player_connected',
            'episode_id' => $episodeId,
            'team_name' => $teamName,
            'team_id' => $team['id'],
            'points' => $team['points']
        ]));
        
        // Notify host of new player
        if ($this->episodes[$episodeId]['host']) {
            $this->episodes[$episodeId]['host']->send(json_encode([
                'type' => 'player_joined',
                'team_name' => $teamName,
                'player_count' => count($this->episodes[$episodeId]['players'])
            ]));
        }
        
        echo "Player '{$teamName}' joined episode {$episodeId}\n";
    }

    private function handleDisplayQuestion($conn, $data) {
        $episodeId = $conn->episodeId;
        $questionId = (int)$data['question_id'];
        
        // Get question from database
        $stmt = $this->conn->prepare("SELECT * FROM questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch();
        
        if (!$question) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Question not found']));
            return;
        }
        
        // Get points for this level
        $stmt = $this->conn->prepare("SELECT points FROM points_config WHERE level = ?");
        $stmt->execute([$question['level']]);
        $pointsConfig = $stmt->fetch();
        $points = $pointsConfig ? $pointsConfig['points'] : 1;
        
        // Broadcast to all players
        $this->broadcastToEpisode($episodeId, [
            'type' => 'question_displayed',
            'question' => [
                'id' => $question['id'],
                'question' => $question['question'],
                'theme' => $question['theme'],
                'level' => $question['level'],
                'points' => $points
            ]
        ], 'player');
        
        // Send to host with answer
        $conn->send(json_encode([
            'type' => 'question_displayed',
            'question' => [
                'id' => $question['id'],
                'question' => $question['question'],
                'theme' => $question['theme'],
                'level' => $question['level'],
                'answer' => $question['answer'],
                'points' => $points
            ]
        ]));
        
        echo "Question {$questionId} displayed for episode {$episodeId}\n";
    }

    private function handleBuzzAnswer($conn, $data) {
        $episodeId = $conn->episodeId;
        $teamName = $conn->teamName;
        $answer = $data['answer'];
        
        // Notify host
        if (isset($this->episodes[$episodeId]['host'])) {
            $this->episodes[$episodeId]['host']->send(json_encode([
                'type' => 'buzz_received',
                'team_name' => $teamName,
                'team_id' => $conn->teamId,
                'answer' => $answer,
                'timestamp' => time()
            ]));
        }
        
        // Confirm to player
        $conn->send(json_encode([
            'type' => 'buzz_confirmed',
            'answer' => $answer
        ]));
        
        echo "Buzz from '{$teamName}': {$answer}\n";
    }

    private function handleRevealAnswer($conn, $data) {
        $episodeId = $conn->episodeId;
        $answer = $data['answer'];
        
        // Broadcast answer to all players
        $this->broadcastToEpisode($episodeId, [
            'type' => 'answer_revealed',
            'answer' => $answer
        ], 'player');
        
        echo "Answer revealed for episode {$episodeId}\n";
    }

    private function handleAwardPoints($conn, $data) {
        $episodeId = $conn->episodeId;
        $teamId = (int)$data['team_id'];
        $points = (int)$data['points'];
        
        // Update database
        $stmt = $this->conn->prepare("UPDATE teams SET points = points + ? WHERE id = ? AND episode_id = ?");
        $stmt->execute([$points, $teamId, $episodeId]);
        
        // Get updated scores
        $scores = $this->getEpisodeScores($episodeId);
        
        // Broadcast to everyone
        $this->broadcastToEpisode($episodeId, [
            'type' => 'scores_updated',
            'scores' => $scores
        ]);
        
        echo "Awarded {$points} points to team {$teamId}\n";
    }

    private function handleGetScores($conn, $data) {
        $episodeId = $conn->episodeId;
        $scores = $this->getEpisodeScores($episodeId);
        
        $conn->send(json_encode([
            'type' => 'scores_updated',
            'scores' => $scores
        ]));
    }

    private function getEpisodeScores($episodeId) {
        $stmt = $this->conn->prepare("SELECT id, team_name, points FROM teams WHERE episode_id = ? ORDER BY points DESC");
        $stmt->execute([$episodeId]);
        return $stmt->fetchAll();
    }

    private function broadcastToEpisode($episodeId, $message, $clientType = null) {
        if (!isset($this->episodes[$episodeId])) {
            return;
        }
        
        $json = json_encode($message);
        
        if ($clientType === 'player' || $clientType === null) {
            foreach ($this->episodes[$episodeId]['players'] as $player) {
                $player->send($json);
            }
        }
        
        if ($clientType === 'host' || $clientType === null) {
            if ($this->episodes[$episodeId]['host']) {
                $this->episodes[$episodeId]['host']->send($json);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if ($conn->episodeId && isset($this->episodes[$conn->episodeId])) {
            $episodeId = $conn->episodeId;
            
            if ($conn->clientType === 'host') {
                $this->episodes[$episodeId]['host'] = null;
            } elseif ($conn->clientType === 'player') {
                $key = array_search($conn, $this->episodes[$episodeId]['players']);
                if ($key !== false) {
                    unset($this->episodes[$episodeId]['players'][$key]);
                }
                
                // Notify host
                if ($this->episodes[$episodeId]['host']) {
                    $this->episodes[$episodeId]['host']->send(json_encode([
                        'type' => 'player_left',
                        'team_name' => $conn->teamName,
                        'player_count' => count($this->episodes[$episodeId]['players'])
                    ]));
                }
            }
        }
        
        $this->clients->detach($conn);
        echo "Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start server
$port = getenv('PORT') ?: 8080;
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new QuizWebSocket()
        )
    ),
    $port
);

echo "Quiz WebSocket server started on port {$port}\n";
echo "Waiting for connections...\n";

$server->run();
