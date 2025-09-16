<?php
// File structure checker for production environment
header('Content-Type: application/json');

// Check various paths where schema.sql might be located
$possiblePaths = [
    '/home/u486120778/domains/pybankers.com/public_html/pypractice_files/schema.sql',
    '/home/u486120778/domains/pybankers.com/public_html/pypractice_files/pypractice_files/schema.sql',
    dirname(__DIR__) . '/pypractice_files/schema.sql',
    dirname(__DIR__) . '/pypractice_files/pypractice_files/schema.sql',
    __DIR__ . '/../pypractice_files/schema.sql',
    __DIR__ . '/schema.sql'
];

$findings = [];

foreach ($possiblePaths as $path) {
    $status = [
        'path' => $path,
        'exists' => file_exists($path),
        'readable' => file_exists($path) ? is_readable($path) : false,
        'real_path' => file_exists($path) ? realpath($path) : null
    ];
    
    if ($status['exists']) {
        $status['file_size'] = filesize($path);
        $status['first_100_chars'] = substr(file_get_contents($path), 0, 100);
    }
    
    $findings[] = $status;
}

// Also check directory structure
$directoryChecks = [
    '/home/u486120778/domains/pybankers.com/public_html',
    '/home/u486120778/domains/pybankers.com/public_html/pypractice_files',
    dirname(__DIR__),
    dirname(__DIR__) . '/pypractice_files'
];

$directoryInfo = [];
foreach ($directoryChecks as $dir) {
    $info = [
        'directory' => $dir,
        'exists' => is_dir($dir),
        'readable' => is_dir($dir) ? is_readable($dir) : false
    ];
    
    if ($info['exists'] && $info['readable']) {
        $files = scandir($dir);
        $info['files'] = array_values(array_diff($files, ['.', '..']));
    }
    
    $directoryInfo[] = $info;
}

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'current_script_path' => __FILE__,
    'current_directory' => __DIR__,
    'parent_directory' => dirname(__DIR__),
    'schema_file_checks' => $findings,
    'directory_structure' => $directoryInfo
]);
?>