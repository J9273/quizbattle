# WebSocket API Documentation

## Overview

The Quiz Application uses WebSocket for real-time bidirectional communication between clients and server. All messages are JSON-formatted and follow a consistent structure.

## Connection

**Endpoint:** `ws://localhost:8080`  
**Protocol:** WebSocket (RFC 6455)  
**Format:** JSON

### Connection Lifecycle

1. **Connect:** Client establishes WebSocket connection
2. **Join:** Client sends `join_episode` message
3. **Exchange:** Bidirectional message exchange
4. **Heartbeat:** Periodic ping/pong to keep connection alive
5. **Disconnect:** Client or server closes connection
6. **Reconnect:** Client automatically attempts reconnection

## Client Types

- `admin` - Quiz administrators with full control
- `display` - Display screens showing questions/leaderboard
- `participant` - Viewers watching the quiz

## Message Structure

All messages follow this format:

```json
{
  "type": "message_type",
  "episode_id": 1,
  "data": { /* message-specific data */ }
}
```

## Client → Server Messages

### 1. Join Episode

Join an episode room to receive updates.

```json
{
  "type": "join_episode",
  "episode_id": 1,
  "client_type": "admin"
}
```

**Parameters:**
- `episode_id` (int) - Episode to join
- `client_type` (string) - Client role: admin, display, participant

**Response:** `episode_state` message with current episode data

---

### 2. Update Score

Modify a team's score.

```json
{
  "type": "update_score",
  "episode_id": 1,
  "team_id": 5,
  "points": 10,
  "action": "add"
}
```

**Parameters:**
- `episode_id` (int) - Episode ID
- `team_id` (int) - Team to update
- `points` (int) - Points amount
- `action` (string) - Action: `add`, `subtract`, `set`

**Response:** `score_updated` broadcast to all clients

---

### 3. Award Points

Award points based on question difficulty.

```json
{
  "type": "award_points",
  "episode_id": 1,
  "team_id": 5,
  "question_id": 42
}
```

**Parameters:**
- `episode_id` (int) - Episode ID
- `team_id` (int) - Team that answered correctly
- `question_id` (int) - Question that was answered

**Server Action:**
1. Fetch question level
2. Get points for that level
3. Add points to team
4. Broadcast `points_awarded`

**Response:** `points_awarded` broadcast to all clients

---

### 4. Show Question

Display a question on all connected displays.

```json
{
  "type": "show_question",
  "episode_id": 1,
  "question_id": 42
}
```

**Parameters:**
- `episode_id` (int) - Episode ID
- `question_id` (int) - Question to display

**Response:** `question_displayed` broadcast with full question data

---

### 5. Reveal Answer

Toggle answer visibility on displays.

```json
{
  "type": "reveal_answer",
  "episode_id": 1,
  "revealed": true
}
```

**Parameters:**
- `episode_id` (int) - Episode ID
- `revealed` (bool) - Show (true) or hide (false) answer

**Response:** `answer_revealed` broadcast to all clients

---

### 6. Calculate Rankings

Recalculate team positions based on points.

```json
{
  "type": "calculate_rankings",
  "episode_id": 1
}
```

**Parameters:**
- `episode_id` (int) - Episode ID

**Server Action:**
1. Sort teams by points (descending)
2. Update position field for each team
3. Broadcast updated rankings

**Response:** `rankings_updated` broadcast with sorted teams

---

### 7. Sync Request

Request full episode state.

```json
{
  "type": "sync_request",
  "episode_id": 1
}
```

**Parameters:**
- `episode_id` (int) - Episode ID

**Response:** `episode_state` with complete episode data

---

### 8. Update Episode Status

Change episode status (admin only).

```json
{
  "type": "episode_status",
  "episode_id": 1,
  "status": "completed"
}
```

**Parameters:**
- `episode_id` (int) - Episode ID
- `status` (string) - Status: `active`, `completed`, `archived`

**Response:** `episode_status_changed` broadcast

---

### 9. Heartbeat

Keep connection alive.

```json
{
  "type": "heartbeat"
}
```

**Response:** `pong` message

---

## Server → Client Messages

### 1. Episode State

Complete episode data (sent on join or sync).

