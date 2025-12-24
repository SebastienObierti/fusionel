<?php
/**
 * FUSIONEL - Classe Mailer (PHPMailer Wrapper)
 * 
 * Emplacement: /public/lib/Mailer.php
 * Structure attendue:
 *   /config/email.php
 *   /vendor/autoload.php
 *   /public/templates/emails/*.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Chemins depuis /public/lib/
define('ROOT_PATH', dirname(dirname(__DIR__))); // Remonte à la racine du projet
define('PUBLIC_PATH', dirname(__DIR__));         // /public

// Charger PHPMailer
require_once ROOT_PATH . '/vendor/autoload.php';

class Mailer {
    private $config;
    private $mail;
    private $lastError = '';
    
    public function __construct() {
        // Charger la config depuis /config/email.php
        $configPath = ROOT_PATH . '/config/email.php';
        
        if (!file_exists($configPath)) {
            throw new Exception("Config email non trouvée: $configPath");
        }
        
        $this->config = require $configPath;
        $this->mail = new PHPMailer(true);
        $this->configure();
    }
    
    private function configure() {
        $smtp = $this->config['smtp'];
        $options = $this->config['options'];
        
        try {
            $this->mail->isSMTP();
            $this->mail->Host = $smtp['host'];
            $this->mail->Port = $smtp['port'];
            $this->mail->SMTPAuth = $smtp['auth'];
            $this->mail->Username = $smtp['username'];
            $this->mail->Password = $smtp['password'];
            
            if ($smtp['encryption'] === 'tls') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($smtp['encryption'] === 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            $this->mail->CharSet = $options['charset'];
            $this->mail->isHTML($options['is_html']);
            $this->mail->SMTPDebug = $options['debug'];
            
            $this->mail->setFrom($this->config['from']['email'], $this->config['from']['name']);
            $this->mail->addReplyTo($this->config['reply_to']['email'], $this->config['reply_to']['name']);
            
        } catch (Exception $e) {
            $this->lastError = "Erreur configuration: " . $e->getMessage();
            error_log("Mailer Config Error: " . $this->lastError);
        }
    }
    
    /**
     * Envoyer un email
     */
    public function send($to, $subject, $htmlBody, $textBody = '', $attachments = []) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $this->mail->addAddress($name);
                    } else {
                        $this->mail->addAddress($email, $name);
                    }
                }
            } else {
                $this->mail->addAddress($to);
            }
            
            $this->mail->Subject = $subject;
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = $textBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $this->mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $this->mail->addAttachment($attachment);
                }
            }
            
            $result = $this->mail->send();
            return $result;
            
        } catch (Exception $e) {
            $this->lastError = $this->mail->ErrorInfo;
            error_log("Mailer Send Error: " . $this->lastError);
            return false;
        }
    }
    
    /**
     * Envoyer avec un template
     */
    public function sendTemplate($to, $templateName, $data = []) {
        $template = $this->loadTemplate($templateName, $data);
        
        if (!$template) {
            $this->lastError = "Template '$templateName' non trouvé";
            error_log("Mailer Template Error: " . $this->lastError);
            return false;
        }
        
        return $this->send($to, $template['subject'], $template['html'], $template['text'] ?? '');
    }
    
    /**
     * Charger un template email
     */
    private function loadTemplate($name, $data) {
        // Templates dans /public/templates/emails/
        $templatePath = PUBLIC_PATH . '/templates/emails/' . $name . '.php';
        
        if (!file_exists($templatePath)) {
            error_log("Template non trouvé: $templatePath");
            return null;
        }
        
        // Variables disponibles dans le template
        extract($data);
        $site = $this->config['site'];
        
        // Initialiser les variables que le template va définir
        $subject = '';
        $html = '';
        $content = '';
        $title = '';
        
        // Capturer le contenu du template
        ob_start();
        include $templatePath;
        $output = ob_get_clean();
        
        return [
            'subject' => $subject ?: 'Notification Fusionel',
            'html' => $html ?: $output,
            'text' => $text ?? ''
        ];
    }
    
    /**
     * Tester la connexion SMTP
     */
    public function testConnection() {
        try {
            // Pas de debug pour éviter la pollution de sortie
            $this->mail->SMTPDebug = 0;
            
            $connected = $this->mail->smtpConnect();
            $this->mail->smtpClose();
            
            return [
                'success' => $connected
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getLastError() { 
        return $this->lastError; 
    }
    
    public function addCC($email, $name = '') { 
        $this->mail->addCC($email, $name); 
        return $this; 
    }
    
    public function addBCC($email, $name = '') { 
        $this->mail->addBCC($email, $name); 
        return $this; 
    }
    
    public function setFrom($email, $name = '') { 
        $this->mail->setFrom($email, $name); 
        return $this; 
    }
}
