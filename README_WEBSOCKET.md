# Quiz Application - WebSocket Real-Time Edition

A complete PHP/MySQL quiz management system with **real-time WebSocket functionality** for live quiz displays, instant scoring, and synchronized updates across all connected clients.

## 🚀 New Features - WebSocket Edition

### Real-Time Capabilities
- ✅ **Live Score Updates**: Scores update instantly across all connected displays
- ✅ **Synchronized Question Display**: Show questions on projector/display screens in real-time
- ✅ **Multi-Client Support**: Multiple viewers can watch the same quiz simultaneously
- ✅ **Instant Answer Reveal**: Toggle answer visibility across all displays at once
- ✅ **Real-Time Leaderboard**: Rankings update automatically as points are awarded
- ✅ **Connection Status**: Visual indicators show connection status
- ✅ **Auto-Reconnection**: Clients automatically reconnect if connection is lost
- ✅ **Broadcast Controls**: Admin can control what all displays show

### Architecture
- **WebSocket Server**: Ratchet-based PHP WebSocket server (port 8080)
- **Client Library**: JavaScript WebSocket client with auto-reconnection
- **Real-Time Protocol**: JSON-based message protocol for all operations
- **Multi-User**: Support for admin, display, and participant client types

## 📋 Requirements

### Server Requirements
- PHP 7.4 or higher
- MySQL 5.7+ / MariaDB 10.3+
- Composer (PHP dependency manager)
- Apache/Nginx web server
- Command line access (for WebSocket server)

### PHP Extensions
- mysqli
- json
- sockets
- mbstring

### Network Requirements
- Port 8080 open for WebSocket connections
- If using remotely, ensure firewall allows WebSocket traffic

## 🔧 Installation

### Step 1: Download and Extract
```bash
# Extract to your web server directory
# XAMPP: C:\xampp\htdocs\quiz-app-websocket
# WAMP: C:\wamp64\www\quiz-app-websocket
# Linux: /var/www/html/quiz-app-websocket
```

### Step 2: Install Dependencies
```bash
cd quiz-app-websocket
composer install
```

This will install:
- `cboden/ratchet` - WebSocket server library

