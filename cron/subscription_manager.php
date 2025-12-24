<?php
/**
 * FUSIONEL - CRON de gestion des abonnements
 * 
 * Emplacement: /cron/subscription_manager.php
 * 
 * Crontab: 0 0 * * * /usr/bin/php /chemin/vers/fusionel/cron/subscription_manager.php
 */

// Définir les chemins
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('LOG_FILE', '/var/log/fusionel/subscription_cron.log');

// Charger les dépendances
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/config/database.php';
require_once PUBLIC_PATH . '/lib/Mailer.php';

function logMessage($message, $level = 'INFO') {
    $date = date('Y-m-d H:i:s');
    $log = "[$date] [$level] $message" . PHP_EOL;
    echo $log;
    @file_put_contents(LOG_FILE, $log, FILE_APPEND);
}

logMessage("=== Démarrage du CRON des abonnements ===");

try {
    $pdo = db();
    $mailer = new Mailer();
    
    // 1. EXPIRER LES ABONNEMENTS TERMINÉS
    logMessage("Vérification des abonnements expirés...");
    
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.firstname 
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active' 
        AND s.ends_at < NOW()
        AND s.plan_type != 'free'
    ");
    $stmt->execute();
    $expiredSubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expiredSubs as $sub) {
        // Marquer comme expiré
        $pdo->prepare("UPDATE subscriptions SET status = 'expired', updated_at = NOW() WHERE id = ?")->execute([$sub['id']]);
        
        // Mettre à jour l'utilisateur
        $pdo->prepare("UPDATE users SET subscription_type = 'free', is_premium = 0, is_vip = 0, subscription_end_date = NULL WHERE id = ?")->execute([$sub['user_id']]);
        
        // Historique
        $pdo->prepare("INSERT INTO subscription_history (user_id, subscription_id, action, old_plan, new_plan, old_status, new_status, notes) VALUES (?, ?, 'expired', ?, 'free', 'active', 'expired', 'Expiration automatique')")->execute([$sub['user_id'], $sub['id'], $sub['plan_type']]);
        
        // Envoyer email d'expiration
        $mailer->sendTemplate($sub['email'], 'expired', [
            'firstname' => $sub['firstname'],
            'plan_type' => $sub['plan_type']
        ]);
        
        logMessage("Abonnement expiré: user_id={$sub['user_id']}, plan={$sub['plan_type']}");
    }
    
    if (count($expiredSubs) > 0) {
        logMessage(count($expiredSubs) . " abonnement(s) expiré(s)");
    }
    
    // 2. ENVOYER LES RAPPELS DE RENOUVELLEMENT
    logMessage("Vérification des rappels à envoyer...");
    
    $reminders = [
        7 => 'renewal_7days',
        3 => 'renewal_3days', 
        1 => 'renewal_1day'
    ];
    
    foreach ($reminders as $days => $reminderType) {
        sendReminders($pdo, $mailer, $days, $reminderType);
    }
    
    // 3. RÉINITIALISER LES COMPTEURS
    logMessage("Réinitialisation des compteurs...");
    
    $pdo->exec("UPDATE users SET likes_today = 0, likes_reset_date = CURDATE() WHERE likes_reset_date < CURDATE() OR likes_reset_date IS NULL");
    
    if (date('N') == 1) {
        $pdo->exec("UPDATE users SET super_likes_this_week = 0, super_likes_reset_date = CURDATE()");
        logMessage("Super likes réinitialisés (lundi)");
    }
    
    if (date('j') == 1) {
        $pdo->exec("UPDATE users SET boosts_this_month = 0, boosts_reset_date = CURDATE()");
        logMessage("Boosts réinitialisés (1er du mois)");
    }
    
    // 4. STATISTIQUES
    $stats = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'active' AND plan_type = 'premium' THEN 1 ELSE 0 END) as premium_active,
            SUM(CASE WHEN status = 'active' AND plan_type = 'vip' THEN 1 ELSE 0 END) as vip_active
        FROM subscriptions
    ")->fetch(PDO::FETCH_ASSOC);
    
    logMessage("Stats: Premium actifs=" . ($stats['premium_active'] ?? 0) . ", VIP actifs=" . ($stats['vip_active'] ?? 0));
    logMessage("=== CRON terminé avec succès ===");
    
} catch (Exception $e) {
    logMessage("ERREUR: " . $e->getMessage(), 'ERROR');
    exit(1);
}

function sendReminders($pdo, $mailer, $daysRemaining, $reminderType) {
    $stmt = $pdo->prepare("
        SELECT s.id as subscription_id, s.user_id, s.plan_type, s.ends_at, s.price, s.billing_period,
               u.email, u.firstname
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active'
        AND s.plan_type != 'free'
        AND DATEDIFF(s.ends_at, NOW()) = ?
        AND NOT EXISTS (
            SELECT 1 FROM subscription_reminders sr 
            WHERE sr.subscription_id = s.id 
            AND sr.reminder_type = ?
        )
    ");
    $stmt->execute([$daysRemaining, $reminderType]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subscriptions as $sub) {
        try {
            // Envoyer l'email
            $emailSent = $mailer->sendTemplate($sub['email'], $reminderType, [
                'firstname' => $sub['firstname'],
                'plan_type' => $sub['plan_type'],
                'end_date' => $sub['ends_at'],
                'price' => $sub['price'],
                'billing_period' => $sub['billing_period']
            ]);
            
            // Enregistrer le rappel
            $pdo->prepare("
                INSERT INTO subscription_reminders 
                (user_id, subscription_id, reminder_type, email_sent, email_sent_at, email_to, notification_sent, notification_sent_at)
                VALUES (?, ?, ?, ?, NOW(), ?, 1, NOW())
            ")->execute([
                $sub['user_id'],
                $sub['subscription_id'],
                $reminderType,
                $emailSent ? 1 : 0,
                $sub['email']
            ]);
            
            logMessage("Rappel '$reminderType' envoyé à {$sub['email']} - " . ($emailSent ? 'OK' : 'ECHEC'));
            
        } catch (Exception $e) {
            logMessage("Erreur rappel user_id={$sub['user_id']}: " . $e->getMessage(), 'ERROR');
        }
    }
    
    $count = count($subscriptions);
    if ($count > 0) {
        logMessage("$count rappel(s) '$reminderType' traité(s)");
    }
}
