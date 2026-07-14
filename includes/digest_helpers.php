<?php
/**
 * Denní souhrn / přehled k řešení — sdílená logika pro web i e-mail.
 */

include_once(__DIR__ . '/boardFunctions.php');

function digest_base_url() {
    if (defined('APP_BASE_URL') && APP_BASE_URL !== '') {
        return rtrim(APP_BASE_URL, '/');
    }
    $is_dev = (strpos($_SERVER['REQUEST_URI'] ?? '', 'dev-vzorky') !== false);
    return $is_dev ? 'https://docs.lifefood.eu/dev-vzorky' : 'https://docs.lifefood.eu/vzorky';
}

function digest_users_table() {
    return defined('DB_TBL_USERS') ? DB_TBL_USERS : 'users';
}

function digest_produkty_sql_extra($conn) {
    if (!function_exists('pf_has_table')) {
        @include_once(__DIR__ . '/portfolio_helpers.php');
    }
    if (!function_exists('pf_has_table') || !pf_has_table($conn, 'pozadavky_produkty')) {
        return "NULL AS produkty_seznam,";
    }
    return "(SELECT GROUP_CONCAT(CONCAT(IFNULL(z.nazev, '—'), ' → ', pr.nazev) ORDER BY pr.nazev SEPARATOR ', ')
         FROM pozadavky_produkty pp
         JOIN produkty pr ON pp.id_produkt = pr.id AND pr.ukonceny = 0
         LEFT JOIN zakaznici z ON pr.id_zakaznik = z.id
         WHERE pp.id_pozadavek = p.id) AS produkty_seznam,";
}

function digest_ping_ids($conn) {
    $ping_ids = [];
    $vcera = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $q = mysqli_query($conn, "SELECT DISTINCT id_pozadavek FROM historie_pozadavku
                              WHERE typ_zaznamu = 'ping_nakup' AND vytvoreno >= '$vcera'");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $ping_ids[(int)$r['id_pozadavek']] = true;
        }
    }
    return $ping_ids;
}

function digest_offer_has_spec($files_str) {
    foreach (explode('^', (string)($files_str ?? '')) as $f) {
        if ($f === '') continue;
        $pts = explode('~', $f);
        if (($pts[1] ?? '') === 'spec') return true;
    }
    return false;
}

/** Váhy skóre Souhrnu — laditelné na jednom místě. */
function digest_score_weights() {
    return [
        'req_urgent'       => 80,
        'prod_urgent'      => 40,
        'urg_comment_7d'   => 70,
        'urgence_7d'       => 60,
        'ping_48h'         => 50,
        'comment_each_7d'  => 10,
        'comment_max_7d'   => 30,
        'day_point'        => 1,
        'day_cap'          => 30,
        'snooze_penalty'   => -55,
    ];
}

function digest_has_snooze_columns($conn) {
    if (!function_exists('pf_has_column')) {
        @include_once(__DIR__ . '/portfolio_helpers.php');
    }
    return function_exists('pf_has_column') && pf_has_column($conn, 'pozadavky', 'souhrn_snooze_do');
}

/** @return string SQL fragment */
function digest_snooze_sql_select($conn) {
    if (digest_has_snooze_columns($conn)) {
        return 'p.souhrn_snooze_do, p.souhrn_snooze_poznamka,';
    }
    return 'NULL AS souhrn_snooze_do, NULL AS souhrn_snooze_poznamka,';
}

function digest_is_snooze_active($row) {
    $until = $row['souhrn_snooze_do'] ?? null;
    if ($until === null || $until === '') {
        return false;
    }
    return strtotime($until) > time();
}

/**
 * @param int[] $req_ids
 * @return array<int, array{last_at:string|null, comments_7d:int, urg_comments_7d:int, urgence_7d:int, ping_48h:bool}>
 */
