<?php
/**
 * Installation Script for Render (PostgreSQL)
 * Run this once after deploying to create database tables
 */

require_once 'includes/config-render.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App Installation - Render</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Quiz App Installation</h1>
        
        <?php
        try {
            echo "<div class='mb-6'>";
            echo "<h2 class='text-xl font-semibold mb-4'>Creating Database Tables...</h2>";
            echo "<div class='space-y-2'>";
            
            // Create questions table
            echo "<p class='text-gray-600'>Creating questions table...</p>";
            $conn->exec("CREATE TABLE IF NOT EXISTS questions (
                id SERIAL PRIMARY KEY,
                question TEXT NOT NULL,
                theme VARCHAR(100) NOT NULL,
                level VARCHAR(20) NOT NULL CHECK (level IN ('easy', 'medium', 'hard')),
                answer TEXT NOT NULL,
                availability VARCHAR(20) DEFAULT 'available' CHECK (availability IN ('available', 'unavailable')),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<p class='text-green-600'>✓ Questions table created</p>";
            
            // Create quiz_episodes table
            echo "<p class='text-gray-600'>Creating quiz episodes table...</p>";
            $conn->exec("CREATE TABLE IF NOT EXISTS quiz_episodes (
                id SERIAL PRIMARY KEY,
                episode_name VARCHAR(200) NOT NULL,
                episode_date DATE NOT NULL,
                number_of_teams INTEGER NOT NULL,
                status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'completed', 'archived')),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<p class='text-green-600'>✓ Quiz episodes table created</p>";
            
            // Create teams table
            echo "<p class='text-gray-600'>Creating teams table...</p>";
            $conn->exec("CREATE TABLE IF NOT EXISTS teams (
                id SERIAL PRIMARY KEY,
                episode_id INTEGER REFERENCES quiz_episodes(id) ON DELETE CASCADE,
                team_name VARCHAR(200) NOT NULL,
                points INTEGER DEFAULT 0,
                position INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<p class='text-green-600'>✓ Teams table created</p>";
            
            // Create admin_users table
            echo "<p class='text-gray-600'>Creating admin users table...</p>";
            $conn->exec("CREATE TABLE IF NOT EXISTS admin_users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<p class='text-green-600'>✓ Admin users table created</p>";
            
            // Create points_config table
            echo "<p class='text-gray-600'>Creating points configuration table...</p>";
            $conn->exec("CREATE TABLE IF NOT EXISTS points_config (
                id SERIAL PRIMARY KEY,
                level VARCHAR(20) UNIQUE NOT NULL,
                points INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<p class='text-green-600'>✓ Points configuration table created</p>";
            
            echo "</div></div>";
            
            // Create default admin user
            echo "<div class='mb-6'>";
            echo "<h2 class='text-xl font-semibold mb-4'>Creating Default Admin User...</h2>";
            
            $username = 'admin';
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $email = 'admin@quiz-app.com';
            
            try {
                $stmt = $conn->prepare("INSERT INTO admin_users (username, password, email) 
                                       VALUES (?, ?, ?)
                                       ON CONFLICT (username) DO NOTHING");
                $stmt->execute([$username, $password, $email]);
                echo "<p class='text-green-600'>✓ Admin user created</p>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'duplicate') !== false) {
                    echo "<p class='text-yellow-600'>⚠ Admin user already exists</p>";
                } else {
                    throw $e;
                }
            }
            echo "</div>";
            
            // Create default points configuration
            echo "<div class='mb-6'>";
            echo "<h2 class='text-xl font-semibold mb-4'>Setting Up Points Configuration...</h2>";
            
            $points_config = [
                ['easy', 1],
                ['medium', 5],
                ['hard', 10]
            ];
            
            foreach ($points_config as $config) {
                try {
                    $stmt = $conn->prepare("INSERT INTO points_config (level, points) 
                                           VALUES (?, ?)
                                           ON CONFLICT (level) DO UPDATE SET points = EXCLUDED.points");
                    $stmt->execute($config);
                    echo "<p class='text-green-600'>✓ {$config[0]} level: {$config[1]} points</p>";
                } catch (PDOException $e) {
                    echo "<p class='text-yellow-600'>⚠ {$config[0]} level already configured</p>";
                }
            }
            echo "</div>";
            
            // Success message
            echo "<div class='bg-green-100 border-l-4 border-green-500 p-4 mb-6'>";
            echo "<h2 class='text-xl font-bold text-green-800 mb-2'>✅ Installation Successful!</h2>";
            echo "<p class='text-green-700'>All database tables have been created successfully.</p>";
            echo "</div>";
            
            // Login credentials
            echo "<div class='bg-blue-100 border-l-4 border-blue-500 p-4 mb-6'>";
            echo "<h3 class='font-bold text-blue-800 mb-2'>Default Login Credentials:</h3>";
            echo "<ul class='list-disc list-inside text-blue-700'>";
            echo "<li>Username: <strong>admin</strong></li>";
            echo "<li>Password: <strong>admin123</strong></li>";
            echo "</ul>";
            echo "</div>";
            
            // Warning
            echo "<div class='bg-red-100 border-l-4 border-red-500 p-4 mb-6'>";
            echo "<h3 class='font-bold text-red-800 mb-2'>⚠️ IMPORTANT SECURITY STEPS:</h3>";
            echo "<ol class='list-decimal list-inside text-red-700 space-y-1'>";
            echo "<li>Login and <strong>change the default password immediately</strong></li>";
            echo "<li>Delete this <code>install-render.php</code> file from your repository</li>";
            echo "<li>Push the changes to GitHub to redeploy without this file</li>";
            echo "</ol>";
            echo "</div>";
            
            // Next steps
            echo "<div class='bg-gray-100 p-4 rounded'>";
            echo "<h3 class='font-bold text-gray-800 mb-2'>Next Steps:</h3>";
            echo "<ol class='list-decimal list-inside text-gray-700 space-y-2'>";
            echo "<li>Click the button below to go to the login page</li>";
            echo "<li>Login with the default credentials above</li>";
            echo "<li>Change your password in the admin dashboard</li>";
            echo "<li>Start creating quiz episodes and questions!</li>";
            echo "</ol>";
            echo "</div>";
            
            echo "<div class='mt-6 flex gap-4'>";
            echo "<a href='/admin/login.php' class='bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg'>Go to Login Page →</a>";
            echo "</div>";
            
        } catch(PDOException $e) {
            echo "<div class='bg-red-100 border-l-4 border-red-500 p-4'>";
            echo "<h2 class='text-xl font-bold text-red-800 mb-2'>❌ Installation Failed</h2>";
            echo "<p class='text-red-700 mb-2'>An error occurred during installation:</p>";
            echo "<pre class='bg-red-200 p-3 rounded text-sm overflow-auto'>" . htmlspecialchars($e->getMessage()) . "</pre>";
            echo "<p class='text-red-700 mt-4'>Please check:</p>";
            echo "<ul class='list-disc list-inside text-red-700 mt-2'>";
            echo "<li>Database connection is working</li>";
            echo "<li>DATABASE_URL environment variable is set correctly</li>";
            echo "<li>PostgreSQL database exists and is accessible</li>";
            echo "</ul>";
            echo "</div>";
        }
        ?>
        
        <div class="mt-8 pt-6 border-t">
            <h3 class="font-bold text-gray-800 mb-2">Need Help?</h3>
            <ul class="text-sm text-gray-600 space-y-1">
                <li>• Check Render logs for errors</li>
                <li>• Verify DATABASE_URL is set in environment variables</li>
                <li>• See RENDER_DEPLOYMENT_GUIDE.md for troubleshooting</li>
            </ul>
        </div>
    </div>
</body>
</html>
