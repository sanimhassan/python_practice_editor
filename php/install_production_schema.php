<?php
// Production Schema installer - Creates required database tables using the correct schema path
header('Content-Type: application/json');

// Include database configuration
include 'db_config.php';

try {
    $pdo = getPDOConnection();
    
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Read schema file from multiple possible paths
    $possiblePaths = [
        '/home/u486120778/domains/pybankers.com/public_html/pypractice_files/schema.sql', // Production path
        dirname(__DIR__) . '/pypractice_files/schema.sql', // Relative path from public_html
        __DIR__ . '/../pypractice_files/schema.sql', // Alternative relative path
        __DIR__ . '/schema.sql' // Fallback to php directory
    ];
    
    $schemaFile = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $schemaFile = $path;
            break;
        }
    }
    
    if (!$schemaFile) {
        echo json_encode([
            'success' => false, 
            'message' => 'Schema file not found in any of the expected locations',
            'checked_paths' => $possiblePaths
        ]);
        exit;
    }
    
    // Read and parse schema more carefully
    $schema = file_get_contents($schemaFile);
    if (empty($schema)) {
        echo json_encode(['success' => false, 'message' => 'Schema file is empty']);
        exit;
    }
    
    // Remove comments and split properly
    $lines = explode("\n", $schema);
    $cleanedLines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comment lines
        if (!empty($line) && !preg_match('/^--/', $line)) {
            $cleanedLines[] = $line;
        }
    }
    
    // Join lines and split by semicolons
    $cleanedSchema = implode(" ", $cleanedLines);
    $statements = explode(';', $cleanedSchema);
    
    // Filter and categorize statements
    $createTableStatements = [];
    $createIndexStatements = [];
    $otherStatements = [];
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        if (preg_match('/^CREATE TABLE/i', $statement)) {
            $createTableStatements[] = $statement;
        } elseif (preg_match('/^CREATE INDEX/i', $statement)) {
            $createIndexStatements[] = $statement;
        } elseif (!empty($statement)) {
            $otherStatements[] = $statement;
        }
    }
    
    // Execute in correct order: Tables first, then indexes, then others
    $allStatements = array_merge($createTableStatements, $createIndexStatements, $otherStatements);
    $results = [];
    $successCount = 0;
    
    foreach ($allStatements as $statement) {
        if (!empty($statement)) {
            try {
                error_log("Executing SQL: " . substr($statement, 0, 100) . "...");
                $pdo->exec($statement);
                $tableName = '';
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                    $tableName = $matches[1];
                    error_log("Successfully created table: $tableName");
                } elseif (preg_match('/CREATE INDEX.*?ON\s+`?(\w+)`?/i', $statement, $matches)) {
                    $tableName = 'Index on ' . $matches[1];
                    error_log("Successfully created index on table: " . $matches[1]);
                }
                $results[] = [
                    'query' => substr($statement, 0, 80) . '...', 
                    'status' => 'success',
                    'table' => $tableName
                ];
                $successCount++;
            } catch (PDOException $e) {
                error_log("SQL Error: " . $e->getMessage() . " for query: " . substr($statement, 0, 100));
                $results[] = [
                    'query' => substr($statement, 0, 80) . '...', 
                    'status' => 'error', 
                    'message' => $e->getMessage(),
                    'sql_state' => $e->getCode()
                ];
            }
        }
    }
    
    // Verify tables were created
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Check specifically for required tables
    $requiredTables = ['users', 'user_progress', 'saved_code'];
    $missingTables = array_diff($requiredTables, $tables);
    
    echo json_encode([
        'success' => $successCount > 0 && empty($missingTables),
        'message' => "Executed $successCount queries successfully",
        'schema_file_used' => $schemaFile,
        'schema_file_found_at' => realpath($schemaFile),
        'debug_info' => [
            'create_table_statements' => count($createTableStatements),
            'create_index_statements' => count($createIndexStatements),
            'other_statements' => count($otherStatements),
            'total_statements' => count($allStatements)
        ],
        'results' => $results,
        'tables_created' => $tables,
        'required_tables_status' => [
            'users' => in_array('users', $tables),
            'user_progress' => in_array('user_progress', $tables),
            'saved_code' => in_array('saved_code', $tables)
        ],
        'missing_tables' => $missingTables
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error creating database schema: ' . $e->getMessage(),
        'schema_path_checked' => isset($schemaFile) ? $schemaFile : 'undefined'
    ]);
}
?>