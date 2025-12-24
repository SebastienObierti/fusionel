<?php
/**
 * Template Email - Rappel renouvellement 1 jour (URGENT)
 */

$subject = "🚨 DERNIER JOUR - Votre abonnement {$plan_type} expire demain !";

$planName = ucfirst($plan_type);
$formattedDate = date('d/m/Y', strtotime($end_date));

$content = <<<HTML
<div style="text-align: center; margin-bottom: 30px;">
    <span style="font-size: 60px;">🚨</span>
</div>

<h2 style="text-align: center; color: #d63031;">Dernier jour, {$firstname} !</h2>

<div class="highlight-box" style="background: #f8d7da; border-color: #d63031;">
    <strong>Votre abonnement {$planName} expire DEMAIN</strong> ({$formattedDate})
</div>

<p style="text-align: center; font-size: 18px;">
    Après demain, vous perdrez l'accès à :
</p>

<div style="display: flex; justify-content: center; margin: 20px 0;">
    <table style="text-align: left;">
        <tr><td style="padding: 8px 15px;">❌</td><td style="padding: 8px;">Likes illimités</td></tr>
        <tr><td style="padding: 8px 15px;">❌</td><td style="padding: 8px;">Super Likes</td></tr>
        <tr><td style="padding: 8px 15px;">❌</td><td style="padding: 8px;">Voir qui vous a liké</td></tr>
        <tr><td style="padding: 8px 15px;">❌</td><td style="padding: 8px;">Boosts mensuels</td></tr>
        <tr><td style="padding: 8px 15px;">❌</td><td style="padding: 8px;">Badge Premium</td></tr>
    </table>
</div>

<p style="text-align: center;">
    <strong>Ne laissez pas vos matchs potentiels vous échapper !</strong>
</p>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$site['url']}/app/subscription.html" class="btn" style="font-size: 18px; padding: 18px 40px;">
        🔥 RENOUVELER MAINTENANT
    </a>
</p>

<div class="divider"></div>

<p style="text-align: center; font-size: 13px; color: #999;">
    Offre spéciale : Passez à l'annuel et économisez 30% !
</p>
HTML;

$title = $subject;
include __DIR__ . '/layout.php';