function digest_load_activity_stats($conn, array $req_ids) {
    $req_ids = array_values(array_unique(array_filter(array_map('intval', $req_ids))));
    if (empty($req_ids)) {
        return [];
    }
    $ids_str = implode(',', $req_ids);
    $out = [];
    foreach ($req_ids as $rid) {
        $out[$rid] = [
            'last_at' => null,
            'comments_7d' => 0,
            'urg_comments_7d' => 0,
            'urgence_7d' => 0,
            'ping_48h' => false,
            'by_nabidka' => [],
        ];
    }

    $sql = "SELECT id_pozadavek, id_nabidka, typ_zaznamu, vytvoreno
            FROM historie_pozadavku
            WHERE id_pozadavek IN ($ids_str)
            ORDER BY vytvoreno DESC";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return $out;
    }

    $now = time();
    $t7 = $now - 7 * 86400;
    $t48 = $now - 48 * 3600;

    while ($row = mysqli_fetch_assoc($res)) {
        $rid = (int)$row['id_pozadavek'];
        if (!isset($out[$rid])) {
            continue;
        }
        $nid = (int)$row['id_nabidka'];
        $ts = strtotime($row['vytvoreno']);
        $typ = $row['typ_zaznamu'];

        $keys = ['req'];
        if ($nid > 0) {
            $keys[] = 'n_' . $nid;
        }
        foreach ($keys as $k) {
            if (!isset($out[$rid]['by_nabidka'][$k])) {
                $out[$rid]['by_nabidka'][$k] = [
                    'last_at' => null,
                    'comments_7d' => 0,
                    'urg_comments_7d' => 0,
                    'urgence_7d' => 0,
                    'ping_48h' => false,
                ];
            }
            $b = &$out[$rid]['by_nabidka'][$k];
            if ($b['last_at'] === null) {
                $b['last_at'] = $row['vytvoreno'];
            }
            if ($ts >= $t7) {
                if ($typ === 'komentar') {
                    $b['comments_7d']++;
                } elseif ($typ === 'komentar_urgentni') {
                    $b['urg_comments_7d']++;
                } elseif ($typ === 'urgence') {
                    $b['urgence_7d']++;
                }
            }
            if ($typ === 'ping_nakup' && $ts >= $t48) {
                $b['ping_48h'] = true;
            }
            unset($b);
        }

        if ($out[$rid]['last_at'] === null) {
            $out[$rid]['last_at'] = $row['vytvoreno'];
        }
        if ($ts >= $t7) {
            if ($typ === 'komentar') {
                $out[$rid]['comments_7d']++;
            } elseif ($typ === 'komentar_urgentni') {
                $out[$rid]['urg_comments_7d']++;
            } elseif ($typ === 'urgence') {
                $out[$rid]['urgence_7d']++;
            }
        }
        if ($typ === 'ping_nakup' && $ts >= $t48) {
            $out[$rid]['ping_48h'] = true;
        }
    }

    return $out;
}

/**
 * @param int[] $req_ids
 * @return array<int, bool> id_pozadavek => má urgentní produkt
 */
function digest_load_prod_urgent_flags($conn, array $req_ids) {
    $req_ids = array_values(array_unique(array_filter(array_map('intval', $req_ids))));
    if (empty($req_ids) || !function_exists('pf_has_table')) {
        @include_once(__DIR__ . '/portfolio_helpers.php');
    }
    if (empty($req_ids) || !function_exists('pf_has_table') || !pf_has_table($conn, 'pozadavky_produkty')) {
        return [];
    }
    if (!function_exists('pf_has_column') || !pf_has_column($conn, 'produkty', 'priorita')) {
        return [];
    }
    $ids_str = implode(',', $req_ids);
    $out = [];
    $sql = "SELECT pp.id_pozadavek, MAX(pr.priorita) AS m
            FROM pozadavky_produkty pp
            INNER JOIN produkty pr ON pr.id = pp.id_produkt AND pr.ukonceny = 0
            WHERE pp.id_pozadavek IN ($ids_str)
            GROUP BY pp.id_pozadavek";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            if ((int)$row['m'] === 1) {
                $out[(int)$row['id_pozadavek']] = true;
            }
        }
    }
    return $out;
}

