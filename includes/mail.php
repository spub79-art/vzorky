<?php
include_once(__DIR__ . '/../config/config.php');

/**
 * E-mailové adresy pro kanál (nakup / vyvoj / kvalita).
 * Primárně z tabulky users podle role, záložně z MAIL_* v configu.
 */
function getEmailsForChannel($channel) {
    global $conn;

    $role_map = [
        'nakup'   => 'orders',
        'vyvoj'   => 'vyvoj',
        'kvalita' => 'kvalita',
    ];

    $emails = [];

    if (isset($role_map[$channel]) && isset($conn) && $conn) {
        $col = $role_map[$channel];
        $tbl = defined('DB_TBL_USERS') ? DB_TBL_USERS : 'users';
        $where = "`$col` = 1";
        if ($channel === 'nakup') {
            $where = "(`$col` = 1 OR IFNULL(nakup_pristup, 0) = 1)";
        }
        $souhrn_filter = '';
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM `$tbl` LIKE 'souhrn_email'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $souhrn_filter = ' AND IFNULL(souhrn_email, 0) = 1';
        }
        $q = mysqli_query($conn, "SELECT email FROM $tbl WHERE $where$souhrn_filter AND email IS NOT NULL AND TRIM(email) != ''");
        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $emails[] = trim($row['email']);
            }
        }
    }

    if (empty($emails)) {
        $const = 'MAIL_' . strtoupper($channel);
        if (defined($const) && constant($const) !== '') {
            foreach (explode(',', constant($const)) as $addr) {
                $addr = trim($addr);
                if ($addr !== '') $emails[] = $addr;
            }
        }
    }

    return array_values(array_unique(array_filter($emails, 'filter_var', FILTER_VALIDATE_EMAIL)));
}

/**
 * Lidsky čitelné stáří požadavku.
 */
function formatStariPozadavku($datum) {
    $sek = time() - strtotime($datum);
    $hodin = (int)floor($sek / 3600);
    $dni = (int)floor($sek / 86400);

    if ($hodin < 1) return 'právě teď';
    if ($hodin < 24) return $hodin . ' h';
    if ($dni === 1) return '1 den';
    if ($dni >= 2 && $dni <= 4) return $dni . ' dny';
    return $dni . ' dní';
}

/**
 * Vykreslí tabulku požadavků pro denní souhrn.
 */
function renderDigestTable($rows, $baseUrl) {
    if (empty($rows)) {
        return "<p style='color:#777;font-size:13px;margin:0;'>Žádné požadavky v této kategorii.</p>";
    }

    $html = "<table cellpadding='0' cellspacing='0' style='width:100%;border-collapse:collapse;font-size:13px;'>";
    $html .= "<tr style='background:#f5f5f5;color:#555;font-size:11px;text-transform:uppercase;'>";
    $html .= "<th style='padding:8px 6px;text-align:left;border-bottom:2px solid #ddd;'>Surovina</th>";
    $html .= "<th style='padding:8px 6px;text-align:left;border-bottom:2px solid #ddd;'>Zákazník</th>";
    $html .= "<th style='padding:8px 6px;text-align:center;border-bottom:2px solid #ddd;width:70px;'>Čeká</th>";
    $html .= "<th style='padding:8px 6px;text-align:left;border-bottom:2px solid #ddd;'>Řeší</th>";
    $html .= "<th style='padding:8px 6px;text-align:left;border-bottom:2px solid #ddd;'>Stav</th>";
    $html .= "</tr>";

    foreach ($rows as $r) {
        $link = htmlspecialchars($baseUrl . '/index.php?Pozadavek=1&req_id=' . $r['id']);
        $sur = htmlspecialchars($r['surovina_nazev']);
        $zak = !empty($r['zakaznici_seznam'])
            ? htmlspecialchars($r['zakaznici_seznam'])
            : '<span style="color:#aaa;">—</span>';
        if (!empty($r['produkty_seznam'])) {
            $zak .= '<br><span style="color:#8e44ad;font-size:11px;">&#128188; '
                . htmlspecialchars($r['produkty_seznam']) . '</span>';
        }

        $stari = formatStariPozadavku($r['datumPozadavek']);
        $dni = (int)floor((time() - strtotime($r['datumPozadavek'])) / 86400);
        $stari_color = ($dni >= 3) ? '#d9534f' : (($dni >= 1) ? '#f0ad4e' : '#555');
        $stari_weight = ($dni >= 3) ? 'bold' : 'normal';

        if (!empty($r['stav_text'])) {
            $stav_text = htmlspecialchars($r['stav_text']);
        } else {
            $stav = [];
            if (!empty($r['vyzadano'])) $stav[] = '🔔 vyžádána nabídka';
            if (!empty($r['pocet_nabidek'])) {
                $stav[] = $r['pocet_nabidek'] . '× nabídka (vše KO)';
            } else {
                $stav[] = 'bez nabídky';
            }
            if (!empty($r['bio'])) $stav[] = 'BIO';
            if (!empty($r['vegan'])) $stav[] = 'Vegan';
            $stav_text = implode(' · ', $stav);
        }

        $resitel = !empty($r['nakupci_jmeno'])
            ? htmlspecialchars($r['nakupci_jmeno'])
            : '<span style="color:#d9534f;">Nepřevzato</span>';

        $html .= "<tr style='border-bottom:1px solid #eee;'>";
        $html .= "<td style='padding:8px 6px;'><a href='$link' style='color:#2c3e50;font-weight:bold;text-decoration:none;'>$sur</a>";
        $html .= "<br><span style='color:#999;font-size:11px;'>#$r[id]</span></td>";
        $html .= "<td style='padding:8px 6px;'>$zak</td>";
        $html .= "<td style='padding:8px 6px;text-align:center;color:$stari_color;font-weight:$stari_weight;white-space:nowrap;'>$stari</td>";
        $html .= "<td style='padding:8px 6px;font-size:12px;'>$resitel</td>";
        $html .= "<td style='padding:8px 6px;color:#666;font-size:12px;'>$stav_text</td>";
        $html .= "</tr>";
    }

    $html .= "</table>";
    return $html;
}

