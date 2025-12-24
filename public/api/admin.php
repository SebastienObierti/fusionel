<?php
/**
 * API Admin - Fusionel
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Charger la config - chemin pour /srv/web/fusionel/public/api/admin.php
require_once __DIR__ . '/../../config/database.php';

$route = $_GET['route'] ?? '';

try {
    $pdo = db();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur BDD: ' . $e->getMessage()]);
    exit;
}

switch ($route) {
    case 'stats':
        $stats = [];
        $stats['total_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['premium_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE subscription_type='premium'")->fetchColumn();
        $stats['vip_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE subscription_type='vip'")->fetchColumn();
        $stats['new_users_today'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        try { 
            $stats['total_matches'] = (int)$pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn(); 
        } catch(Exception $e) { 
            $stats['total_matches'] = 0; 
        }
        try { 
            $stats['matches_today'] = (int)$pdo->query("SELECT COUNT(*) FROM matches WHERE DATE(matched_at)=CURDATE()")->fetchColumn(); 
        } catch(Exception $e) { 
            $stats['matches_today'] = 0; 
        }
        try { 
            $stats['monthly_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND MONTH(created_at)=MONTH(CURDATE())")->fetchColumn(); 
        } catch(Exception $e) { 
            $stats['monthly_revenue'] = 0; 
        }
        echo json_encode($stats);
        break;
        
    case 'users':
        $limit = min(100, (int)($_GET['limit'] ?? 20));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.firstname, u.lastname, u.birthdate, u.gender, u.seeking,
                   u.city, u.bio, u.job, u.subscription_type, u.is_banned, u.is_verified,
                   u.email_verified, u.created_at, u.last_seen,
                   (SELECT CONCAT('/uploads/photos/',filename) FROM user_photos WHERE user_id=u.id AND is_primary=1 LIMIT 1) as main_photo 
            FROM users u 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        echo json_encode([
            'users' => $stmt->fetchAll(), 
            'total' => $total, 
            'page' => $page, 
            'total_pages' => ceil($total/$limit)
        ]);
        break;
        
    case 'user':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['error' => 'ID requis']);
            break;
        }
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.firstname, u.lastname, u.birthdate, u.gender, u.seeking,
                   u.city, u.bio, u.job, u.subscription_type, u.is_banned, u.ban_reason,
                   u.is_verified, u.email_verified, u.created_at, u.last_seen,
                   (SELECT CONCAT('/uploads/photos/',filename) FROM user_photos WHERE user_id=u.id AND is_primary=1 LIMIT 1) as main_photo 
            FROM users u 
            WHERE id=?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        echo json_encode(['user' => $user ?: null]);
        break;
        
    case 'ban-user':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($data['user_id'] ?? 0);
        $reason = $data['reason'] ?? '';
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'ID requis']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE users SET is_banned=1, ban_reason=? WHERE id=?");
        echo json_encode(['success' => $stmt->execute([$reason, $userId])]);
        break;
        
    case 'unban-user':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($data['user_id'] ?? 0);
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'ID requis']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE users SET is_banned=0, ban_reason=NULL WHERE id=?");
        echo json_encode(['success' => $stmt->execute([$userId])]);
        break;
        
    case 'delete-user':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($data['user_id'] ?? 0);
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'ID requis']);
            break;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            echo json_encode(['success' => $stmt->execute([$userId])]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'subscriptions':
        $result = [
            'active_count' => 0, 
            'premium_count' => 0, 
            'vip_count' => 0, 
            'expiring_count' => 0, 
            'subscriptions' => []
        ];
        try {
            $result['active_count'] = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn();
            $result['premium_count'] = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active' AND plan_type='premium'")->fetchColumn();
            $result['vip_count'] = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active' AND plan_type='vip'")->fetchColumn();
            $result['expiring_count'] = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active' AND ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $result['subscriptions'] = $pdo->query("
                SELECT s.*, u.firstname, u.lastname, DATEDIFF(s.ends_at, NOW()) as days_remaining 
                FROM subscriptions s 
                JOIN users u ON s.user_id=u.id 
                WHERE s.status='active' 
                ORDER BY s.ends_at 
                LIMIT 100
            ")->fetchAll();
        } catch(Exception $e) {}
        echo json_encode($result);
        break;
        
    case 'extend-subscription':
        $data = json_decode(file_get_contents('php://input'), true);
        $subId = (int)($data['subscription_id'] ?? 0);
        $days = (int)($data['days'] ?? 30);
        if (!$subId) {
            echo json_encode(['success' => false, 'error' => 'ID requis']);
            break;
        }
        try {
            $stmt = $pdo->prepare("UPDATE subscriptions SET ends_at = DATE_ADD(ends_at, INTERVAL ? DAY) WHERE id=?");
            echo json_encode(['success' => $stmt->execute([$days, $subId])]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'payments':
        $payments = [];
        try { 
            $payments = $pdo->query("
                SELECT p.*, u.firstname, u.lastname, s.plan_type
                FROM payments p 
                JOIN users u ON p.user_id=u.id 
                LEFT JOIN subscriptions s ON p.subscription_id=s.id
                ORDER BY p.created_at DESC 
                LIMIT 100
            ")->fetchAll(); 
        } catch(Exception $e) {}
        echo json_encode(['payments' => $payments]);
        break;
        
    case 'matches':
        $matches = [];
        try { 
            $matches = $pdo->query("
                SELECT m.id, m.user1_id, m.user2_id, m.matched_at, m.is_active,
                       u1.firstname as user1_name, 
                       u2.firstname as user2_name,
                       (SELECT COUNT(*) FROM messages msg JOIN conversations c ON msg.conversation_id=c.id WHERE c.match_id=m.id) as message_count
                FROM matches m 
                JOIN users u1 ON m.user1_id=u1.id 
                JOIN users u2 ON m.user2_id=u2.id 
                ORDER BY m.matched_at DESC 
                LIMIT 100
            ")->fetchAll(); 
        } catch(Exception $e) {}
        echo json_encode(['matches' => $matches]);
        break;
        
    case 'reports':
        $reports = [];
        try { 
            $reports = $pdo->query("
                SELECT r.*, 
                       rep.firstname as reporter_name, 
                       rpd.firstname as reported_name 
                FROM reports r 
                JOIN users rep ON r.reporter_id=rep.id 
                JOIN users rpd ON r.reported_id=rpd.id 
                ORDER BY CASE r.status WHEN 'pending' THEN 0 ELSE 1 END, r.created_at DESC
                LIMIT 100
            ")->fetchAll(); 
        } catch(Exception $e) {}
        echo json_encode(['reports' => $reports]);
        break;
        
    case 'resolve-report':
        $data = json_decode(file_get_contents('php://input'), true);
        $reportId = (int)($data['report_id'] ?? 0);
        if (!$reportId) {
            echo json_encode(['success' => false, 'error' => 'ID requis']);
            break;
        }
        try {
            $stmt = $pdo->prepare("UPDATE reports SET status='resolved', reviewed_at=NOW() WHERE id=?");
            echo json_encode(['success' => $stmt->execute([$reportId])]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $settingsFile = __DIR__ . '/../../config/settings.json';
            $currentSettings = [];
            if (file_exists($settingsFile)) {
                $currentSettings = json_decode(file_get_contents($settingsFile), true) ?: [];
            }
            $newSettings = array_merge($currentSettings, $data);
            $result = file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT));
            echo json_encode(['success' => $result !== false]);
        } else {
            $settingsFile = __DIR__ . '/../../config/settings.json';
            $defaults = [
                'site_name' => 'Fusionel',
                'site_url' => 'https://fusionel.fr',
                'contact_email' => 'contact@fusionel.fr',
                'support_email' => 'support@fusionel.fr',
                'min_age' => 18,
                'max_age' => 99,
                'max_photos' => 6,
                'free_likes_day' => 5,
                'premium_monthly' => 9.99,
                'premium_quarterly' => 25.49,
                'premium_yearly' => 83.88,
                'vip_monthly' => 19.99,
                'vip_quarterly' => 50.99,
                'vip_yearly' => 167.88,
                'smtp_host' => 'smtp.ionos.fr',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_from_email' => 'noreply@fusionel.fr',
                'smtp_from_name' => 'Fusionel',
                'paypal_mode' => 'sandbox',
                'maintenance_mode' => false
            ];
            if (file_exists($settingsFile)) {
                $saved = json_decode(file_get_contents($settingsFile), true) ?: [];
                $settings = array_merge($defaults, $saved);
            } else {
                $settings = $defaults;
            }
            echo json_encode(['settings' => $settings]);
        }
        break;
        
    case 'export-users':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['ID', 'Email', 'Prénom', 'Nom', 'Genre', 'Ville', 'Abonnement', 'Inscrit le', 'Banni']);
        $stmt = $pdo->query("SELECT id, email, firstname, lastname, gender, city, subscription_type, created_at, is_banned FROM users ORDER BY created_at DESC");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'], $row['email'], $row['firstname'], $row['lastname'],
                $row['gender'], $row['city'], $row['subscription_type'] ?? 'free',
                $row['created_at'], $row['is_banned'] ? 'Oui' : 'Non'
            ]);
        }
        fclose($output);
        exit;
        
    case 'clear-cache':
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        echo json_encode(['success' => true, 'message' => 'Cache vidé']);
        break;
        
    case 'optimize-db':
        try {
            $tables = ['users', 'user_photos', 'likes', 'matches', 'messages', 'conversations'];
            foreach ($tables as $table) {
                try { $pdo->exec("OPTIMIZE TABLE $table"); } catch(Exception $e) {}
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'backup-db':
        echo json_encode(['success' => false, 'error' => 'Utilisez phpMyAdmin pour les sauvegardes']);
        break;
        
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Email et mot de passe requis']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
                echo json_encode(['success' => true, 'admin' => ['id' => $admin['id'], 'email' => $admin['email'], 'firstname' => $admin['firstname']]]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Identifiants incorrects']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['status' => 'ok', 'message' => 'API Admin Fusionel', 'route' => $route]);
}
