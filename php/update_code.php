<?php
// Prevent any output before headers
ob_start();

// Error handling - log to a file in the same directory
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Start session and set JSON content type
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log basic request information for debugging
$requestInfo = [
    'time' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'session_id' => session_id()
];
error_log('Update Code Request: ' . json_encode($requestInfo));

// Function to return JSON response and exit
function json_response($success, $data = null, $message = null, $status = 200) {
    http_response_code($status);
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($message !== null) $response['message'] = $message;
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    error_log('Update Code Response: ' . json_encode($response));
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session for update operation');
    json_response(false, null, 'Authentication required', 401);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(false, null, 'Invalid JSON input', 400);
}

// Validate required fields
if (!isset($input['id']) || !isset($input['title']) || !isset($input['code_content'])) {
    json_response(false, null, 'Missing required fields: id, title, code_content', 400);
}

$codeId = intval($input['id']);
$title = trim($input['title']);
$codeContent = $input['code_content'];

// Validate input
if ($codeId <= 0) {
    json_response(false, null, 'Invalid code ID', 400);
}

if (empty($title)) {
    json_response(false, null, 'Title cannot be empty', 400);
}

if (strlen($title) > 255) {
    json_response(false, null, 'Title too long (max 255 characters)', 400);
}

// Database connection
try {
    // Load database configuration
    if (file_exists('db_config.php')) {
        require_once('db_config.php');
    } else {
        error_log('db_config.php not found for update operation');
        json_response(false, null, 'Database configuration file not found', 500);
    }
    
    // Check if required variables exist
    if (!isset($db_host) || !isset($db_name) || !isset($db_user) || !isset($db_pass)) {
        error_log('Database configuration incomplete for update operation');
        throw new Exception('Database configuration is incomplete');
    }
    
    // Connect to database
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $db = new PDO($dsn, $db_user, $db_pass, $options);
    error_log('Database connection established for update operation');
    
} catch(PDOException $e) {
    error_log('PDO connection error in update: ' . $e->getMessage());
    json_response(false, null, 'Database connection failed', 500);
} catch(Exception $e) {
    error_log('General exception in update: ' . $e->getMessage());
    json_response(false, null, 'Error: ' . $e->getMessage(), 500);
}

try {
    // First, verify that the code belongs to the current user
    $checkStmt = $db->prepare('SELECT id FROM saved_codes WHERE id = ? AND user_id = ?');
    $checkStmt->execute([$codeId, $_SESSION['user_id']]);
    
    if (!$checkStmt->fetch()) {
        error_log("Code ID $codeId not found or doesn't belong to user " . $_SESSION['user_id']);
        json_response(false, null, 'Code not found or access denied', 404);
    }
    
    // Update the code
    $updateStmt = $db->prepare('UPDATE saved_codes SET title = ?, code_content = ?, last_modified = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
    $result = $updateStmt->execute([$title, $codeContent, $codeId, $_SESSION['user_id']]);
    
    if ($result && $updateStmt->rowCount() > 0) {
        error_log("Code ID $codeId updated successfully for user " . $_SESSION['user_id']);
        json_response(true, ['id' => $codeId], 'Code updated successfully');
    } else {
        error_log("Failed to update code ID $codeId for user " . $_SESSION['user_id']);
        json_response(false, null, 'Failed to update code', 500);
    }
    
} catch(PDOException $e) {
    error_log('Database error in update: ' . $e->getMessage());
    json_response(false, null, 'Database error occurred', 500);
} catch(Exception $e) {
    error_log('Error updating code: ' . $e->getMessage());
    json_response(false, null, 'Failed to update code', 500);
}
?>