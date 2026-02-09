# Player & Host Pages for Quiz Battle

These two new pages add **real-time player participation** to your Quiz Battle app!

## 🎮 What's New

### 1. Player Page (`public/player.html`)
- Players join with Episode ID and Team Name
- See questions in real-time
- Buzz in with answers
- View live scoreboard
- Mobile-friendly interface

### 2. Host Page (`public/host.html`)
- Host controls the game
- Select and display questions
- See all player buzzes in real-time
- Reveal answers to everyone
- Award points quickly
- View live scoreboard

### 3. WebSocket Server (`websocket-server.php`)
- Handles real-time communication
- Manages episode rooms
- Routes messages between host and players
- Updates scores in database

## 🚀 How to Deploy on Render

### Step 1: Add Files to Your Repo

Copy these new files to your quizbattle folder:
```
quizbattle/
├── public/
│   ├── player.html  (NEW)
│   └── host.html    (NEW)
├── admin/
│   └── api/
│       └── get_questions.php  (NEW)
├── websocket-server.php  (NEW - updated)
└── composer.json  (UPDATE with Ratchet dependency)
```

### Step 2: Update composer.json

Make sure your composer.json includes:
```json
{
    "require": {
        "php": ">=7.4",
        "cboden/ratchet": "^0.4.4"
    }
}
```

### Step 3: Push to GitHub

```bash
git add .
git commit -m "Add player and host pages with WebSocket"
git push origin main
```

### Step 4: Create WebSocket Background Worker on Render

1. **Render Dashboard** → **New +** → **Background Worker**
2. Select your **quizbattle** repository
3. Configure:
   - **Name:** `quizbattle-ws`
   - **Region:** Same as your web service
   - **Branch:** `main`
   - **Build Command:** `composer install`
   - **Start Command:** `php websocket-server.php`
4. **Environment Variables:**
   - `DATABASE_URL` = [Your Internal Database URL]
   - `PORT` = `8080`
5. **Plan:** Starter ($7/month) - background workers require paid plan
6. Click **Create Background Worker**

### Step 5: Access Your New Pages

Once deployed:

**Player Page:**
```
https://quizbattle-xxxx.onrender.com/public/player.html
```

**Host Page:**
```
https://quizbattle-xxxx.onrender.com/public/host.html
```

## 📱 How to Use

### For Players:

1. Open `player.html` on phone/tablet
2. Enter Episode ID (get from admin panel)
3. Enter Team Name
4. Click "Join Game"
5. Wait for host to display question
6. Type answer and click "BUZZ IN!"
7. Watch your score update in real-time

### For Host:

1. Open `host.html` on computer/tablet
2. Enter Episode ID
3. Click "Start Hosting"
4. Select a question from dropdown
5. Click "📺 Display Question" - shows on all player screens
6. Watch buzzes come in from players
7. Click "👁️ Reveal Answer" - shows correct answer to everyone
8. Click "✓ Award X pts" on correct player's buzz
9. Repeat for next question!

## 💰 Cost on Render

**With WebSocket Background Worker:**
- Web Service: $7/month (Starter)
- Database: $7/month
- Background Worker: $7/month
- **Total: $21/month**

**Without WebSocket (HTTP Polling):**
- Web Service: $7/month (or Free with spindown)
- Database: $7/month
- **Total: $14/month**

## 🎯 Features

### Player Features:
- ✅ Join with team name
- ✅ See questions in real-time
- ✅ Buzz in with answer
- ✅ See when answer revealed
- ✅ Live scoreboard
- ✅ Mobile-responsive
- ✅ Auto-reconnect on disconnect

### Host Features:
- ✅ Select questions from database
- ✅ Preview before displaying
- ✅ Display to all players
- ✅ See all buzzes instantly
- ✅ Quick point awards
- ✅ Manual point adjustments
- ✅ Live scoreboard
- ✅ Player count tracking

## 🔧 Troubleshooting

### WebSocket Won't Connect

**Check:**
1. Background Worker is running on Render
2. PORT environment variable is set (8080)
3. DATABASE_URL is set correctly
4. Both services in same region

**Fix:**
- View Background Worker logs in Render
- Make sure it says "Quiz WebSocket server started on port 8080"

### Players Can't See Questions

**Check:**
1. WebSocket connected (green status bar)
2. Host clicked "Display Question"
3. Episode IDs match

**Fix:**
- Refresh player page
- Re-join with correct Episode ID

### Buzzes Not Showing Up

**Check:**
1. Host is connected to correct episode
2. WebSocket server is running

**Fix:**
- Check Background Worker logs
- Restart Background Worker

## 📖 Message Flow

```
Player buzzes answer
        ↓
WebSocket Server
        ↓
Host sees buzz
        ↓
Host awards points
        ↓
WebSocket Server
        ↓
Database updated
        ↓
All players see new scores
```

## 🎮 Game Flow Example

1. **Setup:**
   - Admin creates episode with teams
   - Players join with team names
   - Host joins with episode ID

2. **Question 1:**
   - Host selects question
   - Host clicks "Display Question"
   - All players see question
   - Players buzz in with answers
   - Host sees all buzzes
   - Host clicks "Reveal Answer"
   - All players see correct answer
   - Host awards points to correct team
   - All see updated scores

3. **Repeat** for more questions!

4. **Winner:**
   - Scoreboard shows rankings
   - Highest score wins!

## 🔒 Security Notes

- WebSocket connections are not authenticated (add if needed)
- Anyone with Episode ID can join as player
- Host page should be protected (add password if needed)
- Consider adding team passwords for competitive play

## 🚀 Next Steps

After deploying:

1. Test with 2-3 devices
2. Run a practice quiz
3. Adjust points configuration if needed
4. Add more questions
5. Go live with your quiz!

## 💡 Pro Tips

- Use tablet/laptop for host
- Share player URL via QR code
- Test WebSocket before live event
- Have backup plan (use admin panel if WebSocket fails)
- Keep questions loaded in advance

---

**Need help?** Check the main README or Render logs for errors.

**Enjoy your real-time quiz battles!** 🎉
