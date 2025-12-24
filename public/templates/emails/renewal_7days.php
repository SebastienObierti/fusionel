<?php
/**
 * Template Email - Rappel renouvellement 7 jours
 * 
 * Variables:
 * - $firstname, $plan_type, $end_date, $price, $site
 */

$subject = "Votre abonnement {$plan_type} expire dans 7 jours";

$planName = ucfirst($plan_type);
$formattedDate = date('d/m/Y', strtotime($end_date));
$formattedPrice = number_format($price, 2, ',', ' ');

$content = <<<HTML
<h2>Bonjour {$firstname} 👋</h2>

<p>Nous espérons que vous profitez pleinement de votre expérience sur Fusionel !</p>

<div class="highlight-box">
    <strong>⏰ Rappel :</strong> Votre abonnement <strong>{$planName}</strong> expire dans <strong>7 jours</strong>, le <strong>{$formattedDate}</strong>.
</div>

<p>Pour continuer à bénéficier de tous vos avantages Premium, pensez à renouveler votre abonnement :</p>

<div class="info-box">
    <p style="margin:0"><strong>Votre plan actuel :</strong> {$planName}</p>
    <p style="margin:10px 0 0 0"><strong>Date d'expiration :</strong> {$formattedDate}</p>
</div>

<h3>Vos avantages actuels :</h3>
<ul>
    <li>❤️ Likes illimités</li>
    <li>⭐ Super Likes chaque semaine</li>
    <li>👀 Voir qui vous a liké</li>
    <li>🚀 Boosts mensuels pour plus de visibilité</li>
    <li>↩️ Annuler le dernier swipe</li>
</ul>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$site['url']}/app/subscription.html" class="btn">Renouveler mon abonnement</a>
</p>

<div class="divider"></div>

<p style="font-size: 13px; color: #999;">
    💡 <strong>Astuce :</strong> Optez pour un abonnement annuel et économisez jusqu'à 30% !
</p>
HTML;

// Inclure le layout
$title = $subject;
include __DIR__ . '/layout.php';
