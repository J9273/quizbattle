<?php
/**
 * Join Episode API
 * Registers a player/team to an episode
 */

require_once '../../includes/config-render.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$episode_id = isset($input['episode_id']) ? (int)$input['episode_id'] : 0;
$team_name = isset($input['team_name']) ? trim($input['team_name']) : '';

// Validation
if (!$episode_id) {
    echo json_encode(['success' => false, 'error' => 'Episode ID required']);
    exit;
}

if (empty($team_name)) {
    echo json_encode(['success' => false, 'error' => 'Team name required']);
    exit;
}

if (strlen($team_name) > 50) {
    echo json_encode(['success' => false, 'error' => 'Team name too long (max 50 characters)']);
    exit;
}

try {
    // Check if episode exists and is active
    $stmt = $conn->prepare("SELECT * FROM quiz_episodes WHERE id = ?");
    $stmt->execute([$episode_id]);
    $episode = $stmt->fetch();
    
    if (!$episode) {
        echo json_encode(['success' => false, 'error' => 'Episode not found']);
        exit;
    }
    
    if ($episode['status'] !== 'active') {
        echo json_encode(['success' => false, 'error' => 'Episode is not active']);
        exit;
    }
    
    // Check if team already exists
    $stmt = $conn->prepare("SELECT * FROM teams WHERE episode_id = ? AND team_name = ?");
    $stmt->execute([$episode_id, $team_name]);
    $team = $stmt->fetch();
    
    if ($team) {
        // Team exists, return existing data
        echo json_encode([
            'success' => true,
            'team_id' => $team['id'],
            'team_name' => $team['team_name'],
            'points' => $team['points'],
            'message' => 'Rejoined existing team'
        ]);
    } else {
        // Create new team
        $stmt = $conn->prepare("
            INSERT INTO teams (episode_id, team_name, points, position) 
            VALUES (?, ?, 0, (SELECT COALESCE(MAX(position), 0) + 1 FROM teams WHERE episode_id = ?))
            RETURNING id, team_name, points
        ");
        $stmt->execute([$episode_id, $team_name, $episode_id]);
        $new_team = $stmt->fetch();
        
        // Update episode team count
        $stmt = $conn->prepare("
            UPDATE quiz_episodes 
            SET number_of_teams = (SELECT COUNT(*) FROM teams WHERE episode_id = ?)
            WHERE id = ?
        ");
        $stmt->execute([$episode_id, $episode_id]);
        
        echo json_encode([
            'success' => true,
            'team_id' => $new_team['id'],
            'team_name' => $new_team['team_name'],
            'points' => $new_team['points'],
            'message' => 'Team created successfully'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Join episode error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
