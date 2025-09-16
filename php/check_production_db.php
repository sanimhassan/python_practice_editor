<?php
// Production database structure checker - validates all required tables
header('Content-Type: application/json');

// Include database configuration
include 'db_config.php';

try {
    $pdo = getPDOConnection();
    
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Check for all required tables
    $requiredTables = [
        'users' => [
            'required_columns' => ['id', 'username', 'email', 'password_hash', 'created_at'],
            'exists' => false,
            'columns' => []
        ],
        'user_progress' => [
            'required_columns' => ['id', 'user_id', 'code_snippet', 'exercise_name', 'is_completed'],
            'exists' => false,
            'columns' => []
        ],
        'saved_code' => [
            'required_columns' => ['id', 'user_id', 'title', 'code_content', 'created_at'],
            'exists' => false,
            'columns' => []
        ]
    ];
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $overallStatus = true;
    
    // Check each required table
    foreach ($requiredTables as $tableName => &$tableInfo) {
        $tableInfo['exists'] = in_array($tableName, $allTables);
        
        if ($tableInfo['exists']) {
            // Get column information
            $stmt = $pdo->query("DESCRIBE `$tableName`");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $tableInfo['columns'] = $columns;
            
            // Check if all required columns exist
            $missingColumns = array_diff($tableInfo['required_columns'], $columns);
            $tableInfo['missing_columns'] = $missingColumns;
            $tableInfo['complete'] = empty($missingColumns);
            
            if (!$tableInfo['complete']) {
                $overallStatus = false;
            }
        } else {
            $overallStatus = false;
            $tableInfo['complete'] = false;
            $tableInfo['missing_columns'] = $tableInfo['required_columns'];
        }
    }
    
    // Test basic functionality
    $functionalityTests = [];
    
    if ($requiredTables['users']['exists']) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = $stmt->fetchColumn();
            $functionalityTests['users_table_readable'] = true;
            $functionalityTests['user_count'] = $userCount;
        } catch (Exception $e) {
            $functionalityTests['users_table_readable'] = false;
            $functionalityTests['users_error'] = $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'database_ready' => $overallStatus,
        'total_tables_found' => count($allTables),
        'all_tables' => $allTables,
        'required_tables_status' => $requiredTables,
        'functionality_tests' => $functionalityTests,
        'recommendations' => $overallStatus ? 
            ['All tables are properly configured. Registration should work.'] :
            ['Run install_production_schema.php to create missing tables', 'Verify database credentials in .env file']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error checking database structure: ' . $e->getMessage()
    ]);
}
?>