<?php
/**
 * Template Email - Nouveau Match
 */

$subject = "💕 C'est un Match avec {$match_name} !";

$matchPhoto = $match_photo ?? 'https://ui-avatars.com/api/?background=ff6b6b&color=fff&size=150&name=' . urlencode($match_name);

$content = <<<HTML
<div style="text-align: center;">
    <span style="font-size: 60px;">💕</span>
    
    <h2 style="color: #ff6b6b; margin: 20px 0;">C'est un Match !</h2>
    
    <p style="font-size: 18px;">Vous et <strong>{$match_name}</strong> vous êtes mutuellement likés !</p>
    
    <div style="margin: 30px 0;">
        <img src="{$matchPhoto}" alt="{$match_name}" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #ff6b6b; box-shadow: 0 5px 20px rgba(255,107,107,0.3);">
    </div>
    
    <p>N'attendez pas ! Les conversations démarrées rapidement ont plus de chances d'aboutir.</p>
    
    <p style="margin: 30px 0;">
        <a href="{$site['url']}/app/messages.html?match={$match_id}" class="btn">
            💬 Envoyer un message
        </a>
    </p>
</div>

<div class="divider"></div>

<div class="info-box">
    <h4 style="margin-top: 0;">💡 Conseils pour briser la glace :</h4>
    <ul>
        <li>Mentionnez quelque chose de son profil qui vous a plu</li>
        <li>Posez une question ouverte sur ses centres d'intérêt</li>
        <li>Soyez authentique et enjoué !</li>
    </ul>
</div>

<p style="text-align: center; color: #999; font-size: 13px;">
    Bonne conversation ! 🍀
</p>
HTML;

$title = $subject;
include __DIR__ . '/layout.php';
