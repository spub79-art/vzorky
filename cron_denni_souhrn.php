<?php

// cron_denni_souhrn.php — denní e-mailový souhrn (Nákup, Vývoj, Kvalita)
//
// Crontab (/etc/crontab — POZOR na sloupec USER!):
//   0 8 * * 1-5 root cd /DATA/docs/vzorky && /usr/bin/php cron_denni_souhrn.php >> /DATA/docs/vzorky/logs/cron_souhrn.log 2>&1
//
// Špatně (chybí user → cron spouští jako uživatel „php“):
//   0 8 * * 1-5 php /DATA/docs/vzorky/cron_denni_souhrn.php

if (php_sapi_name() === 'cli') {
    chdir(__DIR__);
}

function cron_souhrn_log($msg) {
    $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    @file_put_contents($log_dir . '/cron_souhrn.log', $line, FILE_APPEND | LOCK_EX);
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $line);
    }
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        cron_souhrn_log('FATAL: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
    }
});

include_once("includes/db_connect.php");
include_once("includes/mail.php");
include_once("includes/digest_helpers.php");

if (empty($conn)) {
    cron_souhrn_log('CHYBA: nepodařilo se připojit k databázi.');
    exit(1);
}

@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

$BASE_URL = digest_base_url();
$is_preview = isset($_GET['preview']) && $_GET['preview'] == '1';
$is_cli = (php_sapi_name() === 'cli');

if ($is_preview) {    if (session_status() === PHP_SESSION_NONE) session_start();
    $is_dev = (strpos($_SERVER['REQUEST_URI'] ?? '', 'dev-vzorky') !== false);
    if (!$is_dev && empty($_SESSION['adm'])) {
        die('Náhled je dostupný jen pro administrátory (nebo na dev prostředí).');
    }
    header('Content-Type: text/html; charset=utf-8');
}



$email_channels = ['nakup', 'vyvoj', 'kvalita'];

$sent = [];

$skipped = [];



foreach ($email_channels as $ch) {

    $digest = digest_build($conn, $ch);

    if ($digest['total'] === 0) {

        $skipped[] = $ch;

        continue;

    }



    $meta = $digest['meta'];

    $html = wrapDigestEmail(

        $meta['title'],

        $meta['subtitle'],

        $digest['sections'],

        $BASE_URL

    );



    if ($is_preview) {
        if (!empty($sent)) {
            echo '<hr style="margin:40px 0;border:0;border-top:3px solid #ccc;">';
        }
        $email_ch = $meta['email_channel'] ?? $ch;
        $recipients = getEmailsForChannel($email_ch);
        echo '<div style="font-family:Arial,sans-serif;max-width:640px;margin:20px auto 0;padding:10px 14px;background:#fff3cd;border:1px solid #f0ad4e;border-radius:4px;font-size:13px;">';
        echo '<strong>' . htmlspecialchars(strtoupper($ch)) . ' — příjemci e-mailu:</strong> ';
        if (empty($recipients)) {
            echo '<span style="color:#d9534f;">žádní (role + zaškrtnutý Souhrn + vyplněný e-mail)</span>';
        } else {
            echo htmlspecialchars(implode(', ', $recipients));
        }
        echo ' · položek: ' . (int)$digest['total'];
        echo '</div>';
        echo $html;
        $sent[] = $ch;
        continue;
    }



    $email_ch = $meta['email_channel'] ?? $ch;

    $recipients = getEmailsForChannel($email_ch);

    if (empty($recipients)) {

        $skipped[] = $ch . ' (bez příjemců)';

        continue;

    }



    if (sendEmailTo($recipients, $digest['subject'], $html)) {

        $sent[] = $ch . ' → ' . implode(', ', $recipients) . " ({$digest['total']})";

    } else {

        $skipped[] = $ch . ' (chyba odeslání)';

    }

}



if ($is_preview) {
    if (empty($sent)) {
        echo '<p style="font-family:Arial;padding:20px;">Vše je čisté — žádná sekce k zobrazení.</p>';
        if (!empty($skipped)) {
            echo '<p style="font-family:Arial;padding:0 20px;color:#666;font-size:13px;">Přeskočeno (0 položek): ' . htmlspecialchars(implode(', ', $skipped)) . '</p>';
        }
    }
    exit;
}



if (empty($sent)) {
    $msg = 'Vše je čisté, nic neodesílám.' . (empty($skipped) ? '' : ' (' . implode('; ', $skipped) . ')');
    if ($is_cli) {
        cron_souhrn_log($msg);
        exit(0);
    }
    die($msg);
}

$out = 'Odesláno: ' . implode(' | ', $sent);
if (!empty($skipped)) {
    $out .= ' · Přeskočeno: ' . implode('; ', $skipped);
}
if ($is_cli) {
    cron_souhrn_log($out);
} else {
    echo $out;
}