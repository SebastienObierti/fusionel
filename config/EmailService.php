<?php
/**
 * FUSIONEL - Service d'envoi d'emails
 * 
 * Emplacement: /config/EmailService.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/vendor/autoload.php';

class EmailService {
    
    private $config;
    private $mailer;
    
    public function __construct() {
        $this->config = require __DIR__ . '/email.php';
        $this->initMailer();
    }
    
    private function initMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Configuration SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp']['host'];
            $this->mailer->Port = $this->config['smtp']['port'];
            $this->mailer->SMTPAuth = $this->config['smtp']['auth'];
            $this->mailer->Username = $this->config['smtp']['username'];
            $this->mailer->Password = $this->config['smtp']['password'];
            
            // Encryption
            if ($this->config['smtp']['encryption'] === 'tls') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->config['smtp']['encryption'] === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Options
            $this->mailer->CharSet = $this->config['options']['charset'];
            $this->mailer->isHTML($this->config['options']['is_html']);
            $this->mailer->SMTPDebug = $this->config['options']['debug'];
            
            // Expéditeur
            $this->mailer->setFrom(
                $this->config['from']['email'],
                $this->config['from']['name']
            );
            
            // Reply-To
            $this->mailer->addReplyTo(
                $this->config['reply_to']['email'],
                $this->config['reply_to']['name']
            );
            
        } catch (Exception $e) {
            error_log("EmailService init error: " . $e->getMessage());
        }
    }
    
    /**
     * Envoyer un email
     */
    public function send($to, $subject, $body, $altBody = '') {
        try {
            // Reset destinataires
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);
            
            $this->mailer->send();
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Email send error: " . $this->mailer->ErrorInfo);
            return ['success' => false, 'error' => $this->mailer->ErrorInfo];
        }
    }
    
    /**
     * Email de vérification d'inscription
     */
    public function sendVerificationEmail($to, $firstname, $token) {
        $verifyUrl = $this->config['site']['url'] . '/verify-email.html?token=' . $token;
        
        $subject = "💕 Bienvenue sur Fusionel - Confirmez votre email";
        
        $body = $this->getTemplate('verification', [
            'firstname' => $firstname,
            'verify_url' => $verifyUrl,
            'site_name' => $this->config['site']['name'],
            'site_url' => $this->config['site']['url'],
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Email de bienvenue (après vérification)
     */
    public function sendWelcomeEmail($to, $firstname) {
        $subject = "🎉 Bienvenue sur Fusionel, $firstname !";
        
        $body = $this->getTemplate('welcome', [
            'firstname' => $firstname,
            'site_name' => $this->config['site']['name'],
            'site_url' => $this->config['site']['url'],
            'discover_url' => $this->config['site']['url'] . '/app/discover.html',
            'profile_url' => $this->config['site']['url'] . '/app/profile.html',
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail($to, $firstname, $token) {
        $resetUrl = $this->config['site']['url'] . '/reset-password.html?token=' . $token;
        
        $subject = "🔐 Réinitialisation de votre mot de passe Fusionel";
        
        $body = $this->getTemplate('password_reset', [
            'firstname' => $firstname,
            'reset_url' => $resetUrl,
            'site_name' => $this->config['site']['name'],
            'site_url' => $this->config['site']['url'],
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Email de notification de match
     */
    public function sendMatchEmail($to, $firstname, $matchName) {
        $subject = "💕 C'est un Match avec $matchName !";
        
        $body = $this->getTemplate('match', [
            'firstname' => $firstname,
            'match_name' => $matchName,
            'site_name' => $this->config['site']['name'],
            'matches_url' => $this->config['site']['url'] . '/app/matches.html',
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Email de notification de message
     */
    public function sendMessageNotificationEmail($to, $firstname, $senderName) {
        $subject = "💬 Nouveau message de $senderName";
        
        $body = $this->getTemplate('new_message', [
            'firstname' => $firstname,
            'sender_name' => $senderName,
            'site_name' => $this->config['site']['name'],
            'messages_url' => $this->config['site']['url'] . '/app/messages.html',
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Obtenir un template HTML
     */
    private function getTemplate($name, $vars = []) {
        $siteName = $vars['site_name'] ?? 'Fusionel';
        $siteUrl = $vars['site_url'] ?? 'https://fusionel.fr';
        
        // Style commun
        $style = '
            <style>
                body { font-family: "Segoe UI", Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #ff6b6b, #ff8e8e); padding: 30px; text-align: center; }
                .header h1 { color: white; margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; }
                .content h2 { color: #2d3436; margin-top: 0; }
                .content p { color: #636e72; line-height: 1.7; font-size: 15px; }
                .btn { display: inline-block; padding: 15px 35px; background: linear-gradient(135deg, #ff6b6b, #ff5252); color: white !important; text-decoration: none; border-radius: 30px; font-weight: 600; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 25px; text-align: center; color: #999; font-size: 12px; }
                .footer a { color: #ff6b6b; text-decoration: none; }
            </style>
        ';
        
        switch ($name) {
            case 'verification':
                return $style . '
                    <div class="container">
                        <div class="header">
                            <h1>💕 ' . $siteName . '</h1>
                        </div>
                        <div class="content">
                            <h2>Bienvenue ' . htmlspecialchars($vars['firstname']) . ' !</h2>
                            <p>Merci de vous être inscrit sur Fusionel. Pour activer votre compte et commencer à faire des rencontres, veuillez confirmer votre adresse email.</p>
                            <p style="text-align: center;">
                                <a href="' . $vars['verify_url'] . '" class="btn">✓ Confirmer mon email</a>
                            </p>
                            <p style="font-size: 13px; color: #999;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>' . $vars['verify_url'] . '</p>
                            <p style="font-size: 13px; color: #999;">Ce lien expire dans 24 heures.</p>
                        </div>
                        <div class="footer">
                            <p>Vous recevez cet email car vous vous êtes inscrit sur <a href="' . $siteUrl . '">' . $siteName . '</a></p>
                            <p>© ' . date('Y') . ' ' . $siteName . ' - Tous droits réservés</p>
                        </div>
                    </div>
                ';
                
            case 'welcome':
                return $style . '
                    <div class="container">
                        <div class="header">
                            <h1>💕 ' . $siteName . '</h1>
                        </div>
                        <div class="content">
                            <h2>🎉 Votre compte est activé !</h2>
                            <p>Félicitations ' . htmlspecialchars($vars['firstname']) . ', votre email a été confirmé avec succès.</p>
                            <p>Vous pouvez maintenant :</p>
                            <ul>
                                <li>Compléter votre profil avec de belles photos</li>
                                <li>Découvrir des profils compatibles</li>
                                <li>Envoyer des likes et matcher</li>
                            </ul>
                            <p style="text-align: center;">
                                <a href="' . $vars['discover_url'] . '" class="btn">💕 Découvrir des profils</a>
                            </p>
                        </div>
                        <div class="footer">
                            <p>© ' . date('Y') . ' ' . $siteName . ' - <a href="' . $siteUrl . '">fusionel.fr</a></p>
                        </div>
                    </div>
                ';
                
            case 'password_reset':
                return $style . '
                    <div class="container">
                        <div class="header">
                            <h1>💕 ' . $siteName . '</h1>
                        </div>
                        <div class="content">
                            <h2>🔐 Réinitialisation du mot de passe</h2>
                            <p>Bonjour ' . htmlspecialchars($vars['firstname']) . ',</p>
                            <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous :</p>
                            <p style="text-align: center;">
                                <a href="' . $vars['reset_url'] . '" class="btn">🔐 Nouveau mot de passe</a>
                            </p>
                            <p style="font-size: 13px; color: #999;">Ce lien expire dans 1 heure.</p>
                            <p style="font-size: 13px; color: #999;">Si vous n\'avez pas fait cette demande, ignorez cet email.</p>
                        </div>
                        <div class="footer">
                            <p>© ' . date('Y') . ' ' . $siteName . '</p>
                        </div>
                    </div>
                ';
                
            case 'match':
                return $style . '
                    <div class="container">
                        <div class="header">
                            <h1>💕 C\'est un Match !</h1>
                        </div>
                        <div class="content">
                            <h2 style="text-align: center;">🎉 Félicitations ' . htmlspecialchars($vars['firstname']) . ' !</h2>
                            <p style="text-align: center; font-size: 18px;">' . htmlspecialchars($vars['match_name']) . ' et vous vous êtes mutuellement likés !</p>
                            <p style="text-align: center;">C\'est le moment d\'engager la conversation...</p>
                            <p style="text-align: center;">
                                <a href="' . $vars['matches_url'] . '" class="btn">💬 Envoyer un message</a>
                            </p>
                        </div>
                        <div class="footer">
                            <p>© ' . date('Y') . ' ' . $siteName . '</p>
                        </div>
                    </div>
                ';
                
            case 'new_message':
                return $style . '
                    <div class="container">
                        <div class="header">
                            <h1>💕 ' . $siteName . '</h1>
                        </div>
                        <div class="content">
                            <h2>💬 Nouveau message !</h2>
                            <p>Bonjour ' . htmlspecialchars($vars['firstname']) . ',</p>
                            <p><strong>' . htmlspecialchars($vars['sender_name']) . '</strong> vous a envoyé un message.</p>
                            <p style="text-align: center;">
                                <a href="' . $vars['messages_url'] . '" class="btn">Lire le message</a>
                            </p>
                        </div>
                        <div class="footer">
                            <p>© ' . date('Y') . ' ' . $siteName . '</p>
                        </div>
                    </div>
                ';
                
            default:
                return '<p>Template non trouvé</p>';
        }
    }
}

/**
 * Fonction helper pour envoyer rapidement un email
 */
function sendEmail($to, $subject, $body) {
    $emailService = new EmailService();
    return $emailService->send($to, $subject, $body);
}

/**
 * Fonction helper pour l'email de vérification
 */
function sendVerificationEmail($to, $firstname, $token) {
    $emailService = new EmailService();
    return $emailService->sendVerificationEmail($to, $firstname, $token);
}

/**
 * Fonction helper pour l'email de bienvenue
 */
function sendWelcomeEmail($to, $firstname) {
    $emailService = new EmailService();
    return $emailService->sendWelcomeEmail($to, $firstname);
}

/**
 * Fonction helper pour l'email de reset password
 */
function sendPasswordResetEmail($to, $firstname, $token) {
    $emailService = new EmailService();
    return $emailService->sendPasswordResetEmail($to, $firstname, $token);
}

/**
 * Fonction helper pour l'email de match
 */
function sendMatchNotification($to, $firstname, $matchName) {
    $emailService = new EmailService();
    return $emailService->sendMatchEmail($to, $firstname, $matchName);
}
