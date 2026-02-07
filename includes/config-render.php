<?php
/**
 * Database Configuration for Render (PostgreSQL)
 * This replaces the MySQL connection in the original app
 */

// Get database URL from environment (Render sets this automatically)
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Parse DATABASE_URL: postgresql://quizdb_f20w_user:SKckt79lkambNOiSbczDJovxb3rSzdvF@dpg-d63s0hchg0os73confcg-a/quizdb_f20w
    $db = parse_url($database_url);
    
    define('DB_HOST', $db['host']);
    define('DB_USER', $db['user']);
    define('DB_PASS', $db['pass']);
    define('DB_NAME', ltrim($db['path'], '/'));
    define('DB_PORT', $db['port'] ?? 5432);
    define('DB_TYPE', 'pgsql');
} else {
    // Local development fallback
    define('DB_HOST', 'dpg-d63s0hchg0os73confcg-a');
    define('DB_USER', 'quizdb_f20w_user');
    define('DB_PASS', 'quizdb_f20w_user');
    define('DB_NAME', 'quizdb_f20w');
    define('DB_PORT', 5432);
    define('DB_TYPE', 'pgsql');
}

// Create PDO connection (PostgreSQL)
try {
    $dsn = DB_TYPE . ":host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Set timezone
    $conn->exec("SET timezone = 'UTC'");
    
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please check server logs.");
}

// Application settings
define('APP_NAME', 'Quiz Application');
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('BASE_URL', getenv('RENDER_EXTERNAL_URL') ?: 'http://localhost');

// WebSocket settings
define('WS_HOST', getenv('WEBSOCKET_HOST') ?: 'localhost');
define('WS_PORT', getenv('WEBSOCKET_PORT') ?: 8080);

// For internal Render services, use service name
if (APP_ENV === 'production' && getenv('RENDER_SERVICE_NAME')) {
    define('WS_URL', 'ws://quiz-app-websocket:' . WS_PORT);
} else {
    define('WS_URL', 'ws://' . WS_HOST . ':' . WS_PORT);
}

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (APP_ENV === 'production') {
    ini_set('session.cookie_secure', 1); // HTTPS only
}

// Error reporting
if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
