<?php
/**
 * Template Email - Layout de base
 * 
 * Variables disponibles:
 * - $site (array): name, url, logo, support_email
 * - $content (string): Contenu principal
 * - $title (string): Titre de l'email
 */

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            padding: 30px 0;
        }
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #ff6b6b;
            text-decoration: none;
            font-family: Georgia, serif;
        }
        .logo-heart {
            color: #ff6b6b;
        }
        .content {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h1, h2, h3 {
            color: #2d3436;
            margin-top: 0;
        }
        p {
            color: #636e72;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
        }
        .btn:hover {
            background: linear-gradient(135deg, #ff5252, #ff6b6b);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #636e72 !important;
            border: 2px solid #ddd;
        }
        .footer {
            text-align: center;
            padding: 30px 0;
            color: #b2bec3;
            font-size: 13px;
        }
        .footer a {
            color: #ff6b6b;
            text-decoration: none;
        }
        .divider {
            height: 1px;
            background: #eee;
            margin: 25px 0;
        }
        .highlight-box {
            background: #fff5f5;
            border-left: 4px solid #ff6b6b;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .price {
            font-size: 28px;
            font-weight: bold;
            color: #ff6b6b;
        }
        ul {
            padding-left: 20px;
        }
        li {
            color: #636e72;
            margin: 8px 0;
        }
        .social-links {
            margin: 20px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #636e72;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="{$site['url']}" class="logo">
                <span class="logo-heart">💕</span> {$site['name']}
            </a>
        </div>
        
        <div class="content">
            {$content}
        </div>
        
        <div class="footer">
            <p>© 2024 {$site['name']} - Trouvez l'amour</p>
            <p>
                <a href="{$site['url']}/app/settings.html">Gérer mes notifications</a> • 
                <a href="{$site['url']}/privacy">Confidentialité</a> • 
                <a href="{$site['url']}/help">Aide</a>
            </p>
            <p style="margin-top: 15px; font-size: 11px;">
                Vous recevez cet email car vous êtes inscrit sur {$site['name']}.<br>
                {$site['support_email']}
            </p>
        </div>
    </div>
</body>
</html>
HTML;
