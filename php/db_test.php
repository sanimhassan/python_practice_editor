<?php
// Enable error reporting for debugging - comment this out in production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Add basic styling for better appearance
echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 0 20px; }
        h1, h2, h3 { color: #333; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre { background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form { background-color: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin: 10px 0; }
        label { display: block; margin: 10px 0 5px; }
        input[type='text'], input[type='password'] { width: 300px; padding: 8px; margin-bottom: 10px; }
        input[type='submit'] { padding: 8px 15px; background-color: #4285f4; color: white; border: none; cursor: pointer; }
        input[type='submit']:hover { background-color: #3b77db; }
        .back-link { display: inline-block; margin: 20px 0; }
    </style>
</head>
<body>";

echo "<h1>Database Connection Test</h1>";

// Check environment variables
echo "<h2>Environment Check</h2>";
echo "HOSTINGER_ENV set: " . (getenv('HOSTINGER_ENV') ? "YES (".getenv('HOSTINGER_ENV').")" : "<span class='warning'>NO</span>") . "<br>";
echo "PHP version: " . phpversion() . "<br>";
echo "PDO drivers available: " . implode(", ", PDO::getAvailableDrivers()) . "<br>";

// Show configuration form
echo "<h2>Connection Configuration</h2>";
echo "<form method='post' action='' autocomplete='off'>";
echo "<label for='host'>Hostname:</label>";
echo "<input type='text' id='host' name='host' value='" . (isset($_POST['host']) ? htmlspecialchars($_POST['host']) : "localhost") . "'>";
echo "<label for='dbname'>Database Name:</label>";
echo "<input type='text' id='dbname' name='dbname' value='" . (isset($_POST['dbname']) ? htmlspecialchars($_POST['dbname']) : "u486120778_zenalys") . "'>";
echo "<label for='username'>Username:</label>";
echo "<input type='text' id='username' name='username' value='" . (isset($_POST['username']) ? htmlspecialchars($_POST['username']) : "u486120778_sanprat") . "'>";
echo "<label for='password'>Password:</label>";
echo "<input type='password' id='password' name='password'>";
echo "<input type='submit' value='Test Connection'>";
echo "</form>";

// If form submitted, test the connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['host']) && isset($_POST['dbname'])) {
    $host = $_POST['host'];
    $dbname = $_POST['dbname'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    echo "<h2>Connection Test Results</h2>";
    echo "Attempting to connect to database: <strong>$dbname</strong> on host: <strong>$host</strong><br><br>";
    
    // Try connecting in different ways
    $connectionSuccess = false;
    $pdo = null;
    
    // Method 1: Standard connection
    try {
        echo "Attempt 1: Using standard connection string...<br>";
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<span class='success'>✓ Connection successful!</span><br>";
        $connectionSuccess = true;
    } catch (PDOException $e) {
        echo "<span class='error'>✗ Connection failed.</span><br>";
        // For security, don't display full error message in production
        $errorMsg = $e->getMessage();
        // Only show non-sensitive parts of error
        echo "<span class='error'>Error: " . htmlspecialchars(preg_replace('/password=(.+?)( |$)/', 'password=******$2', $errorMsg)) . "</span><br><br>";
        
        // If hostname includes port, try splitting them
        if (strpos($host, ':') !== false) {
            try {
                echo "Attempt 2: Using host and port separately...<br>";
                $hostParts = explode(':', $host);
                $hostOnly = $hostParts[0];
                $portOnly = isset($hostParts[1]) ? $hostParts[1] : 3306;
                
                echo "Host: $hostOnly, Port: $portOnly<br>";
                $pdo = new PDO("mysql:host=$hostOnly;port=$portOnly;dbname=$dbname;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo "<span class='success'>✓ Connection successful with separated port!</span><br>";
                $connectionSuccess = true;
            } catch (PDOException $e2) {
                echo "<span class='error'>✗ Connection failed with separated port.</span><br>";
                // Security: don't show full error
                $errorMsg = $e2->getMessage();
                echo "<span class='error'>Error: " . htmlspecialchars(preg_replace('/password=(.+?)( |$)/', 'password=******$2', $errorMsg)) . "</span><br><br>";
            }
        }
    }
    
    // If connection successful, check database tables
    if ($connectionSuccess && $pdo) {
        // Show the configuration that worked for use in PHP files
        echo "<h3>Use this configuration in your PHP files:</h3>";
        echo "<pre>";
        echo "function getDbConnection() {\n";
        echo "    try {\n";
        if (strpos($host, ':') !== false && !$connectionSuccess) {
            $hostParts = explode(':', $host);
            $hostOnly = $hostParts[0];
            $portOnly = isset($hostParts[1]) ? $hostParts[1] : 3306;
            echo "        \$host = '$hostOnly';\n";
            echo "        \$port = $portOnly;\n";
            echo "        \$dbname = '$dbname';\n";
            echo "        \$username = '$username';\n";
            echo "        \$password = '********';\n\n";
            echo "        \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$dbname;charset=utf8mb4\", \$username, \$password);\n";
        } else {
            echo "        \$host = '$host';\n";
            echo "        \$dbname = '$dbname';\n";
            echo "        \$username = '$username';\n";
            echo "        \$password = '********'; // Use your actual password here\n\n";
            echo "        \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\", \$username, \$password);\n";
        }
        echo "        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
        echo "        return \$pdo;\n";
        echo "    } catch (PDOException \$e) {\n";
        echo "        http_response_code(500);\n";
        echo "        echo json_encode(['success' => false, 'message' => 'Database connection error']);\n";
        echo "        exit;\n";
        echo "    }\n";
        echo "}\n";
        echo "</pre>";
        
        // Check tables
        echo "<h2>Database Structure</h2>";
        try {
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) > 0) {
                echo "<p>Tables in database <strong>$dbname</strong>:</p>";
                echo "<ul>";
                foreach ($tables as $table) {
                    echo "<li>$table</li>";
                }
                echo "</ul>";
                
                // Check for users table specifically
                if (in_array('users', $tables)) {
                    echo "<h3>Users Table Check</h3>";
                    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                    $userCount = $stmt->fetchColumn();
                    echo "Number of users in database: <strong>$userCount</strong><br>";
                    
                    if ($userCount > 0) {
                        $stmt = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY id DESC LIMIT 10");
                        echo "<table>";
                        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Created At</th></tr>";
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['created_at'] ?? 'N/A') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p class='warning'>No users found in the database. You may want to create a test account.</p>";
                    }
                } else {
                    echo "<p class='error'>Users table doesn't exist! Your registration feature won't work correctly.</p>";
                    showSchemaImportForm($host, $dbname, $username, $password);
                }
            } else {
                echo "<p class='error'>No tables found in database! Your application won't function correctly.</p>";
                showSchemaImportForm($host, $dbname, $username, $password);
            }
        } catch (PDOException $e) {
            echo "<p class='error'>Error checking tables: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "<h2>Environment Variables</h2>";
    echo "<p>Make sure your .htaccess file contains:</p>";
    echo "<pre>SetEnv HOSTINGER_ENV true</pre>";
    
    echo "<h2>JavaScript Configuration</h2>";
    echo "<p>Make sure your enhanced-ui.js file has mode set to 'php':</p>";
    echo "<pre>";
    echo "auth: {\n";
    echo "    // Configuration - set to 'mock' for localStorage based auth or 'php' for server backend\n";
    echo "    mode: 'php', // Change from 'mock' to 'php' for server-side authentication\n";
    echo "    // ...\n";
    echo "};\n";
    echo "</pre>";
}

// Import schema if requested
if (isset($_POST['import_schema']) && $_POST['import_schema'] == '1') {
    $host = $_POST['host'];
    $dbname = $_POST['dbname'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    echo "<h2>Schema Import Results</h2>";
    
    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // First, check if schema.sql exists in the current directory
        $schemaFile = 'schema.sql';
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
        } else {
            // Try the db directory as fallback
            $schemaPath = __DIR__ . '/../db/schema.sql';
            if (file_exists($schemaPath)) {
                $schema = file_get_contents($schemaPath);
            } else {
                throw new Exception("Schema file not found. Please make sure schema.sql exists in the current directory or in the db folder.");
            }
        }
        
        if (empty($schema)) {
            throw new Exception("Schema file is empty");
        }
        
        // Split schema into separate queries
        $queries = explode(';', $schema);
        
        $totalQueries = count($queries);
        $successfulQueries = 0;
        $failedQueries = [];
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $pdo->exec($query);
                    $successfulQueries++;
                    echo "<p class='success'>✓ Query executed successfully:</p>";
                    echo "<pre>" . htmlspecialchars(substr($query, 0, 100)) . (strlen($query) > 100 ? "..." : "") . "</pre>";
                } catch (PDOException $e) {
                    $failedQueries[] = [
                        'query' => $query,
                        'error' => $e->getMessage()
                    ];
                    echo "<p class='error'>✗ Query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "<pre>" . htmlspecialchars($query) . "</pre>";
                }
            }
        }
        
        echo "<h3>Import Summary</h3>";
        if ($successfulQueries == $totalQueries) {
            echo "<p class='success'>Successfully executed all queries! Your database structure is now complete.</p>";
        } else {
            echo "<p>Successfully executed $successfulQueries out of $totalQueries queries.</p>";
            
            if (!empty($failedQueries)) {
                echo "<p class='warning'>Failed queries: " . count($failedQueries) . "</p>";
            }
        }
        
        echo "<p>Please test the connection again to verify that all tables were created properly.</p>";
        echo "<a href='" . $_SERVER['PHP_SELF'] . "' class='back-link'>Run another connection test</a>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    } catch (Exception $e) {
        echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Function to show schema import form
function showSchemaImportForm($host, $dbname, $username, $password) {
    $schemaFile = 'schema.sql';
    $schemaContent = '';
    
    if (file_exists($schemaFile)) {
        $schemaContent = file_get_contents($schemaFile);
    } else {
        // Try the db directory as fallback
        $schemaPath = __DIR__ . '/../db/schema.sql';
        if (file_exists($schemaPath)) {
            $schemaContent = file_get_contents($schemaPath);
        }
    }
    
    echo "<h3>Missing Database Schema</h3>";
    
    if (!empty($schemaContent)) {
        echo "<p>Found schema.sql with the following structure:</p>";
        echo "<pre style='max-height:300px;overflow-y:scroll;'>" . htmlspecialchars($schemaContent) . "</pre>";
        
        echo "<h4>Import Schema</h4>";
        echo "<form method='post' action=''>";
        echo "<input type='hidden' name='host' value='" . htmlspecialchars($host) . "'>";
        echo "<input type='hidden' name='dbname' value='" . htmlspecialchars($dbname) . "'>";
        echo "<input type='hidden' name='username' value='" . htmlspecialchars($username) . "'>";
        echo "<input type='hidden' name='password' value='" . htmlspecialchars($password) . "'>";
        echo "<input type='hidden' name='import_schema' value='1'>";
        echo "<input type='submit' value='Import Schema Now'>";
        echo "</form>";
    } else {
        echo "<p class='error'>Schema file not found! Please create a schema.sql file with your database structure.</p>";
    }
}

echo "</body></html>";
?>