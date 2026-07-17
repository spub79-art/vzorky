<?php
include_once(__DIR__ . '/../config/config.php');
include_once(__DIR__ . '/digest_helpers.php');

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
        $souhrn_filter = '';
        // Bez backticků kolem celého $tbl — vzorky.users by se jinak četlo jako tabulka „vzorky.users“ v DB vzorky
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM $tbl LIKE 'souhrn_email'");
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
 * Odkaz na detail požadavku — otevře Souhrn s modalem (web i e-mail).
 */
function digest_request_url($baseUrl, $reqId) {
    return rtrim($baseUrl, '/') . '/index.php?Souhrn=1&req_id=' . (int)$reqId;
}

/**
 * Vykreslí buňku priority (skóre + vizuál).
 */
function renderDigestPriorityCell($r, $forEmail = false) {
    $score = (int)($r['digest_score'] ?? 0);
    $tags = $r['digest_tags'] ?? [];
    $breakdown = $r['digest_breakdown'] ?? [];
    $tip = implode('; ', $breakdown);
    if ($tip === '') {
        $tip = 'Skóre ' . $score;
    }

    $pct = min(100, max(5, (int)round($score / 2)));
    if ($score >= 120) {
        $color = '#d9534f';
    } elseif ($score >= 80) {
        $color = '#f0ad4e';
    } elseif ($score >= 40) {
        $color = '#5bc0de';
    } else {
        $color = '#95a5a6';
    }

    $tags_html = '';
    if (!empty($tags)) {
        $tags_html = '<div style="font-size:10px;color:#777;margin-top:2px;">' . htmlspecialchars(implode(' ', $tags)) . '</div>';
    }

    if ($forEmail) {
        return "<span style='display:inline-block;background:$color;color:#fff;font-weight:bold;padding:2px 6px;border-radius:3px;font-size:12px;'>$score</span>" . $tags_html;
    }

    $tip_esc = htmlspecialchars($tip, ENT_QUOTES, 'UTF-8');
    return "<div class='digest-priority' title='$tip_esc'>"
        . "<span class='digest-priority-score' style='color:$color;font-weight:bold;'>$score</span>"
        . "<div class='digest-priority-bar'><span style='width:{$pct}%;background:$color;'></span></div>"
        . $tags_html
        . "</div>";
}

/**
 * Vykreslí tabulku požadavků pro denní souhrn.
 */
function renderDigestTable($rows, $baseUrl, $forEmail = false) {
    if (empty($rows)) {
        return "<p style='color:#777;font-size:13px;margin:0;'>Žádné požadavky v této kategorii.</p>";
    }

    if (!function_exists('digest_format_last_activity')) {
        include_once(__DIR__ . '/digest_helpers.php');
    }

    $tableClass = $forEmail ? '' : ' digest-table';
    $html = "<table cellpadding='0' cellspacing='0' class='table table-condensed{$tableClass}' style='width:100%;border-collapse:collapse;font-size:13px;margin-bottom:0;'>";
    $html .= "<tr style='background:#f5f5f5;color:#555;font-size:11px;text-transform:uppercase;'>";
    $html .= "<th style='padding:8px 6px;text-align:left;border-bottom:2px solid #ddd;'>Surovina</th>";
    $html .= "<th style='padding:8px 6px;text-align:left;border-bottom:2px solid #ddd;'>Zákazník / portfolio</th>";
    $html .= "<th style='padding:8px 6px;text-align:center;border-bottom:2px solid #ddd;width:88px;'>Aktivita</th>";
    $html .= "<th style='padding:8px 6px;text-align:center;border-bottom:2px solid #ddd;width:56px;'>Čeká</th>";
    $html .= "<th style='padding:8px 6px;text-align:center;border-bottom:2px solid #ddd;width:72px;'>Priorita</th>";
    $html .= "<th style='padding:8px 6px;text-align:left;border-bottom:2px solid #ddd;'>Řeší</th>";
    $html .= "<th style='padding:8px 6px;text-align:left;border-bottom:2px solid #ddd;'>Čeká se</th>";
    $html .= "</tr>";

    foreach ($rows as $r) {
        $link = htmlspecialchars(digest_request_url($baseUrl, $r['id']));
        $delta = $r['digest_delta'] ?? '';
        $delta_badge = '';
        $row_style = 'border-bottom:1px solid #eee;';
        if ($delta === 'new') {
            $delta_badge = "<span style='display:inline-block;background:#27ae60;color:#fff;font-size:10px;font-weight:bold;padding:1px 6px;border-radius:3px;margin-right:4px;'>NOVÉ</span>";
            $row_style .= 'background:#eefaf3;';
        } elseif ($delta === 'changed') {
            $delta_badge = "<span style='display:inline-block;background:#f0ad4e;color:#fff;font-size:10px;font-weight:bold;padding:1px 6px;border-radius:3px;margin-right:4px;'>ZMĚNA</span>";
            $row_style .= 'background:#fff8eb;';
        }

        $sur = htmlspecialchars($r['surovina_nazev']);
        if (!empty($r['digest_primary_nabidka'])) {
            $dod = htmlspecialchars($r['digest_primary_dod'] ?? '—');
            $sur .= '<br><span class="digest-offer-line" style="color:#555;font-size:11px;font-weight:normal;">'
                . $dod . ' · #' . (int)$r['digest_primary_nabidka'] . '</span>';
        }
        if (!empty($r['digest_kontext'])) {
            $sur .= '<br><span class="digest-kontext">' . htmlspecialchars($r['digest_kontext']) . '</span>';
        }
        if (function_exists('digest_cert_badges_html')) {
            $sur .= digest_cert_badges_html($r);
        }
        $zak = !empty($r['zakaznici_seznam'])
            ? htmlspecialchars($r['zakaznici_seznam'])
            : '<span style="color:#aaa;">—</span>';
        if (!empty($r['produkty_seznam'])) {
            $zak .= '<br><span style="color:#8e44ad;font-size:11px;">&#128188; '
                . htmlspecialchars($r['produkty_seznam']) . '</span>';
        }
        if (digest_is_snooze_active($r)) {
            $snooze_note = trim($r['souhrn_snooze_poznamka'] ?? '');
            $until = date('j.n.', strtotime($r['souhrn_snooze_do']));
            $zak .= '<br><span style="color:#7f8c8d;font-size:11px;">&#9203; dlouhé dodání do ' . htmlspecialchars($until);
            if ($snooze_note !== '') {
                $zak .= ' — ' . htmlspecialchars($snooze_note);
            }
            $zak .= '</span>';
        }

        $aktivita = digest_format_last_activity($r['digest_last_activity'] ?? null);
        $priorita = renderDigestPriorityCell($r, $forEmail);

        $stari = formatStariPozadavku($r['datumPozadavek']);
        $dni = (int)floor((time() - strtotime($r['datumPozadavek'])) / 86400);
        $stari_color = ($dni >= 30) ? '#d9534f' : (($dni >= 7) ? '#f0ad4e' : '#555');
        $stari_weight = ($dni >= 30) ? 'bold' : 'normal';

        if (!empty($r['stav_text'])) {
            $stav_plain = $r['stav_text'];
        } else {
            $stav_plain = digest_ceka_label('nakup', $r);
        }
        $ceka_tip = trim($r['ceka_detail'] ?? '');
        if ($forEmail) {
            $stav_text = htmlspecialchars($stav_plain);
            if ($ceka_tip !== '') {
                $stav_text .= '<br><span style="color:#999;font-size:10px;">' . htmlspecialchars($ceka_tip) . '</span>';
            }
        } else {
            $stav_text = htmlspecialchars($stav_plain);
            if ($ceka_tip !== '') {
                $stav_text = '<span title="' . htmlspecialchars($ceka_tip, ENT_QUOTES, 'UTF-8') . '">' . $stav_text . '</span>';
            }
        }

        $resitel = !empty($r['nakupci_jmeno'])
            ? htmlspecialchars($r['nakupci_jmeno'])
            : '<span style="color:#d9534f;">Nepřevzato</span>';

        if ($forEmail) {
            $html .= "<tr style='$row_style'>";
            $html .= "<td style='padding:8px 6px;'>" . $delta_badge . "<a href='$link' style='color:#2c3e50;font-weight:bold;text-decoration:none;'>$sur</a>";
            $html .= "<br><span style='color:#999;font-size:11px;'>#$r[id]</span></td>";
            $html .= "<td style='padding:8px 6px;font-size:12px;'>$zak</td>";
            $html .= "<td style='padding:8px 6px;text-align:center;font-size:11px;white-space:nowrap;'>$aktivita</td>";
            $html .= "<td style='padding:8px 6px;text-align:center;color:$stari_color;font-weight:$stari_weight;white-space:nowrap;'>$stari</td>";
            $html .= "<td style='padding:8px 6px;text-align:center;'>$priorita</td>";
            $html .= "<td style='padding:8px 6px;font-size:12px;'>$resitel</td>";
            $html .= "<td style='padding:8px 6px;color:#666;font-size:12px;'>$stav_text</td>";
            $html .= "</tr>";
        } else {
            $rid = (int)$r['id'];
            $tip = htmlspecialchars(implode('; ', $r['digest_breakdown'] ?? []), ENT_QUOTES, 'UTF-8');
            $band = !empty($r['digest_band']) ? ' digest-row-band-' . strtolower($r['digest_band']) : '';
            $delta_class = ($delta === 'new') ? ' digest-row-new' : (($delta === 'changed') ? ' digest-row-changed' : '');
            $html .= "<tr class='digest-row{$band}{$delta_class}' data-req-id='$rid' tabindex='0' role='link' title='Otevřít detail #$rid · $tip'>";
            $html .= "<td class='digest-cell-sur'>" . $delta_badge . "<strong>$sur</strong><br><span class='digest-req-id'>#$rid</span></td>";
            $html .= "<td class='digest-cell-zak'>$zak</td>";
            $html .= "<td class='digest-cell-activity' style='text-align:center;font-size:11px;white-space:nowrap;'>$aktivita</td>";
            $html .= "<td class='digest-cell-stari' style='color:$stari_color;font-weight:$stari_weight;text-align:center;'>$stari</td>";
            $html .= "<td class='digest-cell-priority' style='text-align:center;'>$priorita</td>";
            $html .= "<td class='digest-cell-resitel'>$resitel</td>";
            $html .= "<td class='digest-cell-stav'>$stav_text</td>";
            $html .= "</tr>";
        }
    }

    $html .= "</table>";
    return $html;
}

/**
 * Vnitřní obsah souhrnu (web i e-mail).
 */
function buildDigestBody($title, $subtitle, $sections, $baseUrl, $footer_note = null, $forEmail = false, array $delta_stats = []) {
    $html = "<div class='digest-email-card' style='max-width:100%;background:#fff;border:1px solid #ddd;border-radius:6px;overflow:hidden;'>";

    $html .= "<div style='background:#2c3e50;color:#fff;padding:16px 20px;'>";
    $html .= "<h2 style='margin:0;font-size:18px;'>" . htmlspecialchars($title) . "</h2>";
    $html .= "<p style='margin:4px 0 0;font-size:13px;color:#bdc3c7;'>" . htmlspecialchars($subtitle) . "</p>";
    $html .= "</div>";

    $html .= "<div style='padding:20px;'>";

    $band_counts = ['A' => 0, 'B' => 0, 'C' => 0];
    foreach ($sections as $sec) {
        if (!empty($sec['band']) && empty($sec['dimmed'])) {
            $band_counts[$sec['band']] = count($sec['rows']);
        }
    }
    if (!empty($delta_stats['has_prev']) && (($delta_stats['new'] ?? 0) + ($delta_stats['changed'] ?? 0)) > 0) {
        $html .= "<div style='background:#fff3cd;border:1px solid #f0ad4e;border-radius:4px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#8a6d3b;'>";
        $html .= "<strong>Od minulého mailu:</strong> ";
        $dparts = [];
        if (!empty($delta_stats['new'])) {
            $dparts[] = (int)$delta_stats['new'] . ' nov' . ($delta_stats['new'] === 1 ? 'á' : ($delta_stats['new'] < 5 ? 'é' : 'ých'));
        }
        if (!empty($delta_stats['changed'])) {
            $dparts[] = (int)$delta_stats['changed'] . ' změn' . ($delta_stats['changed'] === 1 ? 'a' : ($delta_stats['changed'] < 5 ? 'y' : ''));
        }
        $html .= htmlspecialchars(implode(', ', $dparts));
        if (!empty($delta_stats['same'])) {
            $html .= ' · ' . (int)$delta_stats['same'] . ' beze změny';
        }
        $html .= "</div>";
    }

    if ($band_counts['A'] + $band_counts['B'] + $band_counts['C'] > 0) {
        $html .= "<div class='digest-band-stats' style='display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;font-size:12px;'>";
        $html .= "<span style='background:#fdecea;color:#c0392b;padding:4px 10px;border-radius:4px;font-weight:bold;'>"
            . "Teď vy: " . $band_counts['A'] . "</span>";
        if ($band_counts['B'] > 0) {
            $html .= "<span style='background:#fef5e7;color:#d68910;padding:4px 10px;border-radius:4px;'>"
                . "Čeká na ostatní: " . $band_counts['B'] . "</span>";
        }
        if ($band_counts['C'] > 0) {
            $html .= "<span style='background:#ebf5fb;color:#2980b9;padding:4px 10px;border-radius:4px;'>"
                . "U vás potom: " . $band_counts['C'] . "</span>";
        }
        $html .= "</div>";
    }

    foreach ($sections as $sec) {
        $is_dimmed = !empty($sec['dimmed']);
        $band = !empty($sec['band']) ? strtolower($sec['band']) : '';
        $sec_class = 'digest-section';
        if ($is_dimmed) {
            $sec_class .= ' digest-section-dimmed';
        }
        if ($band !== '') {
            $sec_class .= ' digest-band-' . $band;
        }
        $title_color = $is_dimmed ? '#95a5a6' : $sec['color'];
        $border_color = $is_dimmed ? '#ddd' : $sec['color'];
        $html .= "<div class='$sec_class' style='margin-bottom:24px;'>";
        $html .= "<h3 style='margin:0 0 10px;padding-bottom:6px;font-size:" . ($is_dimmed ? '13px' : '15px') . ";color:$title_color;border-bottom:2px solid $border_color;'>";
        $html .= htmlspecialchars($sec['title']) . " <span style='font-weight:normal;color:#999;'>(" . count($sec['rows']) . ")</span>";
        $html .= "</h3>";
        $html .= renderDigestTable($sec['rows'], $baseUrl, $forEmail);
        $html .= "</div>";
    }

    $html .= "</div>";

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
function wrapDigestEmail($title, $subtitle, $sections, $baseUrl, array $delta_stats = []) {
    $footer = 'Denní souhrn · ' . date('j.n.Y H:i') . ' · neodpovídejte na tento e-mail';
    return "<html><body style='font-family:Arial,Helvetica,sans-serif;color:#333;line-height:1.5;margin:0;padding:0;background:#f0f0f0;'>"
        . "<div style='max-width:720px;margin:20px auto;'>"
        . buildDigestBody($title, $subtitle, $sections, $baseUrl, $footer, true, $delta_stats)
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
