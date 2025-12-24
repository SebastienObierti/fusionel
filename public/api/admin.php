<?php
/**
 * FUSIONEL - API Admin
 */

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$rootDir = dirname(dirname(__DIR__));
$configDir = $rootDir . '/config';
$settingsFile = $configDir . '/settings.json';
$route = $_GET['route'] ?? 'stats';
$method = $_SERVER['REQUEST_METHOD'];

// Charger la config database
require_once $configDir . '/database.php';

// Debug
if ($route === 'debug') {
    die(json_encode([
        'ok' => true, 
        'db_host' => DB_HOST,
        'db_name' => DB_NAME,
        'config_dir' => $configDir
    ], JSON_PRETTY_PRINT));
}

// Connexion DB via la fonction db() existante
try {
    $pdo = db();
} catch (Exception $e) {
    die(json_encode(['error' => 'DB Connection: ' . $e->getMessage()]));
}

try {
    // STATS
    if ($route === 'stats') {
        $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $today = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        die(json_encode([
            'total_users' => (int)$total, 
            'new_users_today' => (int)$today,
            'premium_users' => 0, 
            'vip_users' => 0, 
            'total_matches' => 0,
            'matches_today' => 0, 
            'monthly_revenue' => 0
        ]));
    }
    
    // USERS
    if ($route === 'users') {
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $page = max(1, intval($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, email, gender, subscription_type, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        die(json_encode([
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC), 
            'total' => (int)$total, 
            'page' => $page, 
            'total_pages' => ceil($total/$limit)
        ]));
    }
    
    // SETTINGS
    if ($route === 'settings') {
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) die(json_encode(['success' => false, 'error' => 'Invalid JSON']));
            $r = file_put_contents($settingsFile, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            die(json_encode(['success' => $r !== false, 'message' => 'Sauvegardé', 'bytes' => $r]));
        }
        if (file_exists($settingsFile)) {
            die(file_get_contents($settingsFile));
        }
        die(json_encode([
            'site_name' => 'Fusionel',
            'site_url' => 'https://fusionel.fr', 
            'contact_email' => 'contact@fusionel.fr',
            'premium_monthly' => 9.99,
            'premium_quarterly' => 25.49,
            'premium_yearly' => 83.88,
            'vip_monthly' => 19.99,
            'vip_quarterly' => 50.99,
            'vip_yearly' => 167.88
        ]));
    }
    
    // OTHER ROUTES
    if ($route === 'subscriptions') die(json_encode(['subscriptions' => []]));
    if ($route === 'payments') die(json_encode(['payments' => []]));
    if ($route === 'matches') die(json_encode(['matches' => []]));
    if ($route === 'clear-cache' || $route === 'optimize-db') die(json_encode(['success' => true]));
    
    // USER BY ID
    if (preg_match('/^user_(\d+)$/', $route, $m)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$m[1]]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) unset($u['password']);
        die(json_encode($u ?: ['error' => 'Not found']));
    }
    
    // EXPORT
    if ($route === 'export-users') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users_'.date('Y-m-d').'.csv');
        $stmt = $pdo->query("SELECT id, firstname, email, created_at FROM users");
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Prénom', 'Email', 'Inscrit']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($out, $row);
        exit;
    }
    
    die(json_encode(['error' => 'Unknown route: ' . $route]));
    
} catch (Exception $e) {
    die(json_encode(['error' => $e->getMessage()]));
}
