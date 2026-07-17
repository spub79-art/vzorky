<?php

// cron_denni_souhrn.php — denní e-mailový souhrn (Nákup, Vývoj, Kvalita)
//
// Cron (/etc/cron.d/vzorky-souhrn):
//   0 8 * * 1-5 root cd /DATA/docs/vzorky && mkdir -p logs && /usr/bin/php8.2 cron_denni_souhrn.php >> logs/cron_souhrn.log 2>&1
//
// Log: skript zapisuje sám do logs/cron_souhrn.log — 2>&1 jen pro neodchycené PHP chyby (bez duplicitního řádku).

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
    $prev_state = digest_load_channel_state($ch);
    $sig = digest_signature($digest);

    if (!$is_preview && $prev_state['sig'] !== '' && hash_equals($prev_state['sig'], $sig)) {
        $skipped[] = $ch . ' (beze změny)';
        continue;
    }

    $delta_stats = digest_apply_delta($digest['sections'], $prev_state['items']);
    $digest['subject'] = digest_subject_with_delta(
        $digest['sections'],
        $meta['subject_role'] ?? $meta['label'] ?? $ch,
        $delta_stats
    );

    $html = wrapDigestEmail(
        $meta['title'],
        $meta['subtitle'],
        $digest['sections'],
        $BASE_URL,
        $delta_stats
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
        if (!empty($delta_stats['has_prev'])) {
            echo ' · nové: ' . (int)$delta_stats['new'] . ', změny: ' . (int)$delta_stats['changed'];
        }
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
        digest_save_channel_state($ch, $digest);

        $delta_note = '';
        if (!empty($delta_stats['has_prev'])) {
            $delta_note = ' [+' . (int)$delta_stats['new'] . ' nových, ' . (int)$delta_stats['changed'] . ' změn]';
        }
        $sent[] = $ch . ' → ' . implode(', ', $recipients) . " ({$digest['total']})" . $delta_note;

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