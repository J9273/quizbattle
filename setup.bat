@echo off
REM Quiz App WebSocket - Quick Setup Script for Windows
REM This script automates the installation process

echo ================================================
echo   Quiz Application - WebSocket Edition Setup
echo ================================================
echo.

REM Check if PHP is installed
echo [1/6] Checking PHP installation...
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP is not installed or not in PATH
    echo Please install PHP 7.4 or higher and add it to PATH
    pause
    exit /b 1
)
echo [OK] PHP found
echo.

REM Check if Composer is installed
echo [2/6] Checking Composer installation...
composer -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Composer is not installed
    echo Please install Composer from https://getcomposer.org/
    pause
    exit /b 1
)
echo [OK] Composer found
echo.

REM Install dependencies
echo [3/6] Installing PHP dependencies...
composer install --no-interaction --prefer-dist
if %errorlevel% neq 0 (
    echo [ERROR] Failed to install dependencies
    pause
    exit /b 1
)
echo [OK] Dependencies installed
echo.

REM Check if MySQL is running (XAMPP/WAMP)
echo [4/6] Checking MySQL/MariaDB...
mysql --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [WARNING] MySQL/MariaDB not found in PATH
    echo Make sure XAMPP or WAMP is running
) else (
    echo [OK] MySQL/MariaDB found
)
echo.

REM Create logs directory
echo [5/6] Creating logs directory...
if not exist "logs" mkdir logs
echo [OK] Logs directory created
echo.

REM Configuration reminder
echo [6/6] Configuration...
echo.
echo ================================================
echo   Setup Complete! Next Steps:
echo ================================================
echo.
echo 1. Edit includes\config.php with your database credentials
echo 2. Start XAMPP/WAMP (Apache + MySQL)
echo 3. Visit http://localhost/quiz-app-websocket/install.php
echo 4. Open a new command prompt and run: php websocket-server.php
echo 5. Login at http://localhost/quiz-app-websocket/admin/login.php
echo    Default credentials: admin / admin123
echo.

REM Optional: Start WebSocket server
set /p start="Do you want to start the WebSocket server now? (Y/N): "
if /i "%start%"=="Y" (
    echo.
    echo Starting WebSocket server...
    echo Press Ctrl+C to stop
    echo.
    php websocket-server.php
) else (
    echo.
    echo To start the WebSocket server later, run:
    echo   php websocket-server.php
    echo.
)

echo.
echo ================================================
echo           Setup Complete! 🎉
echo ================================================
pause