/**
 * @param int[] $req_ids
 * @return array<int, bool> pouze nabídky ve fázi 3 (10/4)
 */
function digest_load_f3_only_flags($conn, array $req_ids) {
    $req_ids = array_values(array_unique(array_filter(array_map('intval', $req_ids))));
    if (empty($req_ids)) {
        return [];
    }
    $ids_str = implode(',', $req_ids);
    $out = [];
    $sql = "SELECT id_pozadavek,
            SUM(CASE WHEN id_status NOT IN (5, 7) THEN 1 ELSE 0 END) AS active_cnt,
            SUM(CASE WHEN id_status NOT IN (5, 7) AND id_status NOT IN (10, 4) THEN 1 ELSE 0 END) AS non_f3_cnt
            FROM pozadavky_nabidky
            WHERE id_pozadavek IN ($ids_str)
            GROUP BY id_pozadavek";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rid = (int)$row['id_pozadavek'];
            $out[$rid] = ((int)$row['active_cnt'] > 0 && (int)$row['non_f3_cnt'] === 0);
        }
    }
    return $out;
}

/**
 * @return array{score:int, tags:array, breakdown:array, last_activity:string|null}
 */
function digest_compute_row_score(array $row, array $activity, $channel, $offer_id = 0) {
    $w = digest_score_weights();
    $rid = (int)($row['id'] ?? 0);
    $score = 0;
    $tags = [];
    $breakdown = [];

    $act = $activity;
    if ($offer_id > 0 && !empty($activity['by_nabidka'])) {
        $merged = [
            'last_at' => $activity['last_at'] ?? null,
            'comments_7d' => (int)($activity['comments_7d'] ?? 0),
            'urg_comments_7d' => (int)($activity['urg_comments_7d'] ?? 0),
            'urgence_7d' => (int)($activity['urgence_7d'] ?? 0),
            'ping_48h' => !empty($activity['ping_48h']),
        ];
        foreach (['req', 'n_' . $offer_id] as $k) {
            if (empty($activity['by_nabidka'][$k])) {
                continue;
            }
            $b = $activity['by_nabidka'][$k];
            if (!empty($b['last_at']) && ($merged['last_at'] === null || strtotime($b['last_at']) > strtotime($merged['last_at']))) {
                $merged['last_at'] = $b['last_at'];
            }
            $merged['comments_7d'] = max($merged['comments_7d'], (int)$b['comments_7d']);
            $merged['urg_comments_7d'] = max($merged['urg_comments_7d'], (int)$b['urg_comments_7d']);
            $merged['urgence_7d'] = max($merged['urgence_7d'], (int)$b['urgence_7d']);
            $merged['ping_48h'] = $merged['ping_48h'] || !empty($b['ping_48h']);
        }
        $act = $merged;
    }

    if (!empty($row['priorita'])) {
        $score += $w['req_urgent'];
        $tags[] = 'URG';
        $breakdown[] = 'požadavek URG +' . $w['req_urgent'];
    }
    if (!empty($row['prod_urgent'])) {
        $score += $w['prod_urgent'];
        $tags[] = 'URG prod';
        $breakdown[] = 'urgentní produkt +' . $w['prod_urgent'];
    }
    if (!empty($act['urg_comments_7d'])) {
        $score += $w['urg_comment_7d'];
        $tags[] = '💬!';
        $breakdown[] = 'urgentní komentář +' . $w['urg_comment_7d'];
    }
    if (!empty($act['urgence_7d'])) {
        $score += $w['urgence_7d'];
        $tags[] = '⚡';
        $breakdown[] = 'urgence +' . $w['urgence_7d'];
    }
    if (!empty($act['ping_48h']) || !empty($row['vyzadano'])) {
        $score += $w['ping_48h'];
        $tags[] = '🔔';
        $breakdown[] = 'ping nákup +' . $w['ping_48h'];
    }
    $c7 = (int)($act['comments_7d'] ?? 0);
    if ($c7 > 0) {
        $c_pts = min($w['comment_max_7d'], $c7 * $w['comment_each_7d']);
        $score += $c_pts;
        $tags[] = '💬' . $c7;
        $breakdown[] = 'komentáře +' . $c_pts;
    }

    $datum = $row['datumPozadavek'] ?? null;
    if ($datum) {
        $dni = (int)floor((time() - strtotime($datum)) / 86400);
        $dni_cap = min($w['day_cap'], max(0, $dni));
        $day_pts = $dni_cap * $w['day_point'];
        if ($day_pts > 0) {
            $score += $day_pts;
            $breakdown[] = 'stáří ' . $dni_cap . ' d +' . $day_pts;
        }
    }

    if (digest_is_snooze_active($row)) {
        $score += $w['snooze_penalty'];
        $tags[] = '⏳';
        $until = date('j.n.', strtotime($row['souhrn_snooze_do']));
        $breakdown[] = 'dlouhé dodání ' . $w['snooze_penalty'] . ' (do ' . $until . ')';
    }

    return [
        'score' => $score,
        'tags' => $tags,
        'breakdown' => $breakdown,
        'last_activity' => $act['last_at'] ?? null,
    ];
}

