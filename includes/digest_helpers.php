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

function digest_split_priority($rows) {
    $urgentni = [];
    $standardni = [];
    foreach ($rows as $row) {
        if (!empty($row['priorita'])) {
            $urgentni[] = $row;
        } else {
            $standardni[] = $row;
        }
    }
    return [$urgentni, $standardni];
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
    $sql = "SELECT p.id, p.priorita, p.datumPozadavek, p.bio, p.vegan, p.bezlepek, p.kosher, p.halal,
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
    $sql = "SELECT p.id, p.priorita, p.datumPozadavek, s.nazev AS surovina_nazev,
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
    if (!empty($perms['is_adm']) || !empty($perms['can_nakup'])) {
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
        list($urgentni, $standardni) = digest_split_priority($rows);
        $sections = [
            ['title' => 'Urgentní', 'color' => '#d9534f', 'rows' => $urgentni],
            ['title' => 'Standardní', 'color' => '#337ab7', 'rows' => $standardni],
        ];
    } elseif ($channel === 'vyvoj' || $channel === 'kvalita') {
        $rows = digest_load_offer_tasks($conn, $channel);
        list($urgentni, $standardni) = digest_split_priority($rows);
        $sections = [
            ['title' => 'Urgentní', 'color' => '#d9534f', 'rows' => $urgentni],
            ['title' => 'Standardní', 'color' => '#337ab7', 'rows' => $standardni],
        ];
    } elseif ($channel === 'portfolio') {
        $rows = digest_load_portfolio_orphans($conn);
        list($urgentni, $standardni) = digest_split_priority($rows);
        $sections = [
            ['title' => 'Urgentní sirotci', 'color' => '#d9534f', 'rows' => $urgentni],
            ['title' => 'Ostatní sirotci', 'color' => '#8e44ad', 'rows' => $standardni],
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
    $urgentni = 0;
    $standardni = 0;
    foreach ($sections as $sec) {
        if (stripos($sec['title'], 'urgent') !== false) {
            $urgentni += count($sec['rows']);
        } else {
            $standardni += count($sec['rows']);
        }
    }

    $subject = 'Vzorkovna: ';
    if ($urgentni > 0) {
        $subject .= $urgentni . ' urgentní';
        if ($standardni > 0) $subject .= ', ';
    }
    if ($standardni > 0) {
        $subject .= $standardni . ' standardní';
    }
    if ($urgentni === 0 && $standardni === 0) {
        $subject .= '0 položek';
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