/**
 * Vnitřní obsah souhrnu (web i e-mail).
 */
function buildDigestBody($title, $subtitle, $sections, $baseUrl, $footer_note = null) {
    $html = "<div class='digest-email-card' style='max-width:100%;background:#fff;border:1px solid #ddd;border-radius:6px;overflow:hidden;'>";

    $html .= "<div style='background:#2c3e50;color:#fff;padding:16px 20px;'>";
    $html .= "<h2 style='margin:0;font-size:18px;'>" . htmlspecialchars($title) . "</h2>";
    $html .= "<p style='margin:4px 0 0;font-size:13px;color:#bdc3c7;'>" . htmlspecialchars($subtitle) . "</p>";
    $html .= "</div>";

    $html .= "<div style='padding:20px;'>";
    foreach ($sections as $sec) {
        $html .= "<div style='margin-bottom:24px;'>";
        $html .= "<h3 style='margin:0 0 10px;padding-bottom:6px;font-size:15px;color:{$sec['color']};border-bottom:2px solid {$sec['color']};'>";
        $html .= htmlspecialchars($sec['title']) . " <span style='font-weight:normal;color:#999;'>(" . count($sec['rows']) . ")</span>";
        $html .= "</h3>";
        $html .= renderDigestTable($sec['rows'], $baseUrl);
        $html .= "</div>";
    }

    $html .= "<div style='text-align:center;margin-top:10px;'>";
    $html .= "<a href='" . htmlspecialchars($baseUrl) . "' style='display:inline-block;background:#337ab7;color:#fff;padding:10px 24px;text-decoration:none;border-radius:4px;font-weight:bold;font-size:14px;'>Otevřít Vzorkovnu</a>";
    $html .= "</div></div>";

    if ($footer_note !== null) {
        $html .= "<div style='background:#f8f9fa;text-align:center;padding:10px;font-size:11px;color:#999;border-top:1px solid #e3e3e3;'>";
        $html .= htmlspecialchars($footer_note);
        $html .= "</div>";
    }

    $html .= "</div>";
    return $html;
}

/**
 * Obalí sekce do kompletního HTML e-mailu.
 */
function wrapDigestEmail($title, $subtitle, $sections, $baseUrl) {
    $footer = 'Denní souhrn · ' . date('j.n.Y H:i') . ' · neodpovídejte na tento e-mail';
    return "<html><body style='font-family:Arial,Helvetica,sans-serif;color:#333;line-height:1.5;margin:0;padding:0;background:#f0f0f0;'>"
        . "<div style='max-width:640px;margin:20px auto;'>"
        . buildDigestBody($title, $subtitle, $sections, $baseUrl, $footer)
        . "</div></body></html>";
}

/**
 * Odešle HTML e-mail na zadané adresy.
 */
function sendEmailTo($recipients, $subject, $html) {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) return false;

    if (defined('IS_DEV') && IS_DEV && defined('MAIL_DEV') && MAIL_DEV !== '') {
        $recipients = [MAIL_DEV];
        $subject = '[TEST] ' . $subject;
    }

    $recipients = array_values(array_unique(array_filter(
        is_array($recipients) ? $recipients : explode(',', $recipients),
        function ($addr) { return filter_var(trim($addr), FILTER_VALIDATE_EMAIL); }
    )));

    if (empty($recipients)) return false;

    $from = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' : MAIL_FROM;
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $from\r\n";

    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = false;

    foreach ($recipients as $to) {
        if (@mail(trim($to), $encoded_subject, $html, $headers)) {
            $ok = true;
        }
    }

    return $ok;
}
