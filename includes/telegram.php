<?php
// Načtení konfigurace
include_once(__DIR__ . '/../config/config.php');

/**
 * Odeslání zprávy do Telegramu
 * @param string $message Text zprávy
 * @param string $channel Klíč kanálu ('dev', 'vyvoj', 'nakup', 'kvalita')
 */
function sendTelegram($message, $channel = 'dev') {

    // ========================================================
    // 🛑 AUTOMATICKÝ PŘEPÍNAČ PROSTŘEDÍ 🛑
    // ========================================================
    $is_test_mode = defined('IS_DEV') ? IS_DEV : true;

    if ($is_test_mode && $channel !== 'dev') {
        $message = "🛠 <b>[TEST -> " . strtoupper($channel) . "]</b>\n" . $message;
        $channel = 'dev'; // Přesměrování na tvé ID
    }

    $botToken = defined('TG_BOT_TOKEN') ? TG_BOT_TOKEN : "";

    // Mapování klíčů na konstanty z configu
    $chats = [
        'dev'     => defined('TG_CHAT_DEV') ? TG_CHAT_DEV : "",
        'vyvoj'   => defined('TG_CHAT_VYVOJ') ? TG_CHAT_VYVOJ : "",
        'nakup'   => defined('TG_CHAT_NAKUP') ? TG_CHAT_NAKUP : "",
        'kvalita' => defined('TG_CHAT_KVALITA') ? TG_CHAT_KVALITA : ""
    ];

    // Kontrola, jestli kanál existuje a má vyplněné ID
    if (!isset($chats[$channel]) || empty($chats[$channel])) return false;

    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";

    $data = [
        'chat_id' => $chats[$channel],
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];

    $context  = stream_context_create($options);
    return @file_get_contents($url, false, $context);
}
?>