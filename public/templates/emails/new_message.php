<?php
/**
 * Template Email - Nouveau Message
 */

$subject = "💬 {$sender_name} vous a envoyé un message";

$senderPhoto = $sender_photo ?? 'https://ui-avatars.com/api/?background=ff6b6b&color=fff&size=80&name=' . urlencode($sender_name);
$messagePreview = strlen($message_preview) > 100 ? substr($message_preview, 0, 100) . '...' : $message_preview;

$content = <<<HTML
<p>Bonjour {$firstname},</p>

<p>Vous avez reçu un nouveau message de <strong>{$sender_name}</strong> !</p>

<div class="info-box" style="display: flex; align-items: flex-start; gap: 15px;">
    <img src="{$senderPhoto}" alt="{$sender_name}" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
    <div>
        <strong style="color: #2d3436;">{$sender_name}</strong>
        <p style="margin: 8px 0 0 0; color: #636e72; font-style: italic;">
            "{$messagePreview}"
        </p>
    </div>
</div>

<p style="text-align: center; margin: 30px 0;">
    <a href="{$site['url']}/app/messages.html?conv={$conversation_id}" class="btn">
        💬 Lire et répondre
    </a>
</p>

<div class="divider"></div>

<p style="font-size: 13px; color: #999; text-align: center;">
    Ne faites pas attendre {$sender_name} trop longtemps ! 😊
</p>
HTML;

$title = $subject;
include __DIR__ . '/layout.php';
