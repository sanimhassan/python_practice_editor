<?php
// Register.php - User registration functionality for Python Practice PWA
header('Content-Type: application/json');

// Ensure PHP errors don't output as HTML - force JSON for all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $errstr]);
    exit;
});

// Start session to manage user's logged-in state
session_start();

// Load database configuration
require_once __DIR__ . '/db_config.php';

// Process registration request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize user input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback to standard POST data if JSON is empty
    if (!$input) {
        $input = $_POST;
    }
    
    error_log("Registration attempt with user: " . (isset($input['username']) ? $input['username'] : 'undefined'));
    
    // Validate required fields
    if (empty($input['username']) || empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];
    
    // Basic validation
    if (strlen($username) < 3 || strlen($username) > 50) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username must be between 3 and 50 characters']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    // Hash the password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        // Get database connection
        $pdo = getPDOConnection();
        
        // Check if username or email already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            exit;
        }
        
        // Insert new user with prepared statement
        $stmt = $pdo->prepare('
            INSERT INTO users (username, email, password_hash, created_at) 
            VALUES (?, ?, ?, NOW())
        ');
        
        if ($stmt->execute([$username, $email, $password_hash])) {
            $userId = $pdo->lastInsertId();
            
            // Set session variables
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['logged_in'] = true;
            
            http_response_code(201); // Created
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! You can now log in.',
                'user_id' => $userId,
                'username' => $username
            ]);
        } else {
            throw new Exception('Failed to register user');
        }
        
    } catch (PDOException $e) {
        error_log("Database error during registration: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    } catch (Exception $e) {
        error_log("Error during registration: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>