    <?php
    /**
     * FUSIONEL - Configuration Email (PHPMailer)
     * 
     * Emplacement: /config/email.php
     */

    return [
        // ========== CONFIGURATION SMTP ==========
        'smtp' => [
            // Gmail: smtp.gmail.com | OVH: ssl.ovh.net | Outlook: smtp-mail.outlook.com
            'host' => 'smtp.ionos.fr',
            
            // Port: 587 (TLS) | 465 (SSL) | 25 (aucune)
            'port' => 587,
            
            // Encryption: 'tls', 'ssl' ou ''
            'encryption' => 'tls',
            
            // Identifiants SMTP - ⚠️ À MODIFIER
            'username' => 'contact@obierti.fr',
            'password' => '***************',  // App Password pour Gmail
            
            'auth' => true,
        ],
        
        // ========== EXPÉDITEUR ==========
        'from' => [
            'email' => 'contact@obierti.fr',
            'name' => 'Fusionel'
        ],
        
        // ========== REPLY-TO ==========
        'reply_to' => [
            'email' => 'contact@obierti.fr',
            'name' => 'Support Fusionel'
        ],
        
        // ========== OPTIONS ==========
        'options' => [
            'charset' => 'UTF-8',
            'is_html' => true,
            'debug' => 0,  // 0=off, 2=debug SMTP
        ],
        
        // ========== SITE ==========
        'site' => [
            'name' => 'Fusionel',
            'url' => 'https://fusionel.fr',
            'logo' => 'https://fusionel.fr/assets/logo.png',
            'support_email' => 'contact@obierti.fr',
        ]
    ];
