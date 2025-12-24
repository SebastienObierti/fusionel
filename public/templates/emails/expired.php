<?php
/**
 * Template Email - Abonnement expiré
 */

$subject = "😢 Votre abonnement {$plan_type} a expiré";

$planName = ucfirst($plan_type);

$content = <<<HTML
<div style="text-align: center; margin-bottom: 30px;">
    <span style="font-size: 60px;">😢</span>
</div>

<h2 style="text-align: center;">Votre abonnement a expiré, {$firstname}</h2>

<p>Votre abonnement <strong>{$planName}</strong> est arrivé à expiration. Vous êtes maintenant sur le plan gratuit.</p>

<div class="info-box">
    <h4 style="margin-top: 0;">Ce que vous avez perdu :</h4>
    <ul>
        <li>❌ Likes illimités → <em>Limité à 5/jour</em></li>
        <li>❌ Super Likes → <em>Non disponible</em></li>
        <li>❌ Voir qui vous a liké → <em>Non disponible</em></li>
        <li>❌ Boosts → <em>Non disponible</em></li>
    </ul>
</div>

<h3>🌟 Bonne nouvelle !</h3>

<p>Vous pouvez réactiver votre abonnement à tout moment et retrouver instantanément tous vos avantages Premium.</p>

<div class="highlight-box">
    <strong>💡 Le saviez-vous ?</strong><br>
    Les membres Premium ont 3x plus de chances de trouver un match compatible !
</div>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$site['url']}/app/subscription.html" class="btn">
        💕 Réactiver mon compte Premium
    </a>
</p>

<div class="divider"></div>

<p style="text-align: center; color: #666; font-size: 14px;">
    Vous nous manquez déjà ! 💔<br>
    L'équipe Fusionel
</p>
HTML;

$title = $subject;
include __DIR__ . '/layout.php';
