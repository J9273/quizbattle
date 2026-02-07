# 🚀 Render Deployment - Quick Start Guide

**FASTEST PATH:** Deploy your Quiz App to Render in ~15 minutes

## ⚡ What You Need

- ✅ Render account (free - sign up with GitHub)
- ✅ GitHub account (free)
- ✅ Credit card (for Render verification - free tier available)
- ✅ This code package

## 📊 Pricing (You Choose)

### Option 1: FREE TIER (Testing)
- Web Service: **$0/month** (spins down after 15 min)
- PostgreSQL: **$0** for 90 days, then **$7/month**
- **Total: FREE** (then $7/month)
- ⚠️ Limitations: Spins down, no WebSocket, slower

### Option 2: PAID TIER (Recommended for Production)
- Web Service: **$7/month** (always on)
- PostgreSQL: **$7/month**
- Background Worker (WebSocket): **$7/month**
- **Total: $21/month**
- ✅ Always online, real-time WebSocket, fast

## 🎯 Choose Your Path

### Path A: FREE TIER (HTTP Polling - No WebSocket)
**Best for:** Testing, low-traffic, budget-conscious
**Features:** Near-real-time via polling (updates every 2-3 seconds)
👉 **Follow: FREE TIER STEPS** below

### Path B: PAID TIER (Full WebSocket)
**Best for:** Production, high-traffic, true real-time
**Features:** True real-time with WebSocket
👉 **Follow: PAID TIER STEPS** below

---

# 🆓 FREE TIER STEPS (HTTP Polling)

## STEP 1: Prepare Code (5 minutes)

1. **Extract this package** to a folder on your computer

2. **Install Git** (if not installed)
   - Windows: https://git-scm.com/download/win
   - Mac: Comes pre-installed
   - Linux: `sudo apt install git`

3. **Open terminal/command prompt** in the extracted folder

## STEP 2: Push to GitHub (3 minutes)

1. **Create GitHub repository:**
   - Go to https://github.com/new
   - Name: `quiz-app-render`
   - Make it Public
   - Click "Create repository"

2. **Push code to GitHub:**
   ```bash
   git init
   git add .
   git commit -m "Initial commit for Render deployment"
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/quiz-app-render.git
   git push -u origin main
   ```
   
   Replace `YOUR_USERNAME` with your GitHub username.

## STEP 3: Create Render Account (2 minutes)

