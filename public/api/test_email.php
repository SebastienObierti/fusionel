<?php
/**
 * Test d'envoi d'email
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$rootDir = dirname(dirname(__DIR__));
$configDir = $rootDir . '/config';
$publicDir = dirname(__DIR__);

// Charger PHPMailer
$autoload = $rootDir . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die(json_encode(['success' => false, 'error' => 'PHPMailer non installé. Exécutez: composer require phpmailer/phpmailer']));
}
require_once $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email destinataire
$to = $_GET['to'] ?? '';
if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    die(json_encode([
        'success' => false, 
        'error' => 'Email invalide. Usage: ?to=votre@email.com'
    ]));
}

// Charger config email
$emailConfigFile = $configDir . '/email.php';
$settingsFile = $configDir . '/settings.json';

// Essayer de charger depuis settings.json d'abord
$smtpConfig = null;
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!empty($settings['smtp_host']) && !empty($settings['smtp_username'])) {
        $smtpConfig = [
            'host' => $settings['smtp_host'],
            'port' => intval($settings['smtp_port'] ?? 587),
            'encryption' => $settings['smtp_encryption'] ?? 'tls',
            'username' => $settings['smtp_username'],
            'password' => $settings['smtp_password'],
            'from_email' => $settings['smtp_from_email'] ?? $settings['smtp_username'],
            'from_name' => $settings['smtp_from_name'] ?? 'Fusionel'
        ];
    }
}

// Sinon charger depuis email.php
if (!$smtpConfig && file_exists($emailConfigFile)) {
    $config = require $emailConfigFile;
    if (is_array($config) && isset($config['smtp'])) {
        $smtpConfig = [
            'host' => $config['smtp']['host'] ?? '',
            'port' => $config['smtp']['port'] ?? 587,
            'encryption' => $config['smtp']['encryption'] ?? 'tls',
            'username' => $config['smtp']['username'] ?? '',
            'password' => $config['smtp']['password'] ?? '',
            'from_email' => $config['from']['email'] ?? $config['smtp']['username'],
            'from_name' => $config['from']['name'] ?? 'Fusionel'
        ];
    }
}

if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['username'])) {
    die(json_encode([
        'success' => false, 
        'error' => 'Configuration SMTP manquante. Configurez les paramètres email dans l\'admin.',
        'settings_file' => $settingsFile,
        'email_config_file' => $emailConfigFile,
        'settings_exists' => file_exists($settingsFile),
        'email_config_exists' => file_exists($emailConfigFile)
    ]));
}

// Envoyer l'email
$mail = new PHPMailer(true);

try {
    // Config SMTP
    $mail->isSMTP();
    $mail->Host = $smtpConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtpConfig['username'];
    $mail->Password = $smtpConfig['password'];
    $mail->Port = $smtpConfig['port'];
    
    if ($smtpConfig['encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($smtpConfig['encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }
    
    $mail->CharSet = 'UTF-8';
    
    // Expéditeur / Destinataire
    $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
    $mail->addAddress($to);
    
    // Contenu
    $mail->isHTML(true);
    $mail->Subject = '🧪 Test Email Fusionel - ' . date('H:i:s');
    $mail->Body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;">
            <h1 style="color: white; margin: 0;">💕 Fusionel</h1>
        </div>
        <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
            <h2 style="color: #333;">✅ Test réussi !</h2>
            <p style="color: #666;">Cet email confirme que votre configuration SMTP fonctionne correctement.</p>
            <p style="color: #999; font-size: 12px;">
                Serveur: ' . htmlspecialchars($smtpConfig['host']) . '<br>
                Port: ' . $smtpConfig['port'] . '<br>
                Date: ' . date('d/m/Y H:i:s') . '
            </p>
        </div>
    </div>';
    $mail->AltBody = 'Test email Fusionel - Configuration SMTP OK - ' . date('d/m/Y H:i:s');
    
    $mail->send();
    
    echo json_encode([
        'success' => true,
        'message' => 'Email envoyé à ' . $to,
        'smtp_host' => $smtpConfig['host'],
        'smtp_port' => $smtpConfig['port']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $mail->ErrorInfo,
        'smtp_host' => $smtpConfig['host'],
        'smtp_port' => $smtpConfig['port']
    ]);
}