```json
{
  "type": "episode_state",
  "episode": {
    "id": 1,
    "episode_name": "Final Quiz 2024",
    "episode_date": "2024-12-15",
    "number_of_teams": 4,
    "status": "active"
  },
  "teams": [
    {
      "id": 1,
      "team_name": "Team Alpha",
      "points": 45,
      "position": 1
    },
    {
      "id": 2,
      "team_name": "Team Beta",
      "points": 30,
      "position": 2
    }
  ]
}
```

---

### 2. Score Updated

A team's score was modified.

```json
{
  "type": "score_updated",
  "team_id": 5,
  "team_data": {
    "id": 5,
    "team_name": "Team Alpha",
    "points": 55,
    "position": 1
  },
  "action": "add",
  "points_changed": 10
}
```

---

### 3. Points Awarded

Points awarded via question.

```json
{
  "type": "points_awarded",
  "team_id": 5,
  "team_data": {
    "id": 5,
    "team_name": "Team Alpha",
    "points": 55,
    "position": 1
  },
  "points": 10,
  "question_id": 42,
  "level": "hard"
}
```

---

### 4. Rankings Updated

Team positions recalculated.

```json
{
  "type": "rankings_updated",
  "teams": [
    {
      "id": 1,
      "team_name": "Team Alpha",
      "points": 55,
      "position": 1
    },
    {
      "id": 2,
      "team_name": "Team Beta",
      "points": 45,
      "position": 2
    }
  ]
}
```

---

### 5. Question Displayed

Question shown on displays.

```json
{
  "type": "question_displayed",
  "question": {
    "id": 42,
    "question": "What is the capital of France?",
    "theme": "Geography",
    "level": "easy",
    "answer": "Paris",
    "points": 1
  }
}
```

---

### 6. Answer Revealed

Answer visibility toggled.

```json
{
  "type": "answer_revealed",
  "revealed": true
}
```

---

### 7. Client Joined

New client connected to episode.

```json
{
  "type": "client_joined",
  "client_type": "display",
  "total_clients": 5
}
```

---

### 8. Client Left

Client disconnected from episode.

```json
{
  "type": "client_left",
  "client_type": "display"
}
```

---

### 9. Episode Status Changed

Episode status updated.

```json
{
  "type": "episode_status_changed",
  "status": "completed"
}
```

---

### 10. Pong

Response to heartbeat.

```json
{
  "type": "pong"
}
```

---

## JavaScript Client Library

### Basic Usage

```javascript
// Initialize client
const wsClient = new QuizWebSocketClient('ws://localhost:8080');

// Connect to server
await wsClient.connect();

// Join episode
wsClient.joinEpisode(1, 'admin');

// Listen for events
wsClient.on('score_updated', (data) => {
  console.log('Score updated:', data);
  updateUI(data);
});

// Send messages
wsClient.awardPoints(teamId, questionId);
wsClient.showQuestion(questionId);
wsClient.revealAnswer(true);

// Disconnect
wsClient.disconnect();
```

### Event Handling

```javascript
// Register event handler
wsClient.on('points_awarded', (data) => {
  console.log(`${data.points} points to team ${data.team_id}`);
});

// Remove event handler
wsClient.off('points_awarded', handlerFunction);

// One-time handler
wsClient.on('episode_state', (data) => {
  console.log('Initial state:', data);
});
```

### Connection Management

```javascript
// Connection events
wsClient.on('connected', () => {
  console.log('Connected to server');
});

wsClient.on('disconnected', () => {
  console.log('Disconnected - will auto-reconnect');
});

wsClient.on('error', (error) => {
  console.error('WebSocket error:', error);
});

// Check connection status
if (wsClient.isConnected()) {
  // Send messages
}

// Get detailed status
const status = wsClient.getStatus();
// { state: 'connected', episodeId: 1, clientType: 'admin', reconnectAttempts: 0 }
```

### Auto-Reconnection

The client automatically reconnects on disconnect:
- Max attempts: 5
- Delay: 3 seconds
- Exponential backoff (optional)

```javascript
// Customize reconnection
wsClient.maxReconnectAttempts = 10;
wsClient.reconnectDelay = 5000; // 5 seconds
```

## Error Handling

### Server Errors

Server may send error messages:

```json
{
  "type": "error",
  "code": "INVALID_EPISODE",
  "message": "Episode not found"
}
```

**Common Error Codes:**
- `INVALID_EPISODE` - Episode doesn't exist
- `INVALID_TEAM` - Team doesn't exist
- `INVALID_QUESTION` - Question doesn't exist
- `PERMISSION_DENIED` - Action not allowed for client type
- `DATABASE_ERROR` - Server database error

### Client Errors

Handle errors in event listeners:

```javascript
wsClient.on('error', (error) => {
  console.error('Connection error:', error);
  // Show user notification
  showErrorToast('Connection lost. Reconnecting...');
});
```

## Best Practices

### 1. Always Check Connection

```javascript
if (wsClient.isConnected()) {
  wsClient.showQuestion(questionId);
} else {
  console.error('Not connected to server');
  showErrorToast('Connection lost');
}
```

### 2. Handle All Events

```javascript
// Always listen for critical events
wsClient.on('disconnected', handleDisconnect);
wsClient.on('episode_state', handleInitialState);
wsClient.on('score_updated', updateLeaderboard);
```

### 3. Cleanup on Page Unload

```javascript
window.addEventListener('beforeunload', () => {
  wsClient.disconnect();
});
```

### 4. Use Sync Request

If client gets out of sync:

```javascript
wsClient.requestSync(); // Re-fetches full episode state
```

### 5. Debounce Frequent Updates

```javascript
// Don't spam server with rapid score updates
let scoreUpdateTimeout;
function updateScore(teamId, points) {
  clearTimeout(scoreUpdateTimeout);
  scoreUpdateTimeout = setTimeout(() => {
    wsClient.updateScore(teamId, points, 'add');
  }, 500);
}
```

## Security Considerations

### 1. Authentication

In production, implement token-based auth:

```javascript
// Client sends auth token
{
  "type": "join_episode",
  "episode_id": 1,
  "client_type": "admin",
  "token": "jwt_token_here"
}

// Server validates token before allowing actions
```

### 2. Input Validation

Server validates all inputs:
- Episode ID exists
- Team ID belongs to episode
- Question ID exists
- Points are reasonable values
- Client type has permission for action

### 3. Rate Limiting

Implement rate limiting to prevent abuse:

```javascript
// Server tracks messages per client
// Blocks if too many messages in short time
```

### 4. WSS in Production

Use secure WebSocket (WSS) with SSL/TLS:

```javascript
const wsClient = new QuizWebSocketClient('wss://yourdomain.com/ws');
```

## Testing

### Manual Testing

Use browser console:

```javascript
// Connect
const ws = new WebSocket('ws://localhost:8080');

// Listen
ws.onmessage = (e) => console.log(JSON.parse(e.data));

// Send
ws.send(JSON.stringify({
  type: 'join_episode',
  episode_id: 1,
  client_type: 'admin'
}));
```

### Automated Testing

Use testing library like `ws` for Node.js:

```javascript
const WebSocket = require('ws');

describe('WebSocket Server', () => {
  it('should connect and join episode', (done) => {
    const ws = new WebSocket('ws://localhost:8080');
    
    ws.on('open', () => {
      ws.send(JSON.stringify({
        type: 'join_episode',
        episode_id: 1,
        client_type: 'admin'
      }));
    });
    
    ws.on('message', (data) => {
      const msg = JSON.parse(data);
      expect(msg.type).toBe('episode_state');
      done();
    });
  });
});
```

## Troubleshooting

### Connection Issues

**Problem:** Can't connect  
**Solution:** 
- Check if server is running
- Verify port 8080 is open
- Check browser console for errors

**Problem:** Frequent disconnects  
**Solution:**
- Check server logs
- Verify heartbeat is working
- Check network stability

### Message Issues

**Problem:** Messages not received  
**Solution:**
- Verify client joined episode
- Check message format
- Look for errors in server logs

**Problem:** State out of sync  
**Solution:**
- Call `wsClient.requestSync()`
- Reload page if necessary

## Performance

### Optimization Tips

1. **Batch updates** when possible
2. **Debounce** rapid score changes
3. **Minimize** message payload size
4. **Reuse** connections (don't reconnect unnecessarily)
5. **Monitor** connection count and memory usage

### Scalability

For high traffic:
- Use Redis for session storage
- Implement horizontal scaling with load balancer
- Use message queue (RabbitMQ, Redis Pub/Sub)
- Separate WebSocket and database servers

## Version History

- **v2.0.0** - WebSocket implementation with real-time updates
- **v1.0.0** - Original PHP/MySQL application

---

**Last Updated:** 2024  
**Server Version:** 2.0.0  
**Protocol Version:** 1.0
