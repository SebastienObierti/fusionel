<?php
/**
 * Fusionel.fr - API REST avec PayPal Subscription
 */

if (session_status() === PHP_SESSION_NONE) session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

function respond($data, $status = 200) { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit(); }
function error($message, $status = 400) { respond(['success' => false, 'error' => $message], $status); }

$configPath = dirname(dirname(__DIR__)) . '/config/database.php';
if (!file_exists($configPath)) error('Config introuvable', 500);
require_once $configPath;

try { $pdo = db(); } catch (Exception $e) { error('Erreur BDD', 500); }

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $path);
$path = trim($path, '/');
$path = preg_replace('/^index\.php\/?/', '', $path);

$segments = $path ? explode('/', $path) : [];
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function requireAuth() { if (!isset($_SESSION['user_id'])) error('Non authentifié', 401); return $_SESSION['user_id']; }
function getCurrentUser() { if (!isset($_SESSION['user_id'])) return null; global $pdo; $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$_SESSION['user_id']]); $u = $stmt->fetch(PDO::FETCH_ASSOC); if ($u) unset($u['password']); return $u; }

$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';

try {
    switch ($resource) {
        
        // ==================== AUTH ====================
        case 'auth':
            switch ($action) {
                case 'register':
                    if ($method !== 'POST') error('Méthode non autorisée', 405);
                    $errors = [];
                    if (empty($input['firstname'])) $errors['firstname'] = 'Prénom requis';
                    if (empty($input['email'])) $errors['email'] = 'Email requis';
                    elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalide';
                    if (empty($input['password'])) $errors['password'] = 'Mot de passe requis';
                    elseif (strlen($input['password']) < 8) $errors['password'] = 'Minimum 8 caractères';
                    if (empty($input['gender'])) $errors['gender'] = 'Genre requis';
                    if (empty($input['seeking'])) $errors['seeking'] = 'Préférence requise';
                    $age = isset($input['age']) ? (int)$input['age'] : 0;
                    if ($age < 18) $errors['age'] = '18 ans minimum';
                    if (empty($errors['email'])) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([strtolower(trim($input['email']))]);
                        if ($stmt->fetch()) $errors['email'] = 'Email déjà utilisé';
                    }
                    if (!empty($errors)) respond(['success' => false, 'errors' => $errors], 400);
                    $birthdate = date('Y-m-d', strtotime("-{$age} years"));
                    $stmt = $pdo->prepare("INSERT INTO users (firstname, email, password, gender, seeking, birthdate, city) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([trim($input['firstname']), strtolower(trim($input['email'])), password_hash($input['password'], PASSWORD_DEFAULT), $input['gender'], $input['seeking'], $birthdate, $input['city'] ?? 'Non renseigné']);
                    $userId = $pdo->lastInsertId();
                    try { $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?)")->execute([$userId]); } catch (Exception $e) {}
                    $_SESSION['user_id'] = $userId;
                    $stmt = $pdo->prepare("SELECT id, firstname, email, gender, city, subscription_type FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    respond(['success' => true, 'user' => $stmt->fetch(PDO::FETCH_ASSOC)], 201);
                    break;
                case 'login':
                    if ($method !== 'POST') error('Méthode non autorisée', 405);
                    if (empty($input['email']) || empty($input['password'])) error('Email et mot de passe requis');
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([strtolower(trim($input['email']))]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user || !password_verify($input['password'], $user['password'])) error('Identifiants incorrects', 401);
                    if (!empty($user['is_banned'])) error('Compte suspendu', 403);
                    $pdo->prepare("UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
                    $_SESSION['user_id'] = $user['id'];
                    unset($user['password']);
                    respond(['success' => true, 'user' => $user]);
                    break;
                case 'logout':
                    if (isset($_SESSION['user_id'])) $pdo->prepare("UPDATE users SET is_online = 0 WHERE id = ?")->execute([$_SESSION['user_id']]);
                    $_SESSION = []; session_destroy();
                    respond(['success' => true]);
                    break;
                case 'me':
                    respond(['user' => getCurrentUser()]);
                    break;
                case 'check':
                    respond(['authenticated' => isset($_SESSION['user_id'])]);
                    break;
                case 'forgot-password':
                    respond(['success' => true, 'message' => 'Si cet email existe, un lien a été envoyé']);
                    break;
                case 'change-password':
                    if ($method !== 'POST') error('Méthode non autorisée', 405);
                    $userId = requireAuth();
                    if (empty($input['current_password']) || empty($input['new_password'])) error('Mots de passe requis');
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?"); $stmt->execute([$userId]);
                    if (!password_verify($input['current_password'], $stmt->fetchColumn())) error('Mot de passe actuel incorrect');
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($input['new_password'], PASSWORD_DEFAULT), $userId]);
                    respond(['success' => true]);
                    break;
                default: error('Endpoint non trouvé', 404);
            }
            break;

        // ==================== USER ====================
        case 'user':
            $userId = requireAuth();
            switch ($action) {
                case 'profile': case '':
                    if ($method === 'GET') { respond(['user' => getCurrentUser()]); }
                    else {
                        $fields = ['firstname','lastname','bio','city','job','company','education','height','body_type','smoking','drinking','children','wants_children','religion'];
                        $updates = []; $values = [];
                        foreach ($fields as $f) { if (isset($input[$f])) { $updates[] = "$f = ?"; $values[] = $input[$f] ?: null; } }
                        if ($updates) { $values[] = $userId; $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute($values); }
                        respond(['success' => true]);
                    }
                    break;
                case 'preferences':
                    if ($method === 'GET') {
                        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?"); $stmt->execute([$userId]);
                        respond(['preferences' => $stmt->fetch(PDO::FETCH_ASSOC) ?: []]);
                    } else {
                        $stmt = $pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?"); $stmt->execute([$userId]);
                        if ($stmt->fetch()) {
                            $pdo->prepare("UPDATE user_preferences SET min_age=?, max_age=?, max_distance=?, show_with_photo_only=?, show_verified_only=? WHERE user_id=?")->execute([$input['min_age']??18, $input['max_age']??50, $input['max_distance']??50, !empty($input['show_with_photo_only'])?1:0, !empty($input['show_verified_only'])?1:0, $userId]);
                        } else {
                            $pdo->prepare("INSERT INTO user_preferences (min_age,max_age,max_distance,show_with_photo_only,show_verified_only,user_id) VALUES(?,?,?,?,?,?)")->execute([$input['min_age']??18, $input['max_age']??50, $input['max_distance']??50, !empty($input['show_with_photo_only'])?1:0, !empty($input['show_verified_only'])?1:0, $userId]);
                        }
                        respond(['success' => true]);
                    }
                    break;
                case 'stats':
                    $stats = [];
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE liked_id = ?"); $stmt->execute([$userId]); $stats['likes_received'] = (int)$stmt->fetchColumn();
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE (user1_id = ? OR user2_id = ?) AND is_active = 1"); $stmt->execute([$userId, $userId]); $stats['matches'] = (int)$stmt->fetchColumn();
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_views WHERE viewed_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"); $stmt->execute([$userId]); $stats['profile_views'] = (int)$stmt->fetchColumn();
                    respond(['stats' => $stats]);
                    break;
                case 'delete':
                    $pdo->prepare("UPDATE users SET is_banned = 1, email = CONCAT('deleted_', id, '@deleted.com') WHERE id = ?")->execute([$userId]);
                    session_destroy();
                    respond(['success' => true]);
                    break;
                default: error('Endpoint non trouvé', 404);
            }
            break;

        // ==================== SUBSCRIPTION (PayPal) ====================
        case 'subscription':
            $userId = requireAuth();
            
            switch ($action) {
                case 'activate':
                    if ($method !== 'POST') error('Méthode non autorisée', 405);
                    
                    $plan = $input['plan'] ?? '';
                    $period = $input['period'] ?? 'monthly';
                    $paypalOrderId = $input['paypal_order_id'] ?? '';
                    $paypalPayerId = $input['paypal_payer_id'] ?? '';
                    $amount = floatval($input['amount'] ?? 0);
                    
                    if (!in_array($plan, ['premium', 'vip'])) error('Plan invalide');
                    if (!$paypalOrderId) error('PayPal order_id requis');
                    
                    // Calculer la date de fin
                    $duration = $period === 'yearly' ? '+1 year' : ($period === 'quarterly' ? '+3 months' : '+1 month');
                    $endsAt = date('Y-m-d H:i:s', strtotime($duration));
                    
                    // Enregistrer le paiement
                    $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, currency, payment_method, payment_provider, transaction_id, status, description) VALUES (?, ?, 'EUR', 'paypal', 'PayPal', ?, 'completed', ?)");
                    $stmt->execute([$userId, $amount, $paypalOrderId, "Abonnement $plan - $period"]);
                    $paymentId = $pdo->lastInsertId();
                    
                    // Créer ou mettre à jour l'abonnement
                    $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, plan_type, price, billing_period, status, payment_id, starts_at, ends_at) VALUES (?, ?, ?, ?, 'active', ?, NOW(), ?)");
                    $stmt->execute([$userId, $plan, $amount, $period, $paypalOrderId, $endsAt]);
                    $subscriptionId = $pdo->lastInsertId();
                    
                    // Mettre à jour le profil utilisateur
                    $stmt = $pdo->prepare("UPDATE users SET subscription_type = ?, is_premium = ?, is_vip = ?, subscription_end_date = ? WHERE id = ?");
                    $stmt->execute([$plan, $plan === 'premium' || $plan === 'vip' ? 1 : 0, $plan === 'vip' ? 1 : 0, $endsAt, $userId]);
                    
                    respond([
                        'success' => true,
                        'subscription' => [
                            'id' => $subscriptionId,
                            'plan' => $plan,
                            'ends_at' => $endsAt
                        ]
                    ]);
                    break;
                    
                case 'status':
                    $user = getCurrentUser();
                    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$userId]);
                    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    respond([
                        'subscription_type' => $user['subscription_type'] ?? 'free',
                        'is_premium' => !empty($user['is_premium']),
                        'is_vip' => !empty($user['is_vip']),
                        'ends_at' => $user['subscription_end_date'],
                        'subscription' => $subscription
                    ]);
                    break;
                    
                case 'cancel':
                    if ($method !== 'POST') error('Méthode non autorisée', 405);
                    
                    $pdo->prepare("UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW() WHERE user_id = ? AND status = 'active'")->execute([$userId]);
                    // Ne pas révoquer immédiatement - l'utilisateur garde l'accès jusqu'à la fin
                    
                    respond(['success' => true, 'message' => 'Abonnement annulé. Vous conservez l\'accès jusqu\'à la fin de la période.']);
                    break;
                    
                default:
                    error('Endpoint non trouvé', 404);
            }
            break;

        // ==================== DISCOVER ====================
        case 'discover':
            $userId = requireAuth();
            $user = getCurrentUser();
            if ($action === 'interests') { respond(['interests' => $pdo->query("SELECT * FROM interests ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC)]); }
            $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?"); $stmt->execute([$userId]);
            $prefs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $seeking = $user['seeking'] ?? 'femme';
            $stmt = $pdo->prepare("SELECT u.id, u.firstname, u.birthdate, u.city, u.bio, u.gender, u.is_online, u.is_verified, (SELECT filename FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as photo FROM users u WHERE u.id != ? AND u.gender = ? AND u.is_banned = 0 AND TIMESTAMPDIFF(YEAR, u.birthdate, CURDATE()) BETWEEN ? AND ? AND u.id NOT IN (SELECT liked_id FROM likes WHERE liker_id = ?) AND u.id NOT IN (SELECT disliked_id FROM dislikes WHERE disliker_id = ?) ORDER BY u.is_online DESC, RAND() LIMIT 20");
            $stmt->execute([$userId, $seeking, $prefs['min_age']??18, $prefs['max_age']??99, $userId, $userId]);
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($profiles as &$p) { if ($p['birthdate']) $p['age'] = (new DateTime($p['birthdate']))->diff(new DateTime())->y; }
            respond(['profiles' => $profiles]);
            break;

        // ==================== LIKES ====================
        case 'likes':
            $userId = requireAuth();
            if ($action === 'send' || ($action === '' && $method === 'POST')) {
                if (empty($input['user_id'])) error('user_id requis');
                $likedId = (int)$input['user_id'];
                $stmt = $pdo->prepare("SELECT id FROM likes WHERE liker_id = ? AND liked_id = ?"); $stmt->execute([$userId, $likedId]);
                if ($stmt->fetch()) error('Déjà liké');
                $pdo->prepare("INSERT INTO likes (liker_id, liked_id, is_super_like, created_at) VALUES (?, ?, ?, NOW())")->execute([$userId, $likedId, !empty($input['super_like']) ? 1 : 0]);
                $stmt = $pdo->prepare("SELECT id FROM likes WHERE liker_id = ? AND liked_id = ?"); $stmt->execute([$likedId, $userId]);
                $isMatch = (bool)$stmt->fetch();
                $matchId = null;
                if ($isMatch) {
                    $minId = min($userId, $likedId); $maxId = max($userId, $likedId);
                    $stmt = $pdo->prepare("SELECT id FROM matches WHERE user1_id = ? AND user2_id = ?"); $stmt->execute([$minId, $maxId]);
                    if (!$stmt->fetch()) {
                        $pdo->prepare("INSERT INTO matches (user1_id, user2_id, created_at, is_active) VALUES (?, ?, NOW(), 1)")->execute([$minId, $maxId]);
                        $matchId = $pdo->lastInsertId();
                        $pdo->prepare("INSERT INTO conversations (match_id, created_at) VALUES (?, NOW())")->execute([$matchId]);
                    }
                }
                respond(['success' => true, 'is_match' => $isMatch, 'match_id' => $matchId]);
            } elseif ($action === 'pass') {
                if (empty($input['user_id'])) error('user_id requis');
                $pdo->prepare("INSERT INTO dislikes (disliker_id, disliked_id, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE created_at = NOW()")->execute([$userId, (int)$input['user_id']]);
                respond(['success' => true]);
            } elseif ($action === 'limits') {
                $user = getCurrentUser();
                $limit = in_array($user['subscription_type'] ?? '', ['premium', 'vip']) ? 999999 : 5;
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE liker_id = ? AND DATE(created_at) = CURDATE()"); $stmt->execute([$userId]);
                respond(['limits' => ['remaining' => max(0, $limit - (int)$stmt->fetchColumn()), 'limit' => $limit]]);
            } else {
                $stmt = $pdo->prepare("SELECT u.id, u.firstname, u.birthdate, u.city, u.is_online, l.is_super_like, l.created_at as liked_at, (SELECT filename FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as photo FROM likes l JOIN users u ON l.liker_id = u.id WHERE l.liked_id = ? AND l.liker_id NOT IN (SELECT liked_id FROM likes WHERE liker_id = ?) AND u.is_banned = 0 ORDER BY l.created_at DESC");
                $stmt->execute([$userId, $userId]);
                respond(['likes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
            break;

        // ==================== MATCHES ====================
        case 'matches':
            $userId = requireAuth();
            $stmt = $pdo->prepare("SELECT m.id as match_id, m.created_at as matched_at, CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END as id, CASE WHEN m.user1_id = ? THEN u2.firstname ELSE u1.firstname END as firstname, CASE WHEN m.user1_id = ? THEN u2.birthdate ELSE u1.birthdate END as birthdate, CASE WHEN m.user1_id = ? THEN u2.city ELSE u1.city END as city, CASE WHEN m.user1_id = ? THEN u2.is_online ELSE u1.is_online END as is_online, c.id as conversation_id, (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message, (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND read_at IS NULL) as unread_count, (SELECT filename FROM user_photos WHERE user_id = CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END AND is_primary = 1 LIMIT 1) as photo FROM matches m JOIN users u1 ON m.user1_id = u1.id JOIN users u2 ON m.user2_id = u2.id LEFT JOIN conversations c ON c.match_id = m.id WHERE (m.user1_id = ? OR m.user2_id = ?) AND m.is_active = 1 ORDER BY m.created_at DESC");
            $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($matches as &$m) { if ($m['birthdate']) $m['age'] = (new DateTime($m['birthdate']))->diff(new DateTime())->y; }
            respond(['matches' => $matches]);
            break;

        // ==================== CONVERSATIONS ====================
        case 'conversations':
            $userId = requireAuth();
            if ($action === '' || $action === null) {
                $stmt = $pdo->prepare("SELECT c.id, c.match_id, CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END as other_user_id, CASE WHEN m.user1_id = ? THEN u2.firstname ELSE u1.firstname END as firstname, CASE WHEN m.user1_id = ? THEN u2.is_online ELSE u1.is_online END as is_online, (SELECT filename FROM user_photos WHERE user_id = CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END AND is_primary = 1 LIMIT 1) as other_photo, (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message, (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_at, (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND read_at IS NULL) as unread_count FROM conversations c JOIN matches m ON c.match_id = m.id JOIN users u1 ON m.user1_id = u1.id JOIN users u2 ON m.user2_id = u2.id WHERE (m.user1_id = ? OR m.user2_id = ?) AND m.is_active = 1 ORDER BY last_message_at DESC");
                $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
                respond(['conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } else {
                $convId = (int)$action;
                if ($method === 'GET') {
                    $stmt = $pdo->prepare("SELECT c.*, CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END as other_user_id, CASE WHEN m.user1_id = ? THEN u2.firstname ELSE u1.firstname END as firstname, CASE WHEN m.user1_id = ? THEN u2.is_online ELSE u1.is_online END as is_online, (SELECT filename FROM user_photos WHERE user_id = CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END AND is_primary = 1 LIMIT 1) as other_photo FROM conversations c JOIN matches m ON c.match_id = m.id JOIN users u1 ON m.user1_id = u1.id JOIN users u2 ON m.user2_id = u2.id WHERE c.id = ? AND (m.user1_id = ? OR m.user2_id = ?)");
                    $stmt->execute([$userId, $userId, $userId, $userId, $convId, $userId, $userId]);
                    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$conv) error('Conversation non trouvée', 404);
                    $msgs = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC"); $msgs->execute([$convId]);
                    $pdo->prepare("UPDATE messages SET read_at = NOW() WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL")->execute([$convId, $userId]);
                    respond(['conversation' => $conv, 'messages' => $msgs->fetchAll(PDO::FETCH_ASSOC)]);
                } else {
                    if (empty($input['content'])) error('Message vide');
                    $stmt = $pdo->prepare("SELECT c.id FROM conversations c JOIN matches m ON c.match_id = m.id WHERE c.id = ? AND (m.user1_id = ? OR m.user2_id = ?)"); $stmt->execute([$convId, $userId, $userId]);
                    if (!$stmt->fetch()) error('Conversation non trouvée', 404);
                    $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content, created_at) VALUES (?, ?, ?, NOW())")->execute([$convId, $userId, $input['content']]);
                    $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);
                    respond(['success' => true, 'message_id' => $pdo->lastInsertId()]);
                }
            }
            break;

        // ==================== NOTIFICATIONS ====================
        case 'notifications':
            $userId = requireAuth();
            if ($action === 'unread') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0"); $stmt->execute([$userId]);
                respond($stmt->fetch(PDO::FETCH_ASSOC));
            }
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50"); $stmt->execute([$userId]);
            respond(['notifications' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ==================== PHOTOS ====================
        case 'photos':
            $userId = requireAuth();
            if ($method === 'GET' && ($action === '' || $action === null)) {
                $stmt = $pdo->prepare("SELECT * FROM user_photos WHERE user_id = ? ORDER BY is_primary DESC, order_position ASC"); $stmt->execute([$userId]);
                respond(['photos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
            if ($action === 'upload' && $method === 'POST') {
                if (!isset($_FILES['photo'])) error('Aucun fichier');
                $file = $_FILES['photo'];
                if ($file['error'] !== UPLOAD_ERR_OK) error('Erreur upload');
                if (!in_array($file['type'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) error('Type non autorisé');
                if ($file['size'] > 10 * 1024 * 1024) error('Fichier trop gros');
                $uploadDir = dirname(__DIR__) . '/uploads/photos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
                $filename = 'photo_' . $userId . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_photos WHERE user_id = ?"); $stmt->execute([$userId]);
                    $count = (int)$stmt->fetchColumn();
                    $pdo->prepare("INSERT INTO user_photos (user_id, filename, filepath, is_primary, order_position, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$userId, $filename, '/uploads/photos/' . $filename, $count === 0 ? 1 : 0, $count + 1]);
                    $photoId = $pdo->lastInsertId();
                    if ($count === 0) $pdo->prepare("UPDATE users SET profile_photo_id = ? WHERE id = ?")->execute([$photoId, $userId]);
                    respond(['success' => true, 'photo_id' => $photoId, 'filename' => $filename]);
                }
                error('Erreur upload');
            }
            if ($action === 'delete' && $method === 'POST') {
                $photoId = (int)($input['photo_id'] ?? 0);
                if (!$photoId) error('photo_id requis');
                $stmt = $pdo->prepare("SELECT * FROM user_photos WHERE id = ? AND user_id = ?"); $stmt->execute([$photoId, $userId]);
                $photo = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$photo) error('Photo non trouvée', 404);
                $filePath = dirname(__DIR__) . '/uploads/photos/' . $photo['filename'];
                if (file_exists($filePath)) unlink($filePath);
                $pdo->prepare("DELETE FROM user_photos WHERE id = ?")->execute([$photoId]);
                if ($photo['is_primary']) {
                    $stmt = $pdo->prepare("SELECT id FROM user_photos WHERE user_id = ? ORDER BY order_position ASC LIMIT 1"); $stmt->execute([$userId]);
                    $newPrimary = $stmt->fetch();
                    if ($newPrimary) {
                        $pdo->prepare("UPDATE user_photos SET is_primary = 1 WHERE id = ?")->execute([$newPrimary['id']]);
                        $pdo->prepare("UPDATE users SET profile_photo_id = ? WHERE id = ?")->execute([$newPrimary['id'], $userId]);
                    } else {
                        $pdo->prepare("UPDATE users SET profile_photo_id = NULL WHERE id = ?")->execute([$userId]);
                    }
                }
                respond(['success' => true]);
            }
            if ($action === 'primary' && $method === 'POST') {
                $photoId = (int)($input['photo_id'] ?? 0);
                if (!$photoId) error('photo_id requis');
                $stmt = $pdo->prepare("SELECT id FROM user_photos WHERE id = ? AND user_id = ?"); $stmt->execute([$photoId, $userId]);
                if (!$stmt->fetch()) error('Photo non trouvée', 404);
                $pdo->prepare("UPDATE user_photos SET is_primary = 0 WHERE user_id = ?")->execute([$userId]);
                $pdo->prepare("UPDATE user_photos SET is_primary = 1 WHERE id = ?")->execute([$photoId]);
                $pdo->prepare("UPDATE users SET profile_photo_id = ? WHERE id = ?")->execute([$photoId, $userId]);
                respond(['success' => true]);
            }
            error('Endpoint photos non trouvé', 404);
            break;

        default:
            if ($resource === '') respond(['api' => 'Fusionel API', 'version' => '1.0', 'status' => 'ok']);
            error('Route non trouvée', 404);
    }
} catch (PDOException $e) {
    error_log("API Error: " . $e->getMessage());
    error('Erreur BDD: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    error('Erreur serveur', 500);
}