/**
 * @param array<int, array> $rows
 */
function digest_enrich_rows($conn, array &$rows, $channel) {
    if (empty($rows)) {
        return;
    }
    $req_ids = [];
    foreach ($rows as $r) {
        $req_ids[] = (int)($r['id'] ?? 0);
    }
    $activity = digest_load_activity_stats($conn, $req_ids);
    $prod_urg = digest_load_prod_urgent_flags($conn, $req_ids);
    $f3_only = digest_load_f3_only_flags($conn, $req_ids);

    foreach ($rows as &$row) {
        $rid = (int)$row['id'];
        $row['prod_urgent'] = !empty($prod_urg[$rid]);
        $row['digest_f3_only'] = !empty($f3_only[$rid]);
        $offer_id = (int)($row['id_nabidka'] ?? 0);
        $act = $activity[$rid] ?? [
            'last_at' => null, 'comments_7d' => 0, 'urg_comments_7d' => 0,
            'urgence_7d' => 0, 'ping_48h' => false, 'by_nabidka' => [],
        ];
        $computed = digest_compute_row_score($row, $act, $channel, $offer_id);
        $row['digest_score'] = $computed['score'];
        $row['digest_tags'] = $computed['tags'];
        $row['digest_breakdown'] = $computed['breakdown'];
        $row['digest_last_activity'] = $computed['last_activity'];
    }
    unset($row);
}

function digest_sort_rows(array &$rows) {
    usort($rows, function ($a, $b) {
        $sa = (int)($a['digest_score'] ?? 0);
        $sb = (int)($b['digest_score'] ?? 0);
        if ($sa !== $sb) {
            return $sb <=> $sa;
        }
        $ta = strtotime($a['digest_last_activity'] ?? $a['datumPozadavek'] ?? '1970-01-01');
        $tb = strtotime($b['digest_last_activity'] ?? $b['datumPozadavek'] ?? '1970-01-01');
        if ($ta !== $tb) {
            return $tb <=> $ta;
        }
        return strcmp($a['datumPozadavek'] ?? '', $b['datumPozadavek'] ?? '');
    });
}

/**
 * @param array<int, array> $rows
 * @return array{main:array, f3:array}
 */
function digest_split_f3_rows(array $rows) {
    $main = [];
    $f3 = [];
    foreach ($rows as $row) {
        if (!empty($row['digest_f3_only'])) {
            $f3[] = $row;
        } else {
            $main[] = $row;
        }
    }
    return ['main' => $main, 'f3' => $f3];
}

function digest_format_last_activity($datetime) {
    if ($datetime === null || $datetime === '') {
        return '<span style="color:#aaa;">—</span>';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 3600) {
        return 'právě teď';
    }
    if ($diff < 86400 && date('Y-m-d', $ts) === date('Y-m-d')) {
        return 'dnes ' . date('G:i', $ts);
    }
    if ($diff < 172800 && date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) {
        return 'včera ' . date('G:i', $ts);
    }
    $dni = (int)floor($diff / 86400);
    if ($dni <= 7) {
        return 'před ' . $dni . ' d';
    }
    return date('j.n. G:i', $ts);
}

function digest_load_nakup_faze1($conn) {
    $users_tbl = digest_users_table();
    $produkty_extra = digest_produkty_sql_extra($conn);
    $sql = "SELECT p.*, s.nazev AS surovina_nazev, u_nak.jmeno AS nakupci_jmeno,
            (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ')
             FROM pozadavky_zakaznici pz
             JOIN zakaznici z ON pz.id_zakaznik = z.id
             WHERE pz.id_pozadavek = p.id) AS zakaznici_seznam,
            $produkty_extra
            GROUP_CONCAT(IFNULL(pn.id_status, '0') SEPARATOR '|') AS nabidky_statusy,
            COUNT(CASE WHEN pn.id IS NOT NULL AND pn.id_status NOT IN (5,7) THEN 1 END) AS pocet_aktivnich,
            COUNT(pn.id) AS pocet_nabidek
            FROM pozadavky p
            LEFT JOIN suroviny s ON p.id_surovina = s.id
            LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
            LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
            WHERE p.id_status NOT IN (5, 6, 7, 8)
            GROUP BY p.id
            ORDER BY p.datumPozadavek ASC";

    $result = mysqli_query($conn, $sql);
    $ping_ids = digest_ping_ids($conn);
    $rows = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $faze1 = true;
            if (!empty($row['nabidky_statusy'])) {
                foreach (explode('|', $row['nabidky_statusy']) as $s_id) {
                    if ($s_id !== '0' && !in_array((int)$s_id, [5, 7])) {
                        $faze1 = false;
                        break;
                    }
                }
            }
            if (!$faze1) continue;

            $row['vyzadano'] = !empty($ping_ids[(int)$row['id']]);
            $row['pocet_nabidek'] = (int)$row['pocet_nabidek'];
            $rows[] = $row;
        }
    }

    return $rows;
}

function digest_vyvoj_stav_label($status_id) {
    $st = (int)$status_id;
    if ($st === 2) return 'Schválit cenu (CENA OK / KO)';
    if ($st === 13) return 'Nutriční hodnocení';
    if (in_array($st, [10, 4], true)) return 'Test vzorku (OK / KO)';
    return 'K řešení';
}

function digest_kvalita_stav_label() {
    return 'Schválit dokumentaci (KVALITA OK / Doplnit / KO)';
}

function digest_load_offer_tasks($conn, $channel) {
    $users_tbl = digest_users_table();
    $produkty_extra = digest_produkty_sql_extra($conn);
    $snooze_sql = digest_snooze_sql_select($conn);
    $sql = "SELECT p.id, p.priorita, p.datumPozadavek, p.bio, p.vegan, p.bezlepek, p.kosher, p.halal,
            $snooze_sql
            s.nazev AS surovina_nazev, u_nak.jmeno AS nakupci_jmeno,
            pn.id AS id_nabidka, pn.id_status AS nabidka_status, pn.seznam_souboru,
            d.nazev AS dodavatel_nazev,
            (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ')
             FROM pozadavky_zakaznici pz
             JOIN zakaznici z ON pz.id_zakaznik = z.id
             WHERE pz.id_pozadavek = p.id) AS zakaznici_seznam,
            $produkty_extra
            FROM pozadavky p
            INNER JOIN suroviny s ON p.id_surovina = s.id
            INNER JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
            LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
            LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
            WHERE p.id_status NOT IN (5, 6, 7, 8)
              AND pn.id_status NOT IN (5, 7)
            ORDER BY p.priorita DESC, p.datumPozadavek ASC";

    $result = mysqli_query($conn, $sql);
    $rows = [];
    if (!$result) {
        return $rows;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $st = (int)$row['nabidka_status'];
        $has_spec = digest_offer_has_spec($row['seznam_souboru'] ?? '');

        if ($channel === 'vyvoj' && !in_array($st, [2, 13, 10, 4], true)) {
            continue;
        }
        if ($channel === 'kvalita' && !($st === 12 && $has_spec)) {
            continue;
        }

        $dod = trim($row['dodavatel_nazev'] ?? '');
        $row['surovina_nazev'] = $row['surovina_nazev']
            . ($dod !== '' ? ' · ' . $dod : '')
            . ' (nab. #' . (int)$row['id_nabidka'] . ')';
        $row['stav_text'] = $channel === 'kvalita'
            ? digest_kvalita_stav_label()
            : digest_vyvoj_stav_label($st);
        $rows[] = $row;
    }

    return $rows;
}

function digest_load_portfolio_orphans($conn) {
    if (!function_exists('pf_has_table')) {
        @include_once(__DIR__ . '/portfolio_helpers.php');
    }
    if (!function_exists('pf_has_table') || !pf_has_table($conn, 'pozadavky_produkty')) {
        return [];
    }

    $users_tbl = digest_users_table();
    $snooze_sql = digest_snooze_sql_select($conn);
    $sql = "SELECT p.id, p.priorita, p.datumPozadavek, s.nazev AS surovina_nazev,
            $snooze_sql
            u_nak.jmeno AS nakupci_jmeno, cs.nazev AS status_nazev,
            (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ')
             FROM pozadavky_zakaznici pz
             JOIN zakaznici z ON pz.id_zakaznik = z.id
             WHERE pz.id_pozadavek = p.id) AS zakaznici_seznam
            FROM pozadavky p
            INNER JOIN suroviny s ON s.id = p.id_surovina
            LEFT JOIN ciselnik_statusu cs ON cs.id = p.id_status
            LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
            WHERE p.id_status NOT IN (5, 6, 7, 8)
              AND NOT EXISTS (
                SELECT 1 FROM pozadavky_produkty pp
                INNER JOIN produkty pr ON pr.id = pp.id_produkt AND pr.ukonceny = 0
                WHERE pp.id_pozadavek = p.id
              )
            ORDER BY p.priorita DESC, p.datumPozadavek ASC
            LIMIT 200";

    $rows = [];
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $r['stav_text'] = !empty($r['status_nazev'])
                ? 'Fáze: ' . $r['status_nazev'] . ' · bez produktu'
                : 'Bez propojení na produkt';
            $rows[] = $r;
        }
    }
    return $rows;
}

function digest_channel_meta() {
    return [
        'nakup' => [
            'label' => 'Nákup',
            'title' => 'Přehled pro Nákup',
            'subtitle' => date('j.n.Y') . ' · Požadavky ve Fázi 1 (čeká se na vaši nabídku)',
            'email_channel' => 'nakup',
            'subject_role' => 'čeká Nákup',
        ],
        'vyvoj' => [
            'label' => 'Vývoj',
            'title' => 'Přehled pro Vývoj',
            'subtitle' => date('j.n.Y') . ' · Nabídky čekající na vaši akci',
            'email_channel' => 'vyvoj',
            'subject_role' => 'čeká Vývoj',
        ],
        'kvalita' => [
            'label' => 'Kvalita',
            'title' => 'Přehled pro Kvalitu',
            'subtitle' => date('j.n.Y') . ' · Dokumentace ke schválení',
            'email_channel' => 'kvalita',
            'subject_role' => 'čeká Kvalita',
        ],
        'portfolio' => [
            'label' => 'Portfolio',
            'title' => 'Přehled Portfolio',
            'subtitle' => date('j.n.Y') . ' · Sirotčinec (požadavky bez aktivního produktu)',
            'email_channel' => null,
            'subject_role' => 'Portfolio',
        ],
    ];
}

function digest_channels_for_user(array $perms) {
    $channels = [];
    // Nákupní souhrn jen pro roli orders (ne rozšířené Nák+ — to je jen přístup k modulu Nákup).
    if (!empty($perms['is_adm']) || !empty($perms['is_orders'])) {
        $channels[] = 'nakup';
    }
    if (!empty($perms['is_adm']) || !empty($perms['is_vyvoj'])) {
        $channels[] = 'vyvoj';
    }
    if (!empty($perms['is_adm']) || !empty($perms['is_kvalita'])) {
        $channels[] = 'kvalita';
    }
    if (!empty($perms['is_adm']) || !empty($perms['can_portfolio'])) {
        $channels[] = 'portfolio';
    }
    return $channels;
}

/**
 * @return array{channel:string,meta:array,sections:array,total:int,subject:string}
 */
function digest_build($conn, $channel) {
    $meta_all = digest_channel_meta();
    if (!isset($meta_all[$channel])) {
        return ['channel' => $channel, 'meta' => [], 'sections' => [], 'total' => 0, 'subject' => ''];
    }
    $meta = $meta_all[$channel];
    $sections = [];

    if ($channel === 'nakup') {
        $rows = digest_load_nakup_faze1($conn);
        digest_enrich_rows($conn, $rows, $channel);
        digest_sort_rows($rows);
        $sections = [
            ['title' => 'K řešení', 'color' => '#337ab7', 'rows' => $rows],
        ];
    } elseif ($channel === 'vyvoj' || $channel === 'kvalita') {
        $rows = digest_load_offer_tasks($conn, $channel);
        digest_enrich_rows($conn, $rows, $channel);
        if ($channel === 'vyvoj') {
            $split = digest_split_f3_rows($rows);
            digest_sort_rows($split['main']);
            digest_sort_rows($split['f3']);
            $sections = [
                ['title' => 'K řešení', 'color' => '#337ab7', 'rows' => $split['main']],
            ];
            if (!empty($split['f3'])) {
                $sections[] = ['title' => 'Ve testování (F3)', 'color' => '#2980b9', 'rows' => $split['f3']];
            }
        } else {
            digest_sort_rows($rows);
            $sections = [
                ['title' => 'K řešení', 'color' => '#337ab7', 'rows' => $rows],
            ];
        }
    } elseif ($channel === 'portfolio') {
        $rows = digest_load_portfolio_orphans($conn);
        digest_enrich_rows($conn, $rows, $channel);
        digest_sort_rows($rows);
        $sections = [
            ['title' => 'Sirotci bez produktu', 'color' => '#8e44ad', 'rows' => $rows],
        ];
    }

    $total = 0;
    foreach ($sections as $sec) {
        $total += count($sec['rows']);
    }

    $subject = digest_subject_line($sections, $meta['subject_role'] ?? $meta['label']);

    return [
        'channel' => $channel,
        'meta' => $meta,
        'sections' => $sections,
        'total' => $total,
        'subject' => $subject,
    ];
}

function digest_subject_line(array $sections, $role_label) {
    $total = 0;
    $top_score = 0;
    foreach ($sections as $sec) {
        $total += count($sec['rows']);
        foreach ($sec['rows'] as $row) {
            $top_score = max($top_score, (int)($row['digest_score'] ?? 0));
        }
    }

    $subject = 'Vzorkovna: ' . $total . ' položek';
    if ($top_score >= 100) {
        $subject .= ' (max priorita ' . $top_score . ')';
    }
    $subject .= ' — ' . $role_label;
    return $subject;
}

function digest_count_for_user($conn, array $perms) {
    $sum = 0;
    foreach (digest_channels_for_user($perms) as $ch) {
        $sum += digest_build($conn, $ch)['total'];
    }
    return $sum;
}
