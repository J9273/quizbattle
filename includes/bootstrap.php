<?php
/**
 * Bootstrap - Loads FIRST before anything else
 * Configures PHP settings before sessions start
 */

// Session settings (BEFORE session_start anywhere)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

$app_env = getenv('APP_ENV') ?: 'development';

if ($app_env === 'production') {
    ini_set('session.cookie_secure', 1);
}

// Error reporting
if ($app_env === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}