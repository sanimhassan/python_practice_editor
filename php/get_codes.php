<?php
// Prevent any output before headers
ob_start();

// Error handling - log to a file in the same directory
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log'); // Log to a specific directory

// Start session and set JSON content type
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For debugging - remove in production
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // For debugging - remove in production

// Log basic request information for debugging
$requestInfo = [
    'time' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'session_id' => session_id()
];
error_log('Request: ' . json_encode($requestInfo));

// Function to return JSON response and exit
function json_response($success, $data = null, $message = null, $status = 200) {
    http_response_code($status);
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($message !== null) $response['message'] = $message;
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    error_log('Response: ' . json_encode($response)); // Log the response for debugging
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session. Session data: ' . json_encode($_SESSION));
    json_response(false, null, 'Authentication required', 401);
}

// Log the session info for debugging
error_log('User ID from session: ' . $_SESSION['user_id']);

// Database connection - wrapped in try-catch
try {
    // Load database configuration
    if (file_exists('db_config.php')) {
        require_once('db_config.php');
    } else {
        error_log('db_config.php not found');
        json_response(false, null, 'Database configuration file not found', 500);
    }
    
    // Check if required variables exist
    if (!isset($db_host) || !isset($db_name) || !isset($db_user) || !isset($db_pass)) {
        error_log('Database configuration incomplete: ' . 
            (isset($db_host) ? 'host:yes ' : 'host:no ') . 
            (isset($db_name) ? 'db:yes ' : 'db:no ') . 
            (isset($db_user) ? 'user:yes ' : 'user:no ') . 
            (isset($db_pass) ? 'pass:yes ' : 'pass:no ')
        );
        throw new Exception('Database configuration is incomplete');
    }
    
    // Connect to database with error reporting
    error_log("Attempting to connect to database: $db_host, $db_name");
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $db = new PDO($dsn, $db_user, $db_pass, $options);
    error_log('Database connection established successfully');
    
} catch(PDOException $e) {
    error_log('PDO connection error: ' . $e->getMessage());
    json_response(false, null, 'Database connection failed: ' . $e->getMessage(), 500);
} catch(Exception $e) {
    error_log('General exception: ' . $e->getMessage());
    json_response(false, null, 'Error: ' . $e->getMessage(), 500);
}

try {
    // Verify the table structure first
    error_log('Verifying table structure for saved_codes');
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'saved_codes'");
        if ($checkTable->rowCount() == 0) {
            error_log('saved_codes table does not exist!');
            // Try to create the table
            $createTableSQL = "CREATE TABLE IF NOT EXISTS saved_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT 'Untitled',
                code_content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (user_id)
            )";
            $db->exec($createTableSQL);
            error_log('Created saved_codes table');
        }
        
        $checkColumns = $db->query("SHOW COLUMNS FROM saved_codes");
        $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
        error_log('Table columns: ' . implode(', ', $columns));
        
        // Required columns
        $requiredColumns = ['id', 'user_id', 'title', 'code_content', 'created_at', 'last_modified'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (!empty($missingColumns)) {
            error_log('Missing columns: ' . implode(', ', $missingColumns));
        }
    } catch (PDOException $e) {
        error_log('Error checking table structure: ' . $e->getMessage());
        // Continue execution, as this is just a validation step
    }

    // Check if specific code ID is requested
    if (isset($_GET['id'])) {
        $codeId = intval($_GET['id']);
        error_log('Fetching specific code with ID: ' . $codeId);
        
        // Get specific code for the current user
        $stmt = $db->prepare('SELECT id, title, code_content, created_at, last_modified FROM saved_codes WHERE id = ? AND user_id = ?');
        $stmt->execute([$codeId, $_SESSION['user_id']]);
        $code = $stmt->fetch();
        
        if ($code) {
            error_log('Code found and being returned');
            json_response(true, $code, 'Code retrieved successfully');
        } else {
            error_log('Code not found or does not belong to user');
            json_response(false, null, 'Code not found', 404);
        }
    } else {
        // Get all saved codes for the current user
        error_log('Executing query to get all codes for user ID: ' . $_SESSION['user_id']);
        $stmt = $db->prepare('SELECT id, title, code_content, created_at, last_modified FROM saved_codes WHERE user_id = ? ORDER BY last_modified DESC');
        $stmt->execute([$_SESSION['user_id']]);
        $codes = $stmt->fetchAll();
        error_log('Query executed successfully. Found ' . count($codes) . ' code entries.');
        
        json_response(true, $codes, 'Codes retrieved successfully');
    }
} catch(PDOException $e) {
    error_log('Database query error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    json_response(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch(Exception $e) {
    error_log('Error retrieving saved codes: ' . $e->getMessage());
    json_response(false, null, 'Failed to retrieve saved codes: ' . $e->getMessage(), 500);
}
?>