### Step 3: Configure Database

Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quiz_app');
```

### Step 4: Run Database Installation

Visit in browser:
```
http://localhost/quiz-app-websocket/install.php
```

This creates:
- Database and tables
- Default admin user (admin/admin123)
- Points configuration
- Sample data (optional)

### Step 5: Start WebSocket Server

Open a terminal/command prompt:

**Windows:**
```bash
cd C:\xampp\htdocs\quiz-app-websocket
php websocket-server.php
```

**Linux/Mac:**
```bash
cd /var/www/html/quiz-app-websocket
php websocket-server.php
```

You should see:
```
WebSocket Server initialized
WebSocket server started on port 8080
Waiting for connections...
```

**Keep this terminal window open** - the server must run continuously.

### Step 6: Login and Start Using

Navigate to:
```
http://localhost/quiz-app-websocket/admin/login.php
```

Default credentials:
- Username: `admin`
- Password: `admin123`

**⚠️ Change password immediately after first login!**

## 🎮 How to Use

### 1. Create Quiz Episode
1. Go to **Quiz Episodes** → **Create Episode**
2. Enter episode name, date, and number of teams
3. Add team names
4. Click **Create Episode**

### 2. Add Questions
**Option A - Single Question:**
1. Go to **Questions** → **Add Question**
2. Fill in question, theme, level, answer
3. Click **Add Question**

**Option B - Bulk Upload:**
1. Prepare CSV file (see `sample_questions.csv`)
2. Go to **Questions** → **Upload CSV**
3. Select file and upload

### 3. Run Live Quiz (NEW!)

**Setup:**
1. Open episode → Click **🎮 Scoring Mode (WebSocket)**
2. On another screen/projector, open **🎮 Live Quiz Display**
3. Both should show "Live - Connected ✓" status

**During Quiz:**
1. **Admin (Scoring Mode):**
   - Select question from dropdown
   - Click "📺 Show on Display" - question appears on projector
   - Click "👁️ Reveal Answer" when ready
   - Select team that answered correctly
   - Click "🏆 Award Points" - points update in real-time

2. **Display Screen:**
   - Shows question, theme, and points
   - Answer hidden until revealed
   - Team buttons for quick scoring
   - Live leaderboard updates automatically

3. **Keyboard Shortcuts (Display):**
   - `Space` - Toggle answer visibility
   - `1-9` - Award points to team 1-9
   - `C` - Toggle control panel
   - `Esc` - Clear display

### 4. Real-Time Features

**All connected clients see:**
- Score updates instantly
- Answer reveals simultaneously
- Ranking changes in real-time
- Connection status of other clients

**Admin can:**
- Award points by question (automatic calculation)
- Manual point adjustments
- Broadcast questions to displays
- Control answer visibility
- Update rankings
- See all connected clients

## 🏗️ Architecture

### WebSocket Server (`websocket-server.php`)
- Handles all real-time communication
- Manages episode rooms
- Broadcasts updates to clients
- Persists data to MySQL

### Client Library (`js/websocket-client.js`)
- Connects to WebSocket server
- Auto-reconnection on disconnect
- Event-based message handling
- Heartbeat for keep-alive

### Message Protocol

**Client → Server:**
```json
{
  "type": "join_episode",
  "episode_id": 1,
  "client_type": "admin"
}
```

**Server → Client:**
```json
{
  "type": "score_updated",
  "team_id": 1,
  "team_data": {...},
  "points_changed": 5
}
```

### Supported Message Types

**Client to Server:**
- `join_episode` - Join episode room
- `update_score` - Modify team score
- `award_points` - Award points by question
- `show_question` - Display question on screens
- `reveal_answer` - Show/hide answer
- `calculate_rankings` - Recalculate positions
- `sync_request` - Request full state sync
- `heartbeat` - Keep connection alive

**Server to Client:**
- `episode_state` - Full episode data
- `score_updated` - Team score changed
- `points_awarded` - Points given to team
- `rankings_updated` - New team positions
- `question_displayed` - Question shown
- `answer_revealed` - Answer visibility changed
- `client_joined` - New client connected
- `client_left` - Client disconnected

## 🔒 Security Considerations

1. **WebSocket Security:**
   - Consider using WSS (WebSocket Secure) in production
   - Implement authentication tokens for WebSocket connections
   - Validate all incoming messages

2. **Database:**
   - Use prepared statements (already implemented)
   - Change default admin password
   - Use strong database passwords

3. **Server:**
   - Run WebSocket server as non-root user
   - Use process manager (systemd, supervisor) for production
   - Implement rate limiting if needed

4. **Network:**
   - Use firewall rules to restrict WebSocket port
   - Consider reverse proxy (nginx) for WebSocket
   - Use HTTPS/WSS in production

## 🚀 Production Deployment

### Using Supervisor (Linux)

Create `/etc/supervisor/conf.d/quiz-websocket.conf`:
```ini
[program:quiz-websocket]
command=/usr/bin/php /var/www/html/quiz-app-websocket/websocket-server.php
directory=/var/www/html/quiz-app-websocket
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/quiz-websocket.err.log
stdout_logfile=/var/log/quiz-websocket.out.log
```

Start service:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start quiz-websocket
```

### Using systemd (Linux)

Create `/etc/systemd/system/quiz-websocket.service`:
```ini
[Unit]
Description=Quiz WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/quiz-app-websocket
ExecStart=/usr/bin/php /var/www/html/quiz-app-websocket/websocket-server.php
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable quiz-websocket
sudo systemctl start quiz-websocket
sudo systemctl status quiz-websocket
```

### Using PM2 (Cross-platform)

```bash
npm install -g pm2
pm2 start websocket-server.php --interpreter php --name quiz-websocket
pm2 save
pm2 startup
```

### Nginx Reverse Proxy