1. Go to https://render.com
2. Click "Get Started"
3. Sign up with GitHub (easiest)
4. Verify email
5. Add payment method (required but won't be charged on free tier)

## STEP 4: Create PostgreSQL Database (2 minutes)

1. Render Dashboard → Click "New +" → "PostgreSQL"
2. Settings:
   - Name: `quiz-app-db`
   - Database: `quiz_production`
   - Region: Choose closest to you
   - Plan: **Free**
3. Click "Create Database"
4. **SAVE THIS:** Copy the "Internal Database URL" (you'll need it)

## STEP 5: Deploy Web Service (3 minutes)

1. Render Dashboard → Click "New +" → "Web Service"
2. Connect your GitHub repository
3. Settings:
   - Name: `quiz-app`
   - Region: Same as database
   - Branch: `main`
   - Root Directory: (leave empty)
   - Runtime: **PHP**
   - Build Command: `composer install --no-dev`
   - Start Command: `php -S 0.0.0.0:$PORT -t .`
4. Click "Advanced" → Add Environment Variable:
   - Key: `DATABASE_URL`
   - Value: [Paste Internal Database URL from Step 4]
5. Plan: **Free**
6. Click "Create Web Service"

Wait ~5 minutes for deployment.

## STEP 6: Run Installation

1. When deployment finishes, Render shows your URL: `https://quiz-app-xxxx.onrender.com`
2. Visit: `https://quiz-app-xxxx.onrender.com/install-render.php`
3. Wait for green "Installation Successful" message
4. **Click "Go to Login Page"**

## STEP 7: Login & Setup

1. Login with:
   - Username: `admin`
   - Password: `admin123`
2. **IMMEDIATELY CHANGE PASSWORD:**
   - Click your username → Change Password
3. **Delete install file:**
   ```bash
   # In your local folder
   git rm install-render.php
   git commit -m "Remove install file"
   git push origin main
   ```

## STEP 8: Start Using!

1. Create episode: Episodes → Create Episode
2. Add questions: Questions → Add Question
3. Start quiz: Use Scoring Mode (polling-based)

**That's it! Your quiz app is live on Render! 🎉**

---

# 💰 PAID TIER STEPS (Full WebSocket)

Follow FREE TIER STEPS 1-6, then:

## STEP 7: Create WebSocket Background Worker

1. Render Dashboard → "New +" → "Background Worker"
2. Connect your repository
3. Settings:
   - Name: `quiz-app-websocket`
   - Region: Same as others
   - Branch: `main`
   - Build Command: `composer install --no-dev`
   - Start Command: `php websocket-server.php`
4. Environment Variable:
   - Key: `DATABASE_URL`
   - Value: [Same Internal Database URL]
5. Plan: **Starter ($7/month)**
6. Click "Create Background Worker"

## STEP 8: Update Web Service Plan

1. Go to your `quiz-app` Web Service
2. Settings → Change Plan to **Starter ($7/month)**
3. This keeps it always online (no spin down)

## STEP 9: Complete Setup

Follow FREE TIER Steps 6-8 (Installation, Login, Setup)

**Now you have full real-time WebSocket! 🚀**

---

# 🔧 Configuration Comparison

## Files You Need to Edit

### For FREE TIER (Polling):
**No edits needed!** Everything is pre-configured.

The app automatically:
- Uses `includes/config-render.php` (PostgreSQL connection)
- Uses `js/polling-client.js` (HTTP polling)
- Polls for updates every 2 seconds

### For PAID TIER (WebSocket):
**No edits needed either!** Works out of the box.

The app automatically:
- Detects Render environment
- Uses internal WebSocket URL: `ws://quiz-app-websocket:8080`
- Full real-time updates

---

# 📝 Troubleshooting

## Issue: "Build Failed"

**Solution:**
1. Check `composer.json` exists in root
2. Verify PHP version in Render settings
3. Check build logs in Render dashboard

## Issue: "Database Connection Failed"

**Solution:**
1. Verify `DATABASE_URL` is set correctly
2. Use **Internal** Database URL (not External)
3. Check database is running in Render dashboard

## Issue: "Slow First Load" (Free Tier)

**Cause:** Service spins down after 15 minutes.

**Solutions:**
1. Accept 30-60 second wait time (free tier limitation)
2. Upgrade to Starter plan ($7/month) for always-on
3. Use uptime monitor to ping every 10 min (keeps it awake)

## Issue: "Can't Access Install Page"

**Solution:**
1. Wait for deployment to finish (check Render dashboard)
2. Make sure URL ends with `/install-render.php`
3. Check logs for errors

---

# 🎯 What's Next?

After deployment:

1. ✅ **Change password** (critical!)
2. ✅ **Delete install file** from GitHub
3. ✅ **Add questions** (manually or CSV)
4. ✅ **Create first episode**
5. ✅ **Test quiz** with teams
6. ✅ **Share URL** with participants

## Free Tier Limitations & Solutions

| Limitation | Impact | Solution |
|------------|--------|----------|
| Spins down after 15 min | 30-60s delay on first request | Upgrade to Starter or use uptime monitor |
| No background workers | No WebSocket support | Use polling (included!) or upgrade |
| 90-day database trial | Must pay after 90 days | Budget $7/month for database |

## Upgrade Path

**Start Free → See if you like it → Upgrade if needed**

Free → Starter ($14/month): Always-on, better UX
Starter → Starter + Worker ($21/month): Add WebSocket

---

# 🔗 Useful Render URLs

After deployment, bookmark these:

- **Dashboard:** https://dashboard.render.com
- **Your App:** `https://quiz-app-xxxx.onrender.com`
- **Database:** Render Dashboard → PostgreSQL service
- **Logs:** Service → Logs tab (real-time)
- **Metrics:** Service → Metrics tab

---

# 💡 Pro Tips

1. **Set up custom domain** (free on all plans)
   - Service → Settings → Custom Domains
   - Add your domain, follow DNS instructions
   - Free SSL included!

2. **Enable auto-deploy from GitHub**
   - Already enabled by default
   - Push to GitHub = automatic deploy

3. **Monitor uptime** (free tier workaround)
   - Use UptimeRobot (free): https://uptimerobot.com
   - Ping your app every 10 minutes
   - Keeps it from spinning down

4. **Backup database regularly**
   - Render has automatic backups
   - But also download manually: Settings → Backups

5. **Check logs often**
   - Logs tab shows errors in real-time
   - Download for analysis

---

# 📞 Need Help?

1. **Read full guide:** `RENDER_DEPLOYMENT_GUIDE.md`
2. **Check Render docs:** https://render.com/docs
3. **View logs:** Render Dashboard → Service → Logs
4. **Common issues:** See Troubleshooting section above

---

# ✅ Quick Checklist

Before going live:

- [ ] Deployed to Render successfully
- [ ] Database created and connected
- [ ] Ran install-render.php
- [ ] Changed default password
- [ ] Deleted install-render.php
- [ ] Added test questions
- [ ] Created test episode
- [ ] Tested scoring
- [ ] Set up custom domain (optional)
- [ ] Configured backups

---

**Estimated Total Time:** 
- Free Tier: 15 minutes
- Paid Tier: 20 minutes

**Cost:**
- Free Tier: $0 (90 days), then $7/month
- Paid Tier: $14-21/month

**Result:** Professional quiz app live on the internet! 🎉

---

Need the full step-by-step? See `RENDER_DEPLOYMENT_GUIDE.md`

Want to deploy elsewhere? See `DEPLOYMENT.md` for other options.

Have questions? Check the troubleshooting sections!
