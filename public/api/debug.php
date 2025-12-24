<?php
/**
 * FUSIONEL - Debug & Test
 * 
 * Accédez à: https://fusionel.fr/api/debug.php
 * 
 * ⚠️ SUPPRIMEZ CE FICHIER EN PRODUCTION !
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);

$debug = [
    'php_version' => PHP_VERSION,
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Config file
$configPath = __DIR__ . '/../../config/database.php';
$debug['tests']['config_file'] = [
    'path' => $configPath,
    'exists' => file_exists($configPath)
];

if (!file_exists($configPath)) {
    $debug['error'] = 'Config file not found';
    echo json_encode($debug, JSON_PRETTY_PRINT);
    exit;
}

// Test 2: Database connection
try {
    $config = require $configPath;
    $debug['tests']['config_loaded'] = [
        'host' => $config['host'] ?? 'NOT SET',
        'database' => $config['database'] ?? 'NOT SET',
        'username' => $config['username'] ?? 'NOT SET',
        'password' => isset($config['password']) ? '***SET***' : 'NOT SET'
    ];
    
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $debug['tests']['database_connection'] = [
        'status' => 'SUCCESS',
        'server_info' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)
    ];
    
} catch (PDOException $e) {
    $debug['tests']['database_connection'] = [
        'status' => 'FAILED',
        'error' => $e->getMessage()
    ];
    echo json_encode($debug, JSON_PRETTY_PRINT);
    exit;
}

// Test 3: Tables existence
$tables = ['users', 'photos', 'likes', 'matches', 'messages', 'subscriptions', 'payments'];
$debug['tests']['tables'] = [];

foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        $debug['tests']['tables'][$table] = [
            'exists' => true,
            'row_count' => (int)$count
        ];
    } catch (PDOException $e) {
        $debug['tests']['tables'][$table] = [
            'exists' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Test 4: Users table structure
try {
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $debug['tests']['users_columns'] = $columns;
} catch (PDOException $e) {
    $debug['tests']['users_columns'] = ['error' => $e->getMessage()];
}

// Test 5: Recent users
try {
    $stmt = $pdo->query("SELECT id, firstname, email, created_at, subscription_type FROM users ORDER BY id DESC LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug['tests']['recent_users'] = $users;
} catch (PDOException $e) {
    $debug['tests']['recent_users'] = ['error' => $e->getMessage()];
}

// Test 6: Test INSERT (dry run)
try {
    $testEmail = 'test_' . time() . '@test.com';
    $stmt = $pdo->prepare("
        INSERT INTO users (firstname, email, password, gender, seeking, birthdate, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    // Start transaction (we'll rollback)
    $pdo->beginTransaction();
    
    $result = $stmt->execute([
        'TestUser',
        $testEmail,
        password_hash('test123', PASSWORD_DEFAULT),
        'male',
        'female',
        '1990-01-01'
    ]);
    
    $insertId = $pdo->lastInsertId();
    
    // Rollback - don't actually insert
    $pdo->rollBack();
    
    $debug['tests']['insert_test'] = [
        'status' => 'SUCCESS',
        'message' => "INSERT would work (rolled back)",
        'would_be_id' => $insertId
    ];
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $debug['tests']['insert_test'] = [
        'status' => 'FAILED',
        'error' => $e->getMessage(),
        'sql_state' => $e->getCode()
    ];
}

// Test 7: Check API endpoint
$debug['tests']['api_check'] = [
    'register_endpoint' => file_exists(__DIR__ . '/index.php') ? 'EXISTS' : 'NOT FOUND',
    'admin_endpoint' => file_exists(__DIR__ . '/admin.php') ? 'EXISTS' : 'NOT FOUND'
];

// Test 8: Session
session_start();
$debug['tests']['session'] = [
    'id' => session_id(),
    'status' => session_status(),
    'save_path' => session_save_path()
];

// Summary
$debug['summary'] = [
    'database' => $debug['tests']['database_connection']['status'] === 'SUCCESS' ? '✅ OK' : '❌ FAILED',
    'users_table' => isset($debug['tests']['tables']['users']['exists']) && $debug['tests']['tables']['users']['exists'] ? '✅ OK' : '❌ MISSING',
    'users_count' => $debug['tests']['tables']['users']['row_count'] ?? 0,
    'insert_works' => $debug['tests']['insert_test']['status'] === 'SUCCESS' ? '✅ OK' : '❌ FAILED'
];

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
