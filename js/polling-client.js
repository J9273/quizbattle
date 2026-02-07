/**
 * Polling Client - Alternative to WebSocket for Render Free Tier
 * Provides near-real-time updates via HTTP polling
 */

class QuizPollingClient {
    constructor(episodeId, interval = 2000) {
        this.episodeId = episodeId;
        this.interval = interval; // Poll every 2 seconds
        this.lastUpdate = 0;
        this.isPolling = false;
        this.pollTimer = null;
        this.eventHandlers = {};
        this.currentState = null;
    }

    /**
     * Start polling for updates
     */
    start() {
        if (this.isPolling) return;
        
        this.isPolling = true;
        console.log('Started polling for episode', this.episodeId);
        
        // Initial fetch
        this.poll();
        
        // Set up recurring polling
        this.pollTimer = setInterval(() => {
            this.poll();
        }, this.interval);
        
        this.emit('started');
    }

    /**
     * Stop polling
     */
    stop() {
        if (!this.isPolling) return;
        
        this.isPolling = false;
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        
        console.log('Stopped polling');
        this.emit('stopped');
    }

    /**
     * Poll for updates
     */
    async poll() {
        try {
            const response = await fetch(
                `/api/poll-updates.php?episode_id=${this.episodeId}&last_update=${this.lastUpdate}`
            );
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                console.error('Poll error:', data.error);
                this.emit('error', data.error);
                return;
            }
            
            // Check if this is the first poll or if there are updates
            const isFirstPoll = !this.currentState;
            const hasUpdates = data.has_updates || isFirstPoll;
            
            if (hasUpdates) {
                // Detect what changed
                if (this.currentState) {
                    this.detectChanges(this.currentState, data);
                }
                
                // Update current state
                this.currentState = data;
                this.lastUpdate = data.timestamp;
                
                // Emit episode state event
                this.emit('episode_state', data);
            }
            
            // Update connection status
            this.emit('connected');
            
        } catch (error) {
            console.error('Polling failed:', error);
            this.emit('error', error);
            this.emit('disconnected');
        }
    }

    /**
     * Detect changes between old and new state
     */
    detectChanges(oldState, newState) {
        // Check for score changes
        if (oldState.teams && newState.teams) {
            oldState.teams.forEach((oldTeam, index) => {
                const newTeam = newState.teams.find(t => t.id === oldTeam.id);
                if (newTeam && oldTeam.points !== newTeam.points) {
                    this.emit('score_updated', {
                        team_id: newTeam.id,
                        team_data: newTeam,
                        old_points: oldTeam.points,
                        new_points: newTeam.points,
                        points_changed: newTeam.points - oldTeam.points
                    });
                }
            });
        }
        
        // Check for ranking changes
        const oldRankings = oldState.teams?.map(t => t.id).join(',');
        const newRankings = newState.teams?.map(t => t.id).join(',');
        
        if (oldRankings !== newRankings) {
            this.emit('rankings_updated', {
                teams: newState.teams
            });
        }
    }

    /**
     * Update score (sends request to server)
     */
    async updateScore(teamId, points, action = 'add') {
        try {
            const response = await fetch('/api/update-score.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    episode_id: this.episodeId,
                    team_id: teamId,
                    points: points,
                    action: action
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Force immediate poll to get updated data
                await this.poll();
            }
            
            return data;
        } catch (error) {
            console.error('Update score failed:', error);
            throw error;
        }
    }

    /**
     * Award points by question
     */
    async awardPoints(teamId, questionId) {
        try {
            const response = await fetch('/api/award-points.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    episode_id: this.episodeId,
                    team_id: teamId,
                    question_id: questionId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Force immediate poll
                await this.poll();
            }
            
            return data;
        } catch (error) {
            console.error('Award points failed:', error);
            throw error;
        }
    }

    /**
     * Calculate rankings
     */
    async calculateRankings() {
        try {
            const response = await fetch('/api/calculate-rankings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    episode_id: this.episodeId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                await this.poll();
            }
            
            return data;
        } catch (error) {
            console.error('Calculate rankings failed:', error);
            throw error;
        }
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
     * Force refresh
     */
    async refresh() {
        this.lastUpdate = 0;
        await this.poll();
    }

    /**
     * Get current state
     */
    getState() {
        return this.currentState;
    }

    /**
     * Check if polling
     */
    isActive() {
        return this.isPolling;
    }
}

// Connection status indicator (same as WebSocket version)
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

// Usage example:
/*
const pollingClient = new QuizPollingClient(episodeId, 2000);

// Listen for updates
pollingClient.on('score_updated', (data) => {
    console.log('Score updated:', data);
    updateUI(data);
});

pollingClient.on('connected', () => {
    console.log('Polling active');
});

// Start polling
pollingClient.start();

// Award points
await pollingClient.awardPoints(teamId, questionId);

// Stop polling when done
pollingClient.stop();
*/
