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
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
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
error_log('Delete Code Request: ' . json_encode($requestInfo));

// Function to return JSON response and exit
function json_response($success, $data = null, $message = null, $status = 200) {
    http_response_code($status);
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($message !== null) $response['message'] = $message;
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    error_log('Delete Code Response: ' . json_encode($response));
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session for delete operation');
    json_response(false, null, 'Authentication required', 401);
}

// Allow both POST and DELETE methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    json_response(false, null, 'Method not allowed', 405);
}

// Get code ID from different sources
$codeId = null;

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Try to get from JSON input first
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['id'])) {
        $codeId = intval($input['id']);
    }
    // Fallback to GET parameter
    elseif (isset($_GET['id'])) {
        $codeId = intval($_GET['id']);
    }
    // Fallback to POST parameter
    elseif (isset($_POST['id'])) {
        $codeId = intval($_POST['id']);
    }
}

// Validate code ID
if (!$codeId || $codeId <= 0) {
    json_response(false, null, 'Invalid or missing code ID', 400);
}

// Database connection
try {
    // Load database configuration
    if (file_exists('db_config.php')) {
        require_once('db_config.php');
    } else {
        error_log('db_config.php not found for delete operation');
        json_response(false, null, 'Database configuration file not found', 500);
    }
    
    // Check if required variables exist
    if (!isset($db_host) || !isset($db_name) || !isset($db_user) || !isset($db_pass)) {
        error_log('Database configuration incomplete for delete operation');
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
    error_log('Database connection established for delete operation');
    
} catch(PDOException $e) {
    error_log('PDO connection error in delete: ' . $e->getMessage());
    json_response(false, null, 'Database connection failed', 500);
} catch(Exception $e) {
    error_log('General exception in delete: ' . $e->getMessage());
    json_response(false, null, 'Error: ' . $e->getMessage(), 500);
}

try {
    // First, verify that the code belongs to the current user and get its title for logging
    $checkStmt = $db->prepare('SELECT id, title FROM saved_codes WHERE id = ? AND user_id = ?');
    $checkStmt->execute([$codeId, $_SESSION['user_id']]);
    $codeData = $checkStmt->fetch();
    
    if (!$codeData) {
        error_log("Code ID $codeId not found or doesn't belong to user " . $_SESSION['user_id']);
        json_response(false, null, 'Code not found or access denied', 404);
    }
    
    // Delete the code
    $deleteStmt = $db->prepare('DELETE FROM saved_codes WHERE id = ? AND user_id = ?');
    $result = $deleteStmt->execute([$codeId, $_SESSION['user_id']]);
    
    if ($result && $deleteStmt->rowCount() > 0) {
        error_log("Code ID $codeId ('{$codeData['title']}') deleted successfully for user " . $_SESSION['user_id']);
        json_response(true, ['id' => $codeId], 'Code deleted successfully');
    } else {
        error_log("Failed to delete code ID $codeId for user " . $_SESSION['user_id']);
        json_response(false, null, 'Failed to delete code', 500);
    }
    
} catch(PDOException $e) {
    error_log('Database error in delete: ' . $e->getMessage());
    json_response(false, null, 'Database error occurred', 500);
} catch(Exception $e) {
    error_log('Error deleting code: ' . $e->getMessage());
    json_response(false, null, 'Failed to delete code', 500);
}
?>