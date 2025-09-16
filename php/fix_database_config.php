<?php
// Database configuration checker and fixer
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/config_fix.log');

try {
    // Check current database configuration
    include 'db_config.php';
    
    $configStatus = [];
    
    // Test current connection
    try {
        $pdo = getPDOConnection();
        if ($pdo) {
            $configStatus['connection'] = 'SUCCESS';
            
            // Get current database name
            $stmt = $pdo->query("SELECT DATABASE()");
            $currentDb = $stmt->fetchColumn();
            $configStatus['current_database'] = $currentDb;
            
            // Check if it matches what we expect
            $configStatus['db_config_name'] = $db_name;
            $configStatus['names_match'] = ($currentDb === $db_name);
            
            // List all available databases for this user
            try {
                $stmt = $pdo->query("SHOW DATABASES");
                $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $configStatus['available_databases'] = $databases;
            } catch (Exception $e) {
                $configStatus['available_databases'] = 'CANNOT_LIST - ' . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $configStatus['connection'] = 'FAILED - ' . $e->getMessage();
    }
    
    // Check .env file
    $envFile = dirname(__DIR__) . '/.env';
    $configStatus['env_file_exists'] = file_exists($envFile);
    
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        $envLines = explode("\n", $envContent);
        $envConfig = [];
        
        foreach ($envLines as $line) {
            if (strpos($line, '=') !== false && !preg_match('/^#/', trim($line))) {
                list($key, $value) = explode('=', $line, 2);
                $envConfig[trim($key)] = trim($value);
            }
        }
        
        $configStatus['env_config'] = $envConfig;
        $configStatus['env_has_db_settings'] = isset($envConfig['DB_NAME']) && isset($envConfig['DB_USER']);
    }
    
    // Recommendations
    $recommendations = [];
    
    if (!isset($configStatus['names_match']) || !$configStatus['names_match']) {
        $recommendations[] = "Database name mismatch detected. Update your .env file with the correct database name.";
    }
    
    if ($configStatus['connection'] === 'SUCCESS') {
        $recommendations[] = "Database connection is working. Ready to install schema.";
    } else {
        $recommendations[] = "Fix database connection issues before proceeding.";
    }
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'config_status' => $configStatus,
        'recommendations' => $recommendations
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>