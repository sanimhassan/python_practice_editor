<?php
// Direct schema installer - manually creates required tables based on project specs
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/direct_install.log');

try {
    // Include database configuration
    include 'db_config.php';
    
    $pdo = getPDOConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Define the exact SQL statements based on your project specifications
    $sqlStatements = [
        // Users table for authentication
        'users_table' => "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )",
        
        // User progress tracking
        'user_progress_table' => "CREATE TABLE IF NOT EXISTS user_progress (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            code_snippet TEXT,
            exercise_name VARCHAR(100),
            is_completed BOOLEAN DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        // User code saves
        'saved_code_table' => "CREATE TABLE IF NOT EXISTS saved_code (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(100) NOT NULL,
            code_content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        // Indexes for performance
        'user_progress_index' => "CREATE INDEX IF NOT EXISTS idx_user_progress ON user_progress(user_id)",
        'saved_code_index' => "CREATE INDEX IF NOT EXISTS idx_saved_code ON saved_code(user_id)"
    ];
    
    $results = [];
    $successCount = 0;
    
    foreach ($sqlStatements as $name => $sql) {
        try {
            error_log("Executing: $name");
            $pdo->exec($sql);
            $results[] = [
                'name' => $name,
                'status' => 'success',
                'query' => substr($sql, 0, 100) . '...'
            ];
            $successCount++;
            error_log("Success: $name");
        } catch (PDOException $e) {
            error_log("Error in $name: " . $e->getMessage());
            $results[] = [
                'name' => $name,
                'status' => 'error',
                'message' => $e->getMessage(),
                'query' => substr($sql, 0, 100) . '...'
            ];
        }
    }
    
    // Verify tables were created
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Check specifically for required tables
    $requiredTables = ['users', 'user_progress', 'saved_code'];
    $missingTables = array_diff($requiredTables, $tables);
    
    // Test basic functionality
    $functionalityTests = [];
    if (in_array('users', $tables)) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = $stmt->fetchColumn();
            $functionalityTests['users_table_readable'] = true;
            $functionalityTests['user_count'] = $userCount;
        } catch (Exception $e) {
            $functionalityTests['users_table_readable'] = false;
            $functionalityTests['error'] = $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => $successCount >= 3 && empty($missingTables), // At least the 3 tables should be created
        'message' => "Executed $successCount out of " . count($sqlStatements) . " statements successfully",
        'results' => $results,
        'tables_created' => $tables,
        'required_tables_status' => [
            'users' => in_array('users', $tables),
            'user_progress' => in_array('user_progress', $tables),
            'saved_code' => in_array('saved_code', $tables)
        ],
        'missing_tables' => $missingTables,
        'functionality_tests' => $functionalityTests,
        'ready_for_registration' => empty($missingTables)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error creating database schema: ' . $e->getMessage()
    ]);
}
?>