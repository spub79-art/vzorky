<?php

// cron_denni_souhrn.php — denní e-mailový souhrn (Nákup, Vývoj, Kvalita)

// Spouštět 1× denně přes cron, např.: 0 8 * * 1-5 php /cesta/cron_denni_souhrn.php



include_once("includes/db_connect.php");

include_once("includes/mail.php");

include_once("includes/digest_helpers.php");

@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");



$BASE_URL = digest_base_url();

$is_preview = isset($_GET['preview']) && $_GET['preview'] == '1';



if ($is_preview) {
    if (session_status() === PHP_SESSION_NONE) session_start();
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

    die('Vše je čisté, nic neodesílám.' . (empty($skipped) ? '' : ' (' . implode('; ', $skipped) . ')'));

}



echo 'Odesláno: ' . implode(' | ', $sent);

if (!empty($skipped)) {

    echo ' · Přeskočeno: ' . implode('; ', $skipped);

}

