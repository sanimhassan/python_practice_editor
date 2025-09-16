<?php
/**
 * Database Configuration File
 * Contains connection parameters for the MySQL/MariaDB database
 */

// Enable error logging to a custom file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../my_custom_error_log.txt');

// Include environment loader
require_once __DIR__ . '/env_loader.php';

// Get database credentials from environment variables
$db_host = env('DB_HOST', 'localhost');
$db_name = env('DB_NAME', '');  // Will be loaded from .env
$db_user = env('DB_USER', '');  // Will be loaded from .env
$db_pass = env('DB_PASSWORD', '');  // Match actual .env variable name

// Log database configuration (without password)
error_log("DB Config loaded. Host: $db_host, DB: $db_name, User: $db_user");

// Create a function to get PDO connection
function getPDOConnection() {
    global $db_host, $db_name, $db_user, $db_pass;
    
    try {
        // First attempt - standard connection
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        error_log("Attempting DB connection with DSN: $dsn");
        
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        error_log("Database connection successful");
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB Connection Error: " . $e->getMessage());
        
        // If hostname includes port, try splitting them as a fallback
        if (strpos($db_host, ':') !== false) {
            try {
                $hostParts = explode(':', $db_host);
                $hostOnly = $hostParts[0];
                $portOnly = isset($hostParts[1]) ? $hostParts[1] : 3306;
                
                error_log("Attempting alternative connection with host: $hostOnly, port: $portOnly");
                $dsn = "mysql:host=$hostOnly;port=$portOnly;dbname=$db_name;charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                error_log("Alternative database connection successful");
                return $pdo;
            } catch (PDOException $e2) {
                error_log("Alternative DB Connection Error: " . $e2->getMessage());
            }
        }
        
        return false;
    }
}

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access forbidden');
}
?>