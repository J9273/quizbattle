/**
 * Quiz Application WebSocket Client
 * Handles real-time communication with the WebSocket server
 */

class QuizWebSocketClient {
    constructor(serverUrl = 'ws://localhost:8080') {
        this.serverUrl = serverUrl;
        this.ws = null;
        this.episodeId = null;
        this.clientType = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        this.heartbeatInterval = null;
        this.eventHandlers = {};
        this.connectionState = 'disconnected';
    }

    /**
     * Connect to WebSocket server
     */
    connect() {
        return new Promise((resolve, reject) => {
            try {
                this.ws = new WebSocket(this.serverUrl);
                
                this.ws.onopen = () => {
                    console.log('WebSocket connected');
                    this.connectionState = 'connected';
                    this.reconnectAttempts = 0;
                    this.startHeartbeat();
                    this.emit('connected');
                    resolve();
                };

                this.ws.onmessage = (event) => {
                    this.handleMessage(event.data);
                };

                this.ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    this.connectionState = 'error';
                    this.emit('error', error);
                };

                this.ws.onclose = () => {
                    console.log('WebSocket disconnected');
                    this.connectionState = 'disconnected';
                    this.stopHeartbeat();
                    this.emit('disconnected');
                    this.attemptReconnect();
                };
            } catch (error) {
                reject(error);
            }
        });
    }

    /**
     * Disconnect from WebSocket server
     */
    disconnect() {
        this.reconnectAttempts = this.maxReconnectAttempts; // Prevent reconnection
        this.stopHeartbeat();
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
    }

    /**
     * Attempt to reconnect
     */
    attemptReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('Max reconnection attempts reached');
            this.emit('max_reconnect_reached');
            return;
        }

        this.reconnectAttempts++;
        console.log(`Reconnecting... Attempt ${this.reconnectAttempts}`);
        
        setTimeout(() => {
            this.connect().catch(err => {
                console.error('Reconnection failed:', err);
            });
        }, this.reconnectDelay);
    }

    /**
     * Start heartbeat to keep connection alive
     */
    startHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            if (this.isConnected()) {
                this.send({ type: 'heartbeat' });
            }
        }, 30000); // Every 30 seconds
    }

    /**
     * Stop heartbeat
     */
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }

    /**
     * Check if connected
     */
    isConnected() {
        return this.ws && this.ws.readyState === WebSocket.OPEN;
    }

    /**
     * Send message to server
     */
    send(data) {
        if (!this.isConnected()) {
            console.error('WebSocket not connected');
            return false;
        }

        try {
            this.ws.send(JSON.stringify(data));
            return true;
        } catch (error) {
            console.error('Failed to send message:', error);
            return false;
        }
    }

    /**
     * Handle incoming message
     */
    handleMessage(data) {
        try {
            const message = JSON.parse(data);
            console.log('Received:', message.type, message);
            
            // Emit event for this message type
            this.emit(message.type, message);
            
            // Also emit generic 'message' event
            this.emit('message', message);
        } catch (error) {
            console.error('Failed to parse message:', error);
        }
    }

    /**
     * Join an episode
     */
    joinEpisode(episodeId, clientType = 'participant') {
        this.episodeId = episodeId;
        this.clientType = clientType;
        
        return this.send({
            type: 'join_episode',
            episode_id: episodeId,
            client_type: clientType
        });
    }

    /**
     * Update team score
     */
    updateScore(teamId, points, action = 'add') {
        return this.send({
            type: 'update_score',
            episode_id: this.episodeId,
            team_id: teamId,
            points: points,
            action: action
        });
    }

    /**
     * Reveal/hide answer
     */
    revealAnswer(revealed) {
        return this.send({
            type: 'reveal_answer',
            episode_id: this.episodeId,
            revealed: revealed
        });
    }

    /**
     * Show question on display
     */
    showQuestion(questionId) {
        return this.send({
            type: 'show_question',
            episode_id: this.episodeId,
            question_id: questionId
        });
    }

    /**
     * Award points to team
     */
    awardPoints(teamId, questionId) {
        return this.send({
            type: 'award_points',
            episode_id: this.episodeId,
            team_id: teamId,
            question_id: questionId
        });
    }

    /**
     * Calculate and update rankings
     */
    calculateRankings() {
        return this.send({
            type: 'calculate_rankings',
            episode_id: this.episodeId
        });
    }

    /**
     * Request full episode sync
     */
    requestSync() {
        return this.send({
            type: 'sync_request',
            episode_id: this.episodeId
        });
    }

    /**
     * Update episode status
     */
    updateEpisodeStatus(status) {
        return this.send({
            type: 'episode_status',
            episode_id: this.episodeId,
            status: status
        });
    }

    /**
     * Register event handler
     */
    on(event, handler) {
        if (!this.eventHandlers[event]) {
            this.eventHandlers[event] = [];
        }
        this.eventHandlers[event].push(handler);
    }

    /**
     * Remove event handler
     */
    off(event, handler) {
        if (!this.eventHandlers[event]) return;
        
        if (handler) {
            this.eventHandlers[event] = this.eventHandlers[event].filter(h => h !== handler);
        } else {
            delete this.eventHandlers[event];
        }
    }

    /**
     * Emit event
     */
    emit(event, data) {
        if (!this.eventHandlers[event]) return;
        
        this.eventHandlers[event].forEach(handler => {
            try {
                handler(data);
            } catch (error) {
                console.error(`Error in event handler for ${event}:`, error);
            }
        });
    }

    /**
     * Get connection status
     */
    getStatus() {
        return {
            state: this.connectionState,
            episodeId: this.episodeId,
            clientType: this.clientType,
            reconnectAttempts: this.reconnectAttempts
        };
    }
}

// Connection status indicator component
class ConnectionIndicator {
    constructor(containerId = 'ws-status') {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            this.createContainer();
        }
    }

    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'ws-status';
        this.container.className = 'fixed top-4 right-4 z-50';
        document.body.appendChild(this.container);
    }

    show(status, message = '') {
        const colors = {
            connected: 'bg-green-500',
            connecting: 'bg-yellow-500',
            disconnected: 'bg-red-500',
            error: 'bg-red-600'
        };

        const icons = {
            connected: '✓',
            connecting: '⟳',
            disconnected: '✕',
            error: '!'
        };

        this.container.innerHTML = `
            <div class="${colors[status]} text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2">
                <span class="font-bold">${icons[status]}</span>
                <span>${message || status.toUpperCase()}</span>
            </div>
        `;
    }

    hide() {
        this.container.innerHTML = '';
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { QuizWebSocketClient, ConnectionIndicator };
}
