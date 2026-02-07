# Production Deployment Guide

Complete guide for deploying the Quiz Application WebSocket edition to a production server.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Setup](#server-setup)
3. [Application Installation](#application-installation)
4. [WebSocket Server Setup](#websocket-server-setup)
5. [Web Server Configuration](#web-server-configuration)
6. [SSL/TLS Setup](#ssltls-setup)
7. [Process Management](#process-management)
8. [Monitoring](#monitoring)
9. [Backup](#backup)
10. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Server Requirements

**Minimum:**
- 1 CPU core
- 1 GB RAM
- 10 GB storage
- Ubuntu 20.04+ / Debian 10+ / CentOS 8+

**Recommended:**
- 2+ CPU cores
- 2+ GB RAM
- 20+ GB storage
- Ubuntu 22.04 LTS

### Software Requirements

- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Nginx or Apache
- Composer
- Supervisor or systemd
- Git (optional)

---

## Server Setup

### 1. Update System

```bash
sudo apt update
sudo apt upgrade -y
```

### 2. Install PHP and Extensions

```bash
# PHP and common extensions
sudo apt install -y php8.1 php8.1-fpm php8.1-cli php8.1-mysql \
    php8.1-mbstring php8.1-xml php8.1-curl php8.1-zip php8.1-gd

# Enable PHP-FPM
sudo systemctl enable php8.1-fpm
sudo systemctl start php8.1-fpm
```

### 3. Install MySQL/MariaDB

```bash
# Install MySQL
sudo apt install -y mysql-server

# Secure installation
sudo mysql_secure_installation

# Or install MariaDB
sudo apt install -y mariadb-server
sudo mysql_secure_installation
```

### 4. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### 5. Install Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

### 6. Install Process Manager

**Option A: Supervisor**
```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

**Option B: systemd** (already installed)

---

## Application Installation

### 1. Create Application Directory

```bash
# Create directory
sudo mkdir -p /var/www/quiz-app
cd /var/www/quiz-app

# Set ownership
sudo chown -R www-data:www-data /var/www/quiz-app
```

### 2. Upload Application Files

**Option A: Using Git**
```bash
sudo -u www-data git clone https://github.com/yourrepo/quiz-app-websocket.git .
```

**Option B: Using SCP**
```bash
# From your local machine
scp -r quiz-app-websocket/* user@server:/var/www/quiz-app/
```

**Option C: Using FTP/SFTP**
Upload files using FileZilla or similar tool.

### 3. Install Dependencies

```bash
cd /var/www/quiz-app
sudo -u www-data composer install --no-dev --optimize-autoloader
```

### 4. Set Permissions

```bash
# Application files
sudo chown -R www-data:www-data /var/www/quiz-app
sudo chmod -R 755 /var/www/quiz-app

# Writable directories
sudo chmod -R 775 /var/www/quiz-app/logs
sudo chmod 644 /var/www/quiz-app/includes/config.php
```

### 5. Configure Database

Edit `includes/config.php`:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'quiz_user');
define('DB_PASS', 'strong_password_here');
define('DB_NAME', 'quiz_production');
```

### 6. Create Database and User

```bash
# Login to MySQL
sudo mysql -u root -p

# Create database and user
CREATE DATABASE quiz_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'quiz_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON quiz_production.* TO 'quiz_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 7. Run Installation

```bash
# Via command line
php install.php

# Or visit in browser (temporarily allow in nginx)
https://yourdomain.com/install.php

# Delete install.php after installation
rm /var/www/quiz-app/install.php
```

---

## WebSocket Server Setup

### 1. Create Logs Directory

```bash
sudo mkdir -p /var/log/quiz-websocket
sudo chown www-data:www-data /var/log/quiz-websocket
```

### 2. Test WebSocket Server

```bash
cd /var/www/quiz-app
sudo -u www-data php websocket-server.php
```

Press Ctrl+C to stop after verifying it starts.

### 3. Configure Firewall

```bash
# Allow WebSocket port
sudo ufw allow 8080/tcp

# Or for nginx reverse proxy only
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

---

## Web Server Configuration

### Nginx Configuration

#### 1. Create Site Configuration

```bash
sudo nano /etc/nginx/sites-available/quiz-app
```

Copy contents from `nginx-quiz-websocket.conf` (provided in repo).

#### 2. Enable Site

```bash
# Create symlink
sudo ln -s /etc/nginx/sites-available/quiz-app /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

#### 3. Configure PHP-FPM Pool (Optional)

```bash
# Create dedicated pool
sudo nano /etc/php/8.1/fpm/pool.d/quiz-app.conf
```

Add:
```ini
[quiz-app]
user = www-data
group = www-data
listen = /run/php/php8.1-fpm-quiz.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

Reload PHP-FPM:
```bash
sudo systemctl reload php8.1-fpm
```

---

## SSL/TLS Setup

### Using Let's Encrypt (Recommended)

#### 1. Install Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
```

#### 2. Obtain Certificate

```bash
sudo certbot --nginx -d quiz.yourdomain.com
```

Follow prompts to configure HTTPS.

#### 3. Auto-Renewal

Certbot automatically sets up renewal. Test it:

```bash
sudo certbot renew --dry-run
```

### Manual SSL Certificate

If you have your own certificate:

```bash
# Copy certificate files
sudo mkdir -p /etc/nginx/ssl
sudo cp fullchain.pem /etc/nginx/ssl/quiz-app.crt
sudo cp privkey.pem /etc/nginx/ssl/quiz-app.key
sudo chmod 600 /etc/nginx/ssl/quiz-app.key
```

Update nginx configuration:
```nginx
ssl_certificate /etc/nginx/ssl/quiz-app.crt;
ssl_certificate_key /etc/nginx/ssl/quiz-app.key;
```

---

## Process Management

### Option A: Using Supervisor

#### 1. Copy Configuration

```bash
sudo cp supervisor-quiz-websocket.conf /etc/supervisor/conf.d/quiz-websocket.conf
```

#### 2. Update Configuration

```bash
sudo nano /etc/supervisor/conf.d/quiz-websocket.conf
```

Verify paths match your installation.

#### 3. Start Service

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Start WebSocket server
sudo supervisorctl start quiz-websocket

# Check status
sudo supervisorctl status quiz-websocket
```

#### 4. Manage Service

```bash
# Start
sudo supervisorctl start quiz-websocket

# Stop
sudo supervisorctl stop quiz-websocket

# Restart
sudo supervisorctl restart quiz-websocket

# View logs
sudo supervisorctl tail -f quiz-websocket
```

### Option B: Using systemd

#### 1. Copy Service File

```bash
sudo cp quiz-websocket.service /etc/systemd/system/
```

#### 2. Update Paths

```bash
sudo nano /etc/systemd/system/quiz-websocket.service
```

#### 3. Start Service

```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable auto-start
sudo systemctl enable quiz-websocket

# Start service
sudo systemctl start quiz-websocket

# Check status
sudo systemctl status quiz-websocket
```

#### 4. Manage Service

```bash
# Start
sudo systemctl start quiz-websocket

# Stop
sudo systemctl stop quiz-websocket

# Restart
sudo systemctl restart quiz-websocket

# View logs
sudo journalctl -u quiz-websocket -f
```

---

## Monitoring

### 1. Server Logs

```bash
# Nginx access log
sudo tail -f /var/log/nginx/quiz-access.log

# Nginx error log
sudo tail -f /var/log/nginx/quiz-error.log

# WebSocket logs
sudo tail -f /var/log/quiz-websocket/output.log
sudo tail -f /var/log/quiz-websocket/error.log

# PHP-FPM logs
sudo tail -f /var/log/php8.1-fpm.log
```

### 2. System Resources

```bash
# CPU and memory
htop

# Disk usage
df -h

# Network connections
ss -tulpn | grep 8080

# Process list
ps aux | grep php
```

### 3. Database Monitoring

```bash
# Login to MySQL
sudo mysql -u root -p

# Show processlist
SHOW PROCESSLIST;

# Check slow queries
SHOW VARIABLES LIKE 'slow_query%';

# Database size
SELECT table_schema AS 'Database',
       ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'quiz_production'
GROUP BY table_schema;
```

### 4. Application Health Check

Create health check script:

```bash
# /usr/local/bin/quiz-health-check.sh
#!/bin/bash

# Check WebSocket server
if ! pgrep -f "websocket-server.php" > /dev/null; then
    echo "WebSocket server is DOWN"
    sudo systemctl restart quiz-websocket
    # Send alert email
    echo "WebSocket server restarted" | mail -s "Quiz App Alert" admin@example.com
fi

# Check database
if ! mysqladmin ping -h localhost > /dev/null 2>&1; then
    echo "Database is DOWN"
    # Send alert email
    echo "Database is down" | mail -s "Quiz App CRITICAL" admin@example.com
fi
```

Add to crontab:
```bash
sudo crontab -e

# Run every 5 minutes
*/5 * * * * /usr/local/bin/quiz-health-check.sh
```

---

## Backup

### 1. Database Backup

Create backup script:

```bash
# /usr/local/bin/quiz-backup.sh
#!/bin/bash

BACKUP_DIR="/var/backups/quiz-app"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="quiz_production"
DB_USER="quiz_user"
DB_PASS="your_password"

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup application files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/quiz-app

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $DATE"
```

Make executable and schedule:
```bash
sudo chmod +x /usr/local/bin/quiz-backup.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
0 2 * * * /usr/local/bin/quiz-backup.sh
```

### 2. Restore from Backup

```bash
# Restore database
gunzip < /var/backups/quiz-app/db_20240101_020000.sql.gz | mysql -u quiz_user -p quiz_production

# Restore files
tar -xzf /var/backups/quiz-app/files_20240101_020000.tar.gz -C /
```

---

## Security Hardening

### 1. Update Regularly

```bash
# Set up automatic updates
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

### 2. Firewall

```bash
# Enable UFW
sudo ufw enable

# Allow only necessary ports
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Check status
sudo ufw status
```

### 3. Fail2Ban

```bash
# Install
sudo apt install -y fail2ban

# Configure
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
sudo nano /etc/fail2ban/jail.local

# Enable and start
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 4. File Permissions

```bash
# Secure sensitive files
sudo chmod 600 /var/www/quiz-app/includes/config.php
sudo chmod 600 /etc/nginx/ssl/quiz-app.key

# Prevent execution in upload directories
sudo chmod 755 /var/www/quiz-app
sudo find /var/www/quiz-app -type f -exec chmod 644 {} \;
sudo find /var/www/quiz-app -type d -exec chmod 755 {} \;
```

---

## Troubleshooting

### WebSocket Server Won't Start

**Check logs:**
```bash
sudo journalctl -u quiz-websocket -n 50
```

**Common issues:**
- Port 8080 already in use
- PHP socket extension missing
- Database connection failed
- File permission errors

**Solutions:**
```bash
# Kill process on port 8080
sudo lsof -ti:8080 | xargs kill -9

# Install socket extension
sudo apt install php8.1-sockets

# Check database connection
php -r "new mysqli('localhost', 'quiz_user', 'password', 'quiz_production');"
```

### Nginx Errors

**Check configuration:**
```bash
sudo nginx -t
```

**Common issues:**
- Syntax errors in config
- PHP-FPM socket not found
- Certificate errors

**View error log:**
```bash
sudo tail -f /var/log/nginx/error.log
```

### Database Connection Issues

**Test connection:**
```bash
mysql -u quiz_user -p quiz_production
```

**Check user privileges:**
```sql
SHOW GRANTS FOR 'quiz_user'@'localhost';
```

### Performance Issues

**Check resource usage:**
```bash
htop
iostat
```

**Optimize MySQL:**
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Add tuning parameters
innodb_buffer_pool_size = 256M
max_connections = 100
query_cache_size = 32M
```

**Restart MySQL:**
```bash
sudo systemctl restart mysql
```

---

## Maintenance

### Regular Tasks

**Weekly:**
- Review logs for errors
- Check disk space
- Verify backups are working

**Monthly:**
- Update system packages
- Review security patches
- Check SSL certificate expiry
- Database optimization

**Quarterly:**
- Review performance metrics
- Update documentation
- Test backup restoration
- Security audit

### Update Application

```bash
# Backup first
/usr/local/bin/quiz-backup.sh

# Pull latest code
cd /var/www/quiz-app
sudo -u www-data git pull

# Update dependencies
sudo -u www-data composer install --no-dev

# Restart services
sudo systemctl restart quiz-websocket
sudo systemctl reload nginx
```

---

## Support

For production issues:

1. Check logs first
2. Review this documentation
3. Search issue tracker
4. Contact support team

**Emergency Contacts:**
- System Admin: admin@example.com
- Database Admin: dba@example.com
- On-call: +1-XXX-XXX-XXXX

---

**Document Version:** 1.0  
**Last Updated:** 2024  
**Applies To:** Quiz App WebSocket Edition v2.0.0
