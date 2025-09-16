<?php
// Login.php - User authentication functionality for Python Practice PWA
header('Content-Type: application/json');

// Enable error logging to a custom file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../my_custom_error_log.txt');

// Ensure PHP errors don't output as HTML - force JSON for all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.', 'error' => $errstr]);
    exit;
});

// Start session to manage user's logged-in state
session_start();

// Load database configuration
require_once __DIR__ . '/db_config.php';

// Database connection function - uses getPDOConnection from db_config.php
function getDbConnection() {
    global $db_host, $db_name, $db_user, $db_pass;
    
    // Log connection attempt without exposing password
    error_log("Login.php: Attempting DB connection to: $db_host, DB: $db_name, User: $db_user");
    
    // Get connection from db_config.php
    $connection = getPDOConnection();
    
    // If connection failed, throw exception with helpful message
    if (!$connection) {
        error_log("Login.php: DB Connection failed - check .env file for correct credentials");
        throw new PDOException("Failed to connect to database. Please ensure your .env file exists with correct database credentials.");
    }
    
    error_log("Login.php: Database connection successful");
    return $connection;
}

// Process login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize user input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST; // Fallback to standard POST data
    }
    
    error_log("Login attempt with user: " . (isset($input['username']) ? $input['username'] : 'undefined'));
    
    // Validate required fields
    if (!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    
    
    // Connect to database for normal users
    try {
        $pdo = getDbConnection();
        
        // Find user by username or email
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]); // Allow login with either username or email
        
        if ($stmt->rowCount() === 0) {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
            exit;
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Update last login timestamp
            $updateStmt = $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
            $updateStmt->execute([$user['id']]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['logged_in'] = true;
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user_id' => $user['id'],
                'username' => $user['username']
            ]);
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
    } catch (PDOException $e) {
        error_log("Login database error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.', 'debug_error' => $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        error_log("General exception in login: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.', 'debug_error' => $e->getMessage()]);
        exit;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    // Check if user is logged in
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Logout functionality
    session_unset();
    session_destroy();
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>