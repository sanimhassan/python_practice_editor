<?php
// Comprehensive diagnostic script for registration issues
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/diagnostic.log');

// Include database configuration
try {
    include 'db_config.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'step' => 'config_load', 'error' => $e->getMessage()]);
    exit;
}

$diagnostics = [];

// 1. Test database connection
try {
    $pdo = getPDOConnection();
    if ($pdo) {
        $diagnostics['database_connection'] = 'SUCCESS';
    } else {
        $diagnostics['database_connection'] = 'FAILED - getPDOConnection returned false';
    }
} catch (Exception $e) {
    $diagnostics['database_connection'] = 'FAILED - ' . $e->getMessage();
}

// 2. Check if users table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersExists = $stmt->rowCount() > 0;
    $diagnostics['users_table_exists'] = $usersExists ? 'YES' : 'NO';
    
    if ($usersExists) {
        // Check table structure
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $diagnostics['users_table_columns'] = array_column($columns, 'Field');
        
        // Check for required columns
        $requiredColumns = ['id', 'username', 'email', 'password_hash'];
        $missingColumns = array_diff($requiredColumns, $diagnostics['users_table_columns']);
        $diagnostics['missing_columns'] = $missingColumns;
    }
} catch (Exception $e) {
    $diagnostics['users_table_check'] = 'FAILED - ' . $e->getMessage();
}

// 3. Test basic INSERT operation (if table exists)
if (isset($diagnostics['users_table_exists']) && $diagnostics['users_table_exists'] === 'YES') {
    try {
        // Test if we can run a SELECT query
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
        $stmt->execute();
        $userCount = $stmt->fetchColumn();
        $diagnostics['current_user_count'] = $userCount;
        
        // Test if we can prepare an INSERT statement
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $diagnostics['insert_statement_prepare'] = 'SUCCESS';
        
    } catch (Exception $e) {
        $diagnostics['insert_test'] = 'FAILED - ' . $e->getMessage();
    }
}

// 4. Check all tables in database
try {
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $diagnostics['all_tables'] = $allTables;
} catch (Exception $e) {
    $diagnostics['show_tables'] = 'FAILED - ' . $e->getMessage();
}

// 5. Test actual registration logic with dummy data
if (isset($diagnostics['users_table_exists']) && $diagnostics['users_table_exists'] === 'YES') {
    try {
        $testUsername = 'test_' . uniqid();
        $testEmail = 'test_' . uniqid() . '@example.com';
        $testPassword = password_hash('testpass123', PASSWORD_DEFAULT);
        
        // Check if username exists (this is what registration does first)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$testUsername, $testEmail]);
        $existingUser = $stmt->rowCount() > 0;
        $diagnostics['duplicate_check_test'] = $existingUser ? 'DUPLICATE FOUND' : 'NO DUPLICATE';
        
        if (!$existingUser) {
            // Try the actual INSERT
            $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
            $result = $stmt->execute([$testUsername, $testEmail, $testPassword]);
            
            if ($result) {
                $userId = $pdo->lastInsertId();
                $diagnostics['test_registration'] = 'SUCCESS - User ID: ' . $userId;
                
                // Clean up test user
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $diagnostics['test_cleanup'] = 'SUCCESS';
            } else {
                $diagnostics['test_registration'] = 'FAILED - INSERT returned false';
            }
        }
        
    } catch (Exception $e) {
        $diagnostics['test_registration'] = 'FAILED - ' . $e->getMessage();
    }
}

// 6. Check environment and PHP settings
$diagnostics['php_version'] = phpversion();
$diagnostics['pdo_available'] = extension_loaded('pdo') ? 'YES' : 'NO';
$diagnostics['pdo_mysql_available'] = extension_loaded('pdo_mysql') ? 'YES' : 'NO';

// 7. Check .env file
$envFile = dirname(__DIR__) . '/.env';
$diagnostics['env_file_exists'] = file_exists($envFile) ? 'YES' : 'NO';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    $diagnostics['env_has_db_config'] = (
        strpos($envContent, 'DB_HOST') !== false &&
        strpos($envContent, 'DB_NAME') !== false &&
        strpos($envContent, 'DB_USER') !== false &&
        strpos($envContent, 'DB_PASSWORD') !== false
    ) ? 'YES' : 'NO';
}

// 8. Check error logs for recent registration attempts
$errorLogFile = __DIR__ . '/php_errors.log';
if (file_exists($errorLogFile)) {
    $recentLogs = tail($errorLogFile, 20);
    $diagnostics['recent_error_logs'] = array_filter(explode("\n", $recentLogs));
}

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'diagnostics' => $diagnostics
]);

// Helper function to read last N lines of a file
function tail($filename, $lines = 10) {
    $file = file($filename);
    return implode("\n", array_slice($file, -$lines));
}
?>