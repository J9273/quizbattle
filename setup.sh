#!/bin/bash

# Quiz App WebSocket - Quick Setup Script
# This script automates the installation process

echo "╔════════════════════════════════════════════════╗"
echo "║  Quiz Application - WebSocket Edition Setup   ║"
echo "╚════════════════════════════════════════════════╝"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if PHP is installed
echo -e "${YELLOW}[1/6] Checking PHP installation...${NC}"
if ! command -v php &> /dev/null; then
    echo -e "${RED}✗ PHP is not installed. Please install PHP 7.4 or higher.${NC}"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo -e "${GREEN}✓ PHP $PHP_VERSION found${NC}"

# Check if Composer is installed
echo -e "${YELLOW}[2/6] Checking Composer installation...${NC}"
if ! command -v composer &> /dev/null; then
    echo -e "${RED}✗ Composer is not installed.${NC}"
    echo "Installing Composer..."
    
    # Download and install Composer
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --quiet
    rm composer-setup.php
    
    # Move to local bin
    sudo mv composer.phar /usr/local/bin/composer
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Composer installed successfully${NC}"
    else
        echo -e "${RED}✗ Failed to install Composer. Please install manually.${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ Composer found${NC}"
fi

# Install dependencies
echo -e "${YELLOW}[3/6] Installing PHP dependencies...${NC}"
composer install --no-interaction --prefer-dist

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Dependencies installed successfully${NC}"
else
    echo -e "${RED}✗ Failed to install dependencies${NC}"
    exit 1
fi

# Check if MySQL is running
echo -e "${YELLOW}[4/6] Checking MySQL/MariaDB...${NC}"
if command -v mysql &> /dev/null; then
    echo -e "${GREEN}✓ MySQL/MariaDB found${NC}"
else
    echo -e "${YELLOW}⚠ MySQL/MariaDB not found in PATH. Make sure it's installed and running.${NC}"
fi

# Create logs directory
echo -e "${YELLOW}[5/6] Creating logs directory...${NC}"
mkdir -p logs
chmod 755 logs
echo -e "${GREEN}✓ Logs directory created${NC}"

# Configuration reminder
echo -e "${YELLOW}[6/6] Configuration...${NC}"
echo ""
echo "Please complete the following steps:"
echo ""
echo "1. Edit includes/config.php with your database credentials"
echo "2. Visit http://localhost/quiz-app-websocket/install.php in your browser"
echo "3. Start the WebSocket server: php websocket-server.php"
echo "4. Login at http://localhost/quiz-app-websocket/admin/login.php"
echo "   Default credentials: admin / admin123"
echo ""

# Optional: Start WebSocket server
echo -e "${YELLOW}Do you want to start the WebSocket server now? (y/n)${NC}"
read -r response

if [[ "$response" =~ ^[Yy]$ ]]; then
    echo ""
    echo -e "${GREEN}Starting WebSocket server...${NC}"
    echo "Press Ctrl+C to stop"
    echo ""
    php websocket-server.php
else
    echo ""
    echo -e "${YELLOW}Setup complete! To start the WebSocket server later, run:${NC}"
    echo "  php websocket-server.php"
    echo ""
fi

echo -e "${GREEN}╔════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           Setup Complete! 🎉                   ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════╝${NC}"
