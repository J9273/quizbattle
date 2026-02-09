<?php
/**
 * Database Configuration for Render (PostgreSQL)
 */

// ===== SESSION SETTINGS (MUST BE FIRST!) =====
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

// ===== DATABASE CONNECTION =====
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    $db = parse_url($database_url);
    
    define('DB_HOST', $db['host']);
    define('DB_USER', $db['user']);
    define('DB_PASS', $db['pass']);
    define('DB_NAME', ltrim($db['path'], '/'));
    define('DB_PORT', $db['port'] ?? 5432);
    define('DB_TYPE', 'pgsql');
} else {
    // Local development fallback
    define('DB_HOST', 'localhost');
    define('DB_USER', 'postgres');
    define('DB_PASS', 'SKckt79lkambNOiSbczDJovxb3rSzdvF');
    define('DB_NAME', 'quiz_battle');
    define('DB_PORT', 5432);
    define('DB_TYPE', 'pgsql');
}

// Create PDO connection
try {
    $dsn = DB_TYPE . ":host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $conn->exec("SET timezone = 'UTC'");
    
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please check server logs.");
}

// ===== APPLICATION SETTINGS =====
define('APP_NAME', 'Quiz Battle');
define('APP_ENV', $app_env);
define('BASE_URL', getenv('RENDER_EXTERNAL_URL') ?: 'http://localhost');