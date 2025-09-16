<?php
// Simple registration test to identify the exact issue
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/registration_test.log');

try {
    // Include configuration
    include 'db_config.php';
    
    // Test database connection using the same method as register.php
    function getDbConnection() {
        global $db_host, $db_name, $db_user, $db_pass;
        
        try {
            error_log("Testing connection to: host=$db_host, db=$db_name, user=$db_user");
            
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", 
                           $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("Connection successful");
            return $pdo;
        } catch (PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Test the connection
    $pdo = getDbConnection();
    
    // Test if users table exists and has correct structure
    $stmt = $pdo->query("SHOW CREATE TABLE users");
    $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Users table structure: " . $tableInfo['Create Table']);
    
    // Test with sample data
    $testData = [
        'username' => 'testuser_' . time(),
        'email' => 'test_' . time() . '@example.com',
        'password' => 'testpass123'
    ];
    
    error_log("Testing registration with: " . json_encode($testData));
    
    // Hash password
    $password_hash = password_hash($testData['password'], PASSWORD_DEFAULT);
    error_log("Password hashed successfully");
    
    // Check for existing user (registration step 1)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$testData['username'], $testData['email']]);
    error_log("Duplicate check query executed");
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Test user already exists']);
        exit;
    }
    
    error_log("No duplicate found, proceeding with INSERT");
    
    // Insert new user (registration step 2)
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $result = $stmt->execute([$testData['username'], $testData['email'], $password_hash]);
    
    if ($result) {
        $userId = $pdo->lastInsertId();
        error_log("Registration successful, user ID: $userId");
        
        // Clean up test user
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        error_log("Test user cleaned up");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registration test completed successfully',
            'test_user_id' => $userId
        ]);
    } else {
        error_log("Registration failed - INSERT returned false");
        echo json_encode(['success' => false, 'message' => 'INSERT operation failed']);
    }
    
} catch (PDOException $e) {
    error_log("PDO Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>