<?php
/**
 * Template Email - Rappel renouvellement 3 jours
 */

$subject = "⚠️ Plus que 3 jours pour votre abonnement {$plan_type}";

$planName = ucfirst($plan_type);
$formattedDate = date('d/m/Y', strtotime($end_date));

$content = <<<HTML
<h2>Bonjour {$firstname} 👋</h2>

<div class="highlight-box" style="background: #fff3cd; border-color: #ffc107;">
    <strong>⚠️ Attention :</strong> Votre abonnement <strong>{$planName}</strong> expire dans <strong>3 jours</strong> !
</div>

<p>Ne perdez pas vos avantages Premium. Après le <strong>{$formattedDate}</strong>, vous passerez automatiquement au plan gratuit avec :</p>

<div class="info-box" style="background: #f8d7da;">
    <ul style="margin:0; padding-left: 20px;">
        <li>Seulement 5 likes par jour</li>
        <li>Plus d'accès à "Qui m'a liké"</li>
        <li>Plus de Super Likes</li>
        <li>Plus de Boosts</li>
    </ul>
</div>

<p><strong>Gardez tous vos avantages !</strong> Renouvelez maintenant en un clic :</p>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$site['url']}/app/subscription.html" class="btn">🔥 Renouveler maintenant</a>
</p>

<div class="divider"></div>

<p style="text-align: center; color: #666;">
    Des questions ? Contactez-nous à <a href="mailto:{$site['support_email']}">{$site['support_email']}</a>
</p>
HTML;

$title = $subject;
include __DIR__ . '/layout.php';
