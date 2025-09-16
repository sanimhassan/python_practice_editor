<?php
// save_progress.php - Save user code progress for Python Practice PWA
header('Content-Type: application/json');
session_start();

// Initialize error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Database connection
function getDbConnection() {
    // Define the path to the database configuration file
    $dbConfigPath = __DIR__ . '/db_config.php';
    
    // Check if the config file exists
    if (!file_exists($dbConfigPath)) {
        error_log("Database configuration file not found at: $dbConfigPath");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database configuration file not found']);
        exit;
    }
    
    // Load database configuration file - use require to ensure variables are in this scope
    require $dbConfigPath;

    // Explicitly check each required variable and provide clear error messages
    $missingVars = [];
    if (!isset($db_host) || empty($db_host)) $missingVars[] = 'db_host';
    if (!isset($db_name) || empty($db_name)) $missingVars[] = 'db_name';
    if (!isset($db_user) || empty($db_user)) $missingVars[] = 'db_user';
    if (!isset($db_pass) || empty($db_pass)) $missingVars[] = 'db_pass';
    if (!empty($missingVars)) {
        error_log('Database configuration is incomplete. Missing: ' . implode(', ', $missingVars));
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database configuration is incomplete',
            'missing' => $missingVars
        ]);
        exit;
    }
    
    try {
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection error', 'error' => $e->getMessage()]);
        exit;
    }
}

// Process save request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize user input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST; // Fallback to standard POST data
    }
    
    // Validate required fields
    if (!isset($input['code_content'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing code content']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $codeContent = $input['code_content'];
    $title = isset($input['title']) ? trim($input['title']) : 'Untitled Code ' . date('Y-m-d H:i:s');
    $exerciseName = isset($input['exercise_name']) ? trim($input['exercise_name']) : null;
    $isCompleted = isset($input['is_completed']) ? (bool)$input['is_completed'] : false;
    
    try {
        // Connect to database
        $pdo = getDbConnection();
        
        // Check if saved_codes table exists, if not try to create it
        $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'saved_codes'");
        $tableExists = ($tableCheckStmt->rowCount() > 0);
        
        if (!$tableExists) {
            error_log('Creating saved_codes table');
            $pdo->exec("CREATE TABLE IF NOT EXISTS saved_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT 'Untitled',
                code_content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (user_id)
            )");
        }
        
        // If this is a specific exercise, update user_progress
        if ($exerciseName) {
            // Check if progress exists
            $stmt = $pdo->prepare('SELECT id FROM user_progress WHERE user_id = ? AND exercise_name = ?');
            $stmt->execute([$userId, $exerciseName]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing progress
                $progressId = $stmt->fetch(PDO::FETCH_COLUMN);
                $stmt = $pdo->prepare('UPDATE user_progress SET code_snippet = ?, is_completed = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?');
                $result = $stmt->execute([$codeContent, $isCompleted ? 1 : 0, $progressId]);
            } else {
                // Create new progress record
                $stmt = $pdo->prepare('INSERT INTO user_progress (user_id, code_snippet, exercise_name, is_completed) VALUES (?, ?, ?, ?)');
                $result = $stmt->execute([$userId, $codeContent, $exerciseName, $isCompleted ? 1 : 0]);
            }
        } else {
            // This is a general code save - use saved_codes table (consistent with get_codes.php)
            $stmt = $pdo->prepare('INSERT INTO saved_codes (user_id, title, code_content) VALUES (?, ?, ?)');
            $result = $stmt->execute([$userId, $title, $codeContent]);
        }
        
        if ($result) {
            $savedId = $pdo->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'Code saved successfully',
                'id' => $savedId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save code']);
        }
    } catch (PDOException $e) {
        error_log('Database error in save_progress.php: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Retrieve saved code or progress
    $userId = $_SESSION['user_id'];
    $codeId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $exerciseName = isset($_GET['exercise']) ? $_GET['exercise'] : null;
    
    try {
        $pdo = getDbConnection();
        
        if ($codeId) {
            // Get specific saved code
            $stmt = $pdo->prepare('SELECT * FROM saved_codes WHERE id = ? AND user_id = ?');
            $stmt->execute([$codeId, $userId]);
        } else if ($exerciseName) {
            // Get exercise progress
            $stmt = $pdo->prepare('SELECT * FROM user_progress WHERE exercise_name = ? AND user_id = ?');
            $stmt->execute([$exerciseName, $userId]);
        } else {
            // Get all saved code
            $stmt = $pdo->prepare('SELECT id, title, created_at, last_modified FROM saved_codes WHERE user_id = ? ORDER BY last_modified DESC');
            $stmt->execute([$userId]);
        }
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } catch (PDOException $e) {
        error_log('Database error in save_progress.php GET: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>