Add to nginx config:
```nginx
location /ws {
    proxy_pass http://localhost:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

Update client connection:
```javascript
new QuizWebSocketClient('wss://yourdomain.com/ws');
```

## 🐛 Troubleshooting

### WebSocket Server Won't Start

**Error: "Address already in use"**
```bash
# Find process using port 8080
lsof -i :8080  # Linux/Mac
netstat -ano | findstr :8080  # Windows

# Kill the process
kill -9 <PID>  # Linux/Mac
taskkill /F /PID <PID>  # Windows
```

**Error: "Connection refused"**
- Check if server is running: `ps aux | grep websocket`
- Verify port 8080 is open in firewall
- Check PHP has socket extension: `php -m | grep socket`

### Clients Can't Connect

1. **Check server status:** Server terminal should show "Waiting for connections..."
2. **Browser console:** Look for WebSocket errors (F12 → Console)
3. **Network:** Verify port 8080 isn't blocked by firewall
4. **URL:** Ensure using `ws://localhost:8080` (not `http://`)

### Scores Not Updating

1. **Check connection:** Status bar should show "Live - Connected ✓"
2. **Refresh page:** Sometimes helps re-establish connection
3. **Check server logs:** Terminal running websocket-server.php
4. **Verify database:** Check if points are updating in MySQL

### Auto-Reconnect Not Working

- Maximum reconnect attempts reached (5 by default)
- Restart WebSocket server
- Hard refresh browser (Ctrl+F5 / Cmd+Shift+R)

## 📊 Performance

### Tested Load
- 50+ concurrent connections
- Sub-100ms message latency
- Negligible CPU/memory impact

### Optimization Tips
- Use Redis for session storage (scalability)
- Implement connection pooling for database
- Use CDN for static assets
- Enable gzip compression
- Consider load balancing for high traffic

## 🔄 Migration from Old Version

1. **Backup database:** `mysqldump quiz_app > backup.sql`
2. **Install dependencies:** `composer install`
3. **Update file paths** in includes/config.php
4. **Test WebSocket server:** `php websocket-server.php`
5. **Verify all pages load** before going live

## 📁 File Structure

```
quiz-app-websocket/
├── websocket-server.php          # WebSocket server (run this!)
├── composer.json                 # Dependencies
├── install.php                   # Database setup
├── README.md                     # This file
│
├── admin/                        # Admin interface
│   ├── quiz_display_ws.php       # Live display (WebSocket)
│   ├── score_episode_ws.php      # Scoring mode (WebSocket)
│   ├── [other admin files]       # Standard admin pages
│
├── includes/                     # Core files
│   ├── config.php                # Database config
│   ├── auth.php                  # Authentication
│   └── points_helper.php         # Points calculation
│
├── js/                           # JavaScript
│   └── websocket-client.js       # WebSocket client library
│
└── vendor/                       # Composer dependencies
    └── cboden/ratchet/           # WebSocket library
```

## 🆘 Support

### Common Issues

**Q: Can I use without WebSocket?**  
A: Yes! The original non-WebSocket pages still work. WebSocket adds real-time features.

**Q: Does it work on mobile?**  
A: Yes! Both admin and display interfaces are mobile-responsive.

**Q: Can multiple quizzes run simultaneously?**  
A: Yes! Each episode creates its own "room" with isolated connections.

**Q: What happens if server crashes?**  
A: Clients will auto-reconnect when server restarts. Data is persisted in MySQL.

### Getting Help

1. Check server terminal for error messages
2. Check browser console (F12) for client errors
3. Verify all requirements are installed
4. Try the troubleshooting section above

## 📜 License

Open source - Free for educational and personal use.

## 🎉 Credits

- **WebSocket Library:** Ratchet (cboden/ratchet)
- **CSS Framework:** TailwindCSS
- **Database:** MySQL/MariaDB

---

**Version**: 2.0.0 (WebSocket Edition)  
**Last Updated**: 2024  
**Requires**: PHP 7.4+, Composer, MySQL 5.7+

---

## Quick Start Commands

```bash
# Install dependencies
composer install

# Start WebSocket server
php websocket-server.php

# In another terminal, start web server
php -S localhost:8000

# Access application
# http://localhost:8000/admin/login.php
```

Enjoy your real-time quiz experience! 🎮🏆
