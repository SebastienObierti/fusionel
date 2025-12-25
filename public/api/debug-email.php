<?php
/**
 * Debug Email - Fusionel
 * Test l'envoi d'emails
 * 
 * Accès: https://fusionel.fr/api/debug-email.php
 * ⚠️ SUPPRIMER APRÈS UTILISATION
 */

// Affichage HTML pour debug
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>🔍 Debug Email - Fusionel</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        h1 { color: #ff6b6b; }
        .section { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section h2 { margin-top: 0; color: #333; border-bottom: 2px solid #ff6b6b; padding-bottom: 10px; }
        .success { color: #00b894; font-weight: bold; }
        .error { color: #d63031; font-weight: bold; }
        .warning { color: #fdcb6e; font-weight: bold; }
        .info { color: #0984e3; }
        pre { background: #2d3436; color: #dfe6e9; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; }
        code { background: #eee; padding: 2px 6px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .btn { display: inline-block; padding: 12px 25px; background: #ff6b6b; color: white; text-decoration: none; border-radius: 25px; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #ff5252; }
        form { margin: 20px 0; }
        input[type="email"] { padding: 12px 15px; border: 2px solid #ddd; border-radius: 8px; width: 300px; font-size: 14px; }
        input[type="email"]:focus { outline: none; border-color: #ff6b6b; }
    </style>
</head>
<body>
    <h1>🔍 Debug Email - Fusionel</h1>

<?php

echo '<div class="section">';
echo '<h2>1️⃣ Informations PHP</h2>';
echo '<table>';
echo '<tr><th>Version PHP</th><td>' . phpversion() . '</td></tr>';
echo '<tr><th>Chemin actuel</th><td>' . __DIR__ . '</td></tr>';
echo '<tr><th>Serveur</th><td>' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</td></tr>';
echo '</table>';
echo '</div>';

// Vérifier la config email
echo '<div class="section">';
echo '<h2>2️⃣ Configuration Email</h2>';

$configPath = __DIR__ . '/../config/email.php';
$configPath2 = dirname(__DIR__) . '/config/email.php';
$configPath3 = dirname(dirname(__DIR__)) . '/config/email.php';

$configFound = false;
$configFile = null;

foreach ([$configPath, $configPath2, $configPath3] as $path) {
    $realPath = realpath($path);
    if (file_exists($path)) {
        echo "<p class='success'>✅ Config trouvée: $path</p>";
        $configFile = $path;
        $configFound = true;
        break;
    } else {
        echo "<p class='error'>❌ Non trouvé: $path</p>";
    }
}

if (!$configFound) {
    echo "<p class='error'>❌ Aucun fichier de configuration email trouvé!</p>";
    echo "<p>Créez le fichier <code>/srv/web/fusionel/config/email.php</code></p>";
}
echo '</div>';

// Afficher la config (sans mot de passe)
if ($configFound && $configFile) {
    echo '<div class="section">';
    echo '<h2>3️⃣ Paramètres SMTP</h2>';
    
    // Charger la config
    $emailConfig = [];
    
    // Vérifier si c'est un fichier PHP avec constantes ou tableau
    $configContent = file_get_contents($configFile);
    
    // Essayer d'inclure le fichier
    try {
        include_once $configFile;
        
        // Vérifier les constantes
        $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : (defined('MAIL_HOST') ? MAIL_HOST : null);
        $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : (defined('MAIL_PORT') ? MAIL_PORT : null);
        $smtpUser = defined('SMTP_USER') ? SMTP_USER : (defined('MAIL_USERNAME') ? MAIL_USERNAME : null);
        $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : (defined('MAIL_PASSWORD') ? MAIL_PASSWORD : null);
        $smtpFrom = defined('SMTP_FROM') ? SMTP_FROM : (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : null);
        $smtpFromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Fusionel');
        $smtpEncryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : (defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls');
        
        echo '<table>';
        echo '<tr><th>SMTP Host</th><td>' . ($smtpHost ? "<span class='success'>$smtpHost</span>" : "<span class='error'>Non défini</span>") . '</td></tr>';
        echo '<tr><th>SMTP Port</th><td>' . ($smtpPort ? "<span class='success'>$smtpPort</span>" : "<span class='error'>Non défini</span>") . '</td></tr>';
        echo '<tr><th>SMTP User</th><td>' . ($smtpUser ? "<span class='success'>$smtpUser</span>" : "<span class='error'>Non défini</span>") . '</td></tr>';
        echo '<tr><th>SMTP Pass</th><td>' . ($smtpPass ? "<span class='success'>••••••••</span>" : "<span class='error'>Non défini</span>") . '</td></tr>';
        echo '<tr><th>From Email</th><td>' . ($smtpFrom ? "<span class='success'>$smtpFrom</span>" : "<span class='warning'>Non défini</span>") . '</td></tr>';
        echo '<tr><th>From Name</th><td>' . ($smtpFromName ?: 'Fusionel') . '</td></tr>';
        echo '<tr><th>Encryption</th><td>' . ($smtpEncryption ?: 'tls') . '</td></tr>';
        echo '</table>';
        
    } catch (Exception $e) {
        echo "<p class='error'>Erreur lors du chargement: " . $e->getMessage() . "</p>";
    }
    
    echo '</div>';
}

// Vérifier PHPMailer
echo '<div class="section">';
echo '<h2>4️⃣ PHPMailer</h2>';

$phpmailerPaths = [
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(dirname(__DIR__)) . '/vendor/autoload.php',
    '/srv/web/fusionel/vendor/autoload.php',
];

$phpmailerFound = false;
$autoloadPath = null;

foreach ($phpmailerPaths as $path) {
    if (file_exists($path)) {
        echo "<p class='success'>✅ Autoload trouvé: $path</p>";
        $autoloadPath = $path;
        $phpmailerFound = true;
        require_once $path;
        break;
    }
}

if (!$phpmailerFound) {
    echo "<p class='error'>❌ PHPMailer non trouvé (vendor/autoload.php)</p>";
    echo "<p>Installez PHPMailer avec: <code>composer require phpmailer/phpmailer</code></p>";
}

if ($phpmailerFound && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p class='success'>✅ Classe PHPMailer disponible</p>";
} elseif ($phpmailerFound) {
    echo "<p class='error'>❌ Classe PHPMailer non trouvée malgré l'autoload</p>";
}

echo '</div>';

// Vérifier la fonction mail() native
echo '<div class="section">';
echo '<h2>5️⃣ Fonction mail() native PHP</h2>';

if (function_exists('mail')) {
    echo "<p class='success'>✅ Fonction mail() disponible</p>";
} else {
    echo "<p class='error'>❌ Fonction mail() désactivée</p>";
}

// Vérifier sendmail
$sendmailPath = ini_get('sendmail_path');
echo "<p>Sendmail path: <code>" . ($sendmailPath ?: 'Non configuré') . "</code></p>";

echo '</div>';

// Test d'envoi
echo '<div class="section">';
echo '<h2>6️⃣ Test d\'envoi</h2>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['test_email'])) {
    $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    
    if (!$testEmail) {
        echo "<p class='error'>❌ Email invalide</p>";
    } else {
        echo "<p class='info'>📧 Envoi en cours vers: <strong>$testEmail</strong></p>";
        
        $success = false;
        $errorMsg = '';
        
        // Méthode 1: PHPMailer
        if ($phpmailerFound && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "<p>🔄 Tentative avec PHPMailer...</p>";
            
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->CharSet = 'UTF-8';
                
                // Config SMTP
                $mail->Host = $smtpHost ?? 'smtp.ionos.fr';
                $mail->Port = $smtpPort ?? 587;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser ?? '';
                $mail->Password = $smtpPass ?? '';
                $mail->SMTPSecure = ($smtpEncryption ?? 'tls') === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                
                // Debug
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function($str, $level) {
                    echo "<pre style='font-size:11px; margin:5px 0;'>$str</pre>";
                };
                
                $mail->setFrom($smtpFrom ?? $smtpUser ?? 'noreply@fusionel.fr', $smtpFromName ?? 'Fusionel');
                $mail->addAddress($testEmail);
                
                $mail->isHTML(true);
                $mail->Subject = '🧪 Test Email Fusionel - ' . date('H:i:s');
                $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px;">
                        <h1 style="color: #ff6b6b;">💕 Fusionel</h1>
                        <p>Ceci est un <strong>email de test</strong> envoyé depuis le script de debug.</p>
                        <p>Date: ' . date('d/m/Y H:i:s') . '</p>
                        <p>Si vous recevez cet email, la configuration SMTP fonctionne correctement ! ✅</p>
                        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                        <p style="color: #999; font-size: 12px;">Fusionel.fr - Site de rencontre</p>
                    </div>
                ';
                $mail->AltBody = 'Test email Fusionel - ' . date('d/m/Y H:i:s');
                
                $mail->send();
                $success = true;
                echo "<p class='success'>✅ Email envoyé avec succès via PHPMailer!</p>";
                
            } catch (PHPMailer\PHPMailer\Exception $e) {
                $errorMsg = $mail->ErrorInfo;
                echo "<p class='error'>❌ Erreur PHPMailer: " . htmlspecialchars($errorMsg) . "</p>";
            }
        }
        
        // Méthode 2: mail() natif (fallback)
        if (!$success && function_exists('mail')) {
            echo "<p>🔄 Tentative avec mail() natif...</p>";
            
            $subject = '=?UTF-8?B?' . base64_encode('🧪 Test Email Fusionel') . '?=';
            $message = "Test email Fusionel\nDate: " . date('d/m/Y H:i:s');
            $headers = "From: Fusionel <noreply@fusionel.fr>\r\n";
            $headers .= "Reply-To: noreply@fusionel.fr\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            if (mail($testEmail, $subject, $message, $headers)) {
                $success = true;
                echo "<p class='success'>✅ Email envoyé via mail() natif (vérifiez les spams)</p>";
            } else {
                echo "<p class='error'>❌ Échec de mail() natif</p>";
            }
        }
        
        if (!$success) {
            echo "<p class='error'>❌ Impossible d'envoyer l'email</p>";
            echo "<h3>Solutions possibles:</h3>";
            echo "<ul>";
            echo "<li>Vérifiez les identifiants SMTP dans <code>config/email.php</code></li>";
            echo "<li>Vérifiez que le port SMTP (587 ou 465) n'est pas bloqué</li>";
            echo "<li>Vérifiez que PHPMailer est installé: <code>composer require phpmailer/phpmailer</code></li>";
            echo "<li>Contactez votre hébergeur pour vérifier la configuration SMTP</li>";
            echo "</ul>";
        }
    }
}
?>

<form method="POST">
    <p>Entrez votre email pour recevoir un email de test:</p>
    <input type="email" name="test_email" placeholder="votre@email.com" required>
    <button type="submit" class="btn">📧 Envoyer un test</button>
</form>

</div>

<div class="section">
    <h2>7️⃣ Vérification inscription email</h2>
    <p>Vérifiez si l'inscription envoie bien des emails:</p>
    
    <?php
    // Chercher où sont envoyés les emails d'inscription
    $apiFiles = glob(__DIR__ . '/*.php');
    $emailFunctionFound = false;
    
    foreach ($apiFiles as $file) {
        $content = file_get_contents($file);
        if (strpos($content, 'sendVerificationEmail') !== false || 
            strpos($content, 'PHPMailer') !== false ||
            strpos($content, 'mail(') !== false) {
            echo "<p class='info'>📄 Email trouvé dans: " . basename($file) . "</p>";
            $emailFunctionFound = true;
        }
    }
    
    if (!$emailFunctionFound) {
        echo "<p class='warning'>⚠️ Aucune fonction d'envoi d'email trouvée dans les fichiers API</p>";
        echo "<p>L'envoi d'email de vérification n'est peut-être pas implémenté.</p>";
    }
    ?>
</div>

<div class="section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
    <h2>⚠️ Sécurité</h2>
    <p><strong>SUPPRIMEZ ce fichier après utilisation!</strong></p>
    <pre>rm /srv/web/fusionel/public/api/debug-email.php</pre>
</div>

</body>
</html>
