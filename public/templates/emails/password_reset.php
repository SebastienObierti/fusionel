<?php
/**
 * Template Email - Réinitialisation de mot de passe
 */

$subject = "🔐 Réinitialisation de votre mot de passe Fusionel";

$resetLink = $site['url'] . '/reset-password.html?token=' . $reset_token;

$content = <<<HTML
<h2>Réinitialisation de mot de passe</h2>

<p>Bonjour {$firstname},</p>

<p>Vous avez demandé à réinitialiser votre mot de passe sur Fusionel. Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe :</p>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$resetLink}" class="btn">
        🔐 Réinitialiser mon mot de passe
    </a>
</p>

<div class="highlight-box">
    <strong>⚠️ Important :</strong><br>
    Ce lien expire dans <strong>1 heure</strong>.<br>
    Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.
</div>

<p style="font-size: 13px; color: #666;">
    Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
    <a href="{$resetLink}" style="color: #ff6b6b; word-break: break-all;">{$resetLink}</a>
</p>

<div class="divider"></div>

<p style="font-size: 13px; color: #999;">
    <strong>Vous n'avez pas fait cette demande ?</strong><br>
    Votre compte est en sécurité. Quelqu'un a peut-être entré votre email par erreur.
    Si vous êtes inquiet, contactez-nous à <a href="mailto:{$site['support_email']}">{$site['support_email']}</a>
</p>
HTML;

$title = $subject;
include __DIR__ . '/layout.php';
