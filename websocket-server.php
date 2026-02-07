<?php
/**
 * Quiz Application WebSocket Server
 * Handles real-time communication for quiz updates, scoring, and live display
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class QuizWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $episodes;
    protected $db;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->episodes = []; // episode_id => [clients]
        $this->connectDB();
        echo "WebSocket Server initialized\n";
    }

    private function connectDB() {
        $this->db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->db->connect_error) {
            die("Database connection failed: " . $this->db->connect_error);
        }
        $this->db->set_charset("utf8mb4");
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->episode_id = null;
        $conn->client_type = null; // 'admin', 'display', 'participant'
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }

        echo "Message received: {$data['type']} from {$from->resourceId}\n";

        switch ($data['type']) {
            case 'join_episode':
                $this->handleJoinEpisode($from, $data);
                break;
            
            case 'update_score':
                $this->handleUpdateScore($from, $data);
                break;
            
            case 'reveal_answer':
                $this->handleRevealAnswer($from, $data);
                break;
            
            case 'show_question':
                $this->handleShowQuestion($from, $data);
                break;
            
            case 'award_points':
                $this->handleAwardPoints($from, $data);
                break;
            
            case 'calculate_rankings':
                $this->handleCalculateRankings($from, $data);
                break;
            
            case 'sync_request':
                $this->handleSyncRequest($from, $data);
                break;
            
            case 'episode_status':
                $this->handleEpisodeStatus($from, $data);
                break;
            
            case 'heartbeat':
                $from->send(json_encode(['type' => 'pong']));
                break;
        }
    }

    private function handleJoinEpisode($conn, $data) {
        $episode_id = (int)$data['episode_id'];
        $client_type = $data['client_type'] ?? 'participant';
        
        // Remove from previous episode if any
        if ($conn->episode_id) {
            $this->leaveEpisode($conn);
        }
        
        // Join new episode
        $conn->episode_id = $episode_id;
        $conn->client_type = $client_type;
        
        if (!isset($this->episodes[$episode_id])) {
            $this->episodes[$episode_id] = new \SplObjectStorage;
        }
        $this->episodes[$episode_id]->attach($conn);
        
        // Send current episode state
        $this->sendEpisodeState($conn, $episode_id);
        
        // Notify others
        $this->broadcastToEpisode($episode_id, [
            'type' => 'client_joined',
            'client_type' => $client_type,
            'total_clients' => count($this->episodes[$episode_id])
        ], $conn);
        
        echo "Client {$conn->resourceId} joined episode {$episode_id} as {$client_type}\n";
    }

    private function handleUpdateScore($conn, $data) {
        $episode_id = (int)$data['episode_id'];
        $team_id = (int)$data['team_id'];
        $points = (int)$data['points'];
        $action = $data['action'] ?? 'add'; // 'add', 'set', 'subtract'
        
        // Update database
        if ($action === 'add') {
            $stmt = $this->db->prepare("UPDATE teams SET points = points + ? WHERE id = ? AND episode_id = ?");
        } elseif ($action === 'subtract') {
            $stmt = $this->db->prepare("UPDATE teams SET points = points - ? WHERE id = ? AND episode_id = ?");
        } else {
            $stmt = $this->db->prepare("UPDATE teams SET points = ? WHERE id = ? AND episode_id = ?");
        }
        
        $stmt->bind_param("iii", $points, $team_id, $episode_id);
        $stmt->execute();
        
        // Get updated team data
        $team_data = $this->getTeamData($team_id);
        
        // Broadcast to all clients in episode
        $this->broadcastToEpisode($episode_id, [
            'type' => 'score_updated',
            'team_id' => $team_id,
            'team_data' => $team_data,
            'action' => $action,
            'points_changed' => $points
        ]);
        
        echo "Score updated for team {$team_id} in episode {$episode_id}\n";
    }

    private function handleRevealAnswer($conn, $data) {
        $episode_id = (int)$data['episode_id'];
        $revealed = (bool)$data['revealed'];
        
        $this->broadcastToEpisode($episode_id, [
            'type' => 'answer_revealed',
            'revealed' => $revealed
        ], $conn);
    }

    private function handleShowQuestion($conn, $data) {
        $episode_id = (int)$data['episode_id'];
        $question_id = (int)$data['question_id'];
        
        // Get question data
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $question = $result->fetch_assoc();
        
        // Get points for this level
        $points = $this->getPointsForLevel($question['level']);
        
        $this->broadcastToEpisode($episode_id, [
            'type' => 'question_displayed',
            'question' => [
                'id' => $question['id'],
                'question' => $question['question'],
                'theme' => $question['theme'],
                'level' => $question['level'],
                'answer' => $question['answer'],
                'points' => $points
            ]
        ]);
    }

    private function handleAwardPoints($conn, $data) {
        $episode_id = (int)$data['episode_id'];
        $team_id = (int)$data['team_id'];
        $question_id = (int)$data['question_id'];
        
        // Get question level
        $stmt = $this->db->prepare("SELECT level FROM questions WHERE id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $question = $result->fetch_assoc();
        
        // Get points for level
        $points = $this->getPointsForLevel($question['level']);
        
        // Update team points
        $stmt = $this->db->prepare("UPDATE teams SET points = points + ? WHERE id = ? AND episode_id = ?");
        $stmt->bind_param("iii", $points, $team_id, $episode_id);
        $stmt->execute();
        
        // Get updated team data
        $team_data = $this->getTeamData($team_id);
        
        // Broadcast award
        $this->broadcastToEpisode($episode_id, [
            'type' => 'points_awarded',
            'team_id' => $team_id,
            'team_data' => $team_data,
            'points' => $points,
            'question_id' => $question_id,
            'level' => $question['level']
        ]);
        
        echo "Awarded {$points} points to team {$team_id}\n";
    }

    private function handleCalculateRankings($conn, $data) {
        $episode_id = (int)$data['episode_id'];
        
        // Get all teams for episode
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY points DESC, team_name ASC");
        $stmt->bind_param("i", $episode_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $position = 1;
        $teams = [];
        while ($team = $result->fetch_assoc()) {
            // Update position
            $update_stmt = $this->db->prepare("UPDATE teams SET position = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $position, $team['id']);
            $update_stmt->execute();
            
            $team['position'] = $position;
            $teams[] = $team;
            $position++;
        }
        
        // Broadcast rankings
        $this->broadcastToEpisode($episode_id, [
            'type' => 'rankings_updated',
            'teams' => $teams
        ]);
    }

    private function handleSyncRequest($conn, $data) {
        $episode_id = (int)$data['episode_id'];
        $this->sendEpisodeState($conn, $episode_id);
    }

    private function handleEpisodeStatus($conn, $data) {
        $episode_id = (int)$data['episode_id'];
        $status = $data['status'];
        
        $stmt = $this->db->prepare("UPDATE quiz_episodes SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $episode_id);
        $stmt->execute();
        
        $this->broadcastToEpisode($episode_id, [
            'type' => 'episode_status_changed',
            'status' => $status
        ]);
    }

    private function sendEpisodeState($conn, $episode_id) {
        // Get episode data
        $stmt = $this->db->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
        $stmt->bind_param("i", $episode_id);
        $stmt->execute();
        $episode = $stmt->get_result()->fetch_assoc();
        
        // Get teams
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE episode_id = ? ORDER BY position ASC, points DESC");
        $stmt->bind_param("i", $episode_id);
        $stmt->execute();
        $teams_result = $stmt->get_result();
        $teams = [];
        while ($team = $teams_result->fetch_assoc()) {
            $teams[] = $team;
        }
        
        $conn->send(json_encode([
            'type' => 'episode_state',
            'episode' => $episode,
            'teams' => $teams
        ]));
    }

    private function getTeamData($team_id) {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getPointsForLevel($level) {
        $stmt = $this->db->prepare("SELECT points FROM points_config WHERE level = ?");
        $stmt->bind_param("s", $level);
        $stmt->execute();
        $result = $stmt->get_result();
        $config = $result->fetch_assoc();
        return $config ? (int)$config['points'] : 1;
    }

    private function broadcastToEpisode($episode_id, $message, $exclude = null) {
        if (!isset($this->episodes[$episode_id])) {
            return;
        }
        
        $json = json_encode($message);
        foreach ($this->episodes[$episode_id] as $client) {
            if ($client !== $exclude) {
                $client->send($json);
            }
        }
    }

    private function leaveEpisode($conn) {
        if ($conn->episode_id && isset($this->episodes[$conn->episode_id])) {
            $this->episodes[$conn->episode_id]->detach($conn);
            
            $this->broadcastToEpisode($conn->episode_id, [
                'type' => 'client_left',
                'client_type' => $conn->client_type
            ]);
            
            // Clean up empty episodes
            if (count($this->episodes[$conn->episode_id]) === 0) {
                unset($this->episodes[$conn->episode_id]);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->leaveEpisode($conn);
        $this->clients->detach($conn);
        echo "Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new QuizWebSocket()
        )
    ),
    8080
);

echo "WebSocket server started on port 8080\n";
echo "Waiting for connections...\n";

$server->run();
