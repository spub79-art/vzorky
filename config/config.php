<?php
// Zamezení přímého přístupu
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Přístup odepřen.');
}

// ==========================================
// 1. DETEKCE PROSTŘEDÍ A DATABÁZE
// ==========================================
// Využíváme tvou super detekci přes fyzickou cestu složky
if (strpos(__DIR__, 'dev-vzorky') !== false) {
    if (!defined('IS_DEV')) define('IS_DEV', true);

    // Vývojová databáze
    define('DB_HOST', 'localhost');
    define('DB_USER', 'vzorky');
    define('DB_PASS', 'vzorky');
    define('DB_NAME', 'vzorky_dev');
// Dev verze sahá "přes plot" do ostré databáze
    define('DB_TBL_USERS', 'users');
    // Na vývoji chceme vidět všechny PHP chyby
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    if (!defined('IS_DEV')) define('IS_DEV', false);

    // Ostrá (produkční) databáze
    define('DB_HOST', 'localhost');
    define('DB_USER', 'vzorky');
    define('DB_PASS', 'vzorky');
    define('DB_NAME', 'vzorky');
    // Dev verze sahá "přes plot" do ostré databáze
    define('DB_TBL_USERS', 'vzorky.users');

    // Na produkci PHP chyby před uživateli skrýváme
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ==========================================
// 2. NEXTCLOUD
// ==========================================
define('NC_USER', 'aplikace_poptavky');
define('NC_PASS', 'vase-vygenerovane-app-heslo');

// ==========================================
// 🤖 TELEGRAM BOT & KANÁLY
// ==========================================
define('TG_BOT_TOKEN', '8790873615:AAHbJ9JAwHMrYVsunaCnhez6mVJIjZk1oLg');

// IDčka jednotlivých chatů/skupin
define('TG_CHAT_DEV', '8697871307');
define('TG_CHAT_VYVOJ', '-5288684842');
define('TG_CHAT_NAKUP', '-5199215023');
define('TG_CHAT_KVALITA', '-5297014569');

// ==========================================
// 📧 E-MAIL — pouze denní souhrn (cron_denni_souhrn.php)
// ==========================================
define('MAIL_ENABLED', true);
define('MAIL_FROM', 'noreply@lifefood.eu');
define('MAIL_FROM_NAME', 'Vzorkovna LifeFood');
define('MAIL_DEV', 'it@lifefood.eu'); // na dev prostředí jdou souhrny sem
define('MAIL_NAKUP', ''); // záloha, např. 'nakup@lifefood.eu'

// ==========================================
// 🌐 URL APLIKACE
// ==========================================
define('APP_BASE_URL', IS_DEV
    ? 'https://docs.lifefood.eu/dev-vzorky'
    : 'https://docs.lifefood.eu/vzorky'
);

// Původní nastavení cest (pokud ho tam potřebuješ)
set_include_path($_SERVER['DOCUMENT_ROOT'] . '/includes/');
?>