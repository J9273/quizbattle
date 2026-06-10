<?php
/**
 * Database Configuration for Render (PostgreSQL)
 */

// Load bootstrap first (only if not already loaded)
if (!defined('BOOTSTRAP_LOADED')) {
    require_once __DIR__ . '/bootstrap.php';
    define('BOOTSTRAP_LOADED', true);
}

// ===== DATABASE CONNECTION =====
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    $db = parse_url($database_url);
    
    define('DB_HOST', $db['host']);
    define('DB_USER', $db['user']);
    define('DB_PASS', $db['pass']);
    define('DB_NAME', ltrim($db['path'], '/'));
    define('DB_PORT', $db['port'] ?? 3306);
    define('DB_TYPE', 'mysql');
} else {
    // Local development fallback
    define('DB_HOST', 'mysql8.unoeuro.com');
    define('DB_USER', 'drawbridge_dk');
    define('DB_PASS', '9gbnx61c94');
    define('DB_NAME', 'drawbridge_dk');
    define('DB_PORT', 3306);
    define('DB_TYPE', 'mysql');
}

// Create PDO connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    
    $conn->exec("SET timezone = 'UTC'");
    
} catch(PDOException $e) {
 //   error_log("Database connection failed: " . $e->getMessage());
 //   die("Connection failed. Please check server logs.");
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage()); // ← temporary, remove after fixing!

}

// ===== APPLICATION SETTINGS =====
define('APP_NAME', 'Quiz Battle');
define('APP_ENV', $app_env);
define('BASE_URL', getenv('RENDER_EXTERNAL_URL') ?: 'http://localhost');
