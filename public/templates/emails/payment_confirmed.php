<?php
/**
 * Template Email - Confirmation de paiement / Activation abonnement
 */

$subject = "✅ Bienvenue dans {$plan_type} ! Votre paiement est confirmé";

$planName = ucfirst($plan_type);
$formattedPrice = number_format($price, 2, ',', ' ');
$formattedStartDate = date('d/m/Y', strtotime($start_date));
$formattedEndDate = date('d/m/Y', strtotime($end_date));
$invoiceNumber = 'FUS-' . str_pad($payment_id ?? rand(1000, 9999), 6, '0', STR_PAD_LEFT);

$features = [
    'premium' => [
        '❤️ Likes illimités',
        '⭐ 5 Super Likes par semaine',
        '🚀 1 Boost par mois',
        '👀 Voir qui vous a liké',
        '↩️ Annuler le dernier swipe'
    ],
    'vip' => [
        '❤️ Likes illimités',
        '⭐ Super Likes illimités',
        '🚀 5 Boosts par mois',
        '👀 Voir qui vous a liké',
        '↩️ Annuler le dernier swipe',
        '✨ Badge VIP vérifié',
        '🎯 Profil prioritaire',
        '💬 Support prioritaire'
    ]
];

$planFeatures = $features[$plan_type] ?? $features['premium'];
$featuresHtml = implode('</li><li>', $planFeatures);

$content = <<<HTML
<div style="text-align: center; margin-bottom: 30px;">
    <span style="font-size: 60px;">🎉</span>
</div>

<h2 style="text-align: center; color: #00b894;">Paiement confirmé !</h2>

<p>Bonjour {$firstname},</p>

<p>Merci pour votre confiance ! Votre abonnement <strong>{$planName}</strong> est maintenant actif.</p>

<div class="info-box" style="background: #d4edda; border: 1px solid #28a745;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0;"><strong>Facture N°</strong></td>
            <td style="padding: 8px 0; text-align: right;">{$invoiceNumber}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>Plan</strong></td>
            <td style="padding: 8px 0; text-align: right;">{$planName}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>Période</strong></td>
            <td style="padding: 8px 0; text-align: right;">{$billing_period}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>Montant payé</strong></td>
            <td style="padding: 8px 0; text-align: right; font-size: 18px; color: #00b894;"><strong>{$formattedPrice} €</strong></td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>Date de début</strong></td>
            <td style="padding: 8px 0; text-align: right;">{$formattedStartDate}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>Valide jusqu'au</strong></td>
            <td style="padding: 8px 0; text-align: right;">{$formattedEndDate}</td>
        </tr>
    </table>
</div>

<h3>🌟 Vos nouveaux avantages</h3>

<ul>
    <li>{$featuresHtml}</li>
</ul>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$site['url']}/app/discover.html" class="btn">
        💕 Commencer à matcher
    </a>
</p>

<div class="divider"></div>

<p style="font-size: 13px; color: #666;">
    <strong>Transaction PayPal :</strong> {$transaction_id}<br>
    Ce reçu fait office de facture. Conservez-le pour vos archives.
</p>

<p style="font-size: 13px; color: #999;">
    Des questions sur votre abonnement ? Contactez-nous à <a href="mailto:{$site['support_email']}">{$site['support_email']}</a>
</p>
HTML;

$title = $subject;
include __DIR__ . '/layout.php';
