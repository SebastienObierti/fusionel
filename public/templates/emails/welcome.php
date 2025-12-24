<?php
/**
 * Template Email - Bienvenue (inscription)
 */

$subject = "💕 Bienvenue sur Fusionel, {$firstname} !";

$content = <<<HTML
<div style="text-align: center; margin-bottom: 30px;">
    <span style="font-size: 60px;">💕</span>
</div>

<h2 style="text-align: center;">Bienvenue sur Fusionel !</h2>

<p>Bonjour {$firstname},</p>

<p>Nous sommes ravis de vous accueillir dans la communauté Fusionel ! Votre compte a été créé avec succès.</p>

<div class="highlight-box">
    <strong>🎯 Prochaines étapes pour maximiser vos chances :</strong>
</div>

<div class="info-box">
    <h4 style="margin-top: 0;">1. Complétez votre profil</h4>
    <p>Les profils complets reçoivent 10x plus de visites !</p>
    
    <h4>2. Ajoutez vos plus belles photos</h4>
    <p>Montrez votre personnalité avec 3 à 6 photos variées.</p>
    
    <h4>3. Rédigez une bio accrocheuse</h4>
    <p>Parlez de vos passions, ce qui vous rend unique.</p>
    
    <h4>4. Commencez à explorer !</h4>
    <p>Découvrez les profils et envoyez vos premiers likes.</p>
</div>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$site['url']}/app/profile.html" class="btn">
        ✨ Compléter mon profil
    </a>
</p>

<div class="divider"></div>

<h3>💡 Conseils pour réussir</h3>

<ul>
    <li><strong>Soyez authentique</strong> - Les profils sincères attirent plus de matchs</li>
    <li><strong>Connectez-vous régulièrement</strong> - Les profils actifs sont mis en avant</li>
    <li><strong>Prenez le temps de lire les bios</strong> - Un message personnalisé fait toute la différence</li>
</ul>

<div class="divider"></div>

<p style="text-align: center; color: #666;">
    Des questions ? Notre équipe est là pour vous aider !<br>
    <a href="mailto:{$site['support_email']}">{$site['support_email']}</a>
</p>

<p style="text-align: center; margin-top: 20px;">
    Bonne chance dans vos rencontres ! 🍀<br>
    <strong>L'équipe Fusionel</strong>
</p>
HTML;

$title = $subject;
include __DIR__ . '/layout.php';
