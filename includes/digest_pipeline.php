<?php
/**
 * Souhrn v2 — pipeline pásy A/B/C a dávky podle oddělení.
 * Vyžaduje boardFunctions.php (STATUS_NABIDKA_BEZ_CENY).
 */

function digest_offer_has_lab($files_str) {
    foreach (explode('^', (string)($files_str ?? '')) as $f) {
        if ($f === '') continue;
        $pts = explode('~', $f);
        if (($pts[1] ?? '') === 'lab') return true;
    }
    return false;
}

/**
 * @return array{actor:string, actor_label:string, dept_actions:array, dept_next:array, kontext_short:string}
 */
function digest_offer_workflow($st, $has_spec, $has_lab) {
    $st = (int)$st;
    $bc = defined('STATUS_NABIDKA_BEZ_CENY') ? STATUS_NABIDKA_BEZ_CENY : 14;

    $actor = 'none';
    $actor_label = '';
    $dept_actions = ['nakup' => null, 'vyvoj' => null, 'kvalita' => null];
    $dept_next = ['nakup' => null, 'vyvoj' => null, 'kvalita' => null];
    $kontext_short = '';

    switch ($st) {
        case 2:
            $actor = 'vyvoj';
            $actor_label = 'Vývoj';
            $dept_actions['vyvoj'] = 'Schválit cenu a množství (CENA OK)';
            $dept_next['nakup'] = 'Nahrát TDS a předat kvalitě';
            $kontext_short = 'čeká Vývoj · CENA OK';
            break;
        case 3:
            $actor = 'nakup';
            $actor_label = 'Nákup';
            if (!$has_spec) {
                $dept_actions['nakup'] = 'Nahrát TDS';
            } else {
                $dept_actions['nakup'] = 'Předat kvalitě';
            }
            $dept_next['kvalita'] = 'Schválit TDS';
            $kontext_short = 'čeká Nákup · TDS / předání';
            break;
        case $bc:
            $actor = 'nakup';
            $actor_label = 'Nákup';
            $dept_actions['nakup'] = $has_spec
                ? 'Doplnit cenu (pak schválí Vývoj)'
                : 'Nahrát TDS (dokumentace bez ceny)';
            $dept_next['vyvoj'] = 'Schválit cenu (CENA OK)';
            $kontext_short = 'čeká Nákup · dokumentace';
            break;
        case 12:
            $actor = 'kvalita';
            $actor_label = 'Kvalita';
            $dept_actions['kvalita'] = 'Schválit TDS (KVALITA OK)';
            $dept_next['vyvoj'] = 'Kontrola nutričních hodnot';
            $kontext_short = 'čeká Kvalita · TDS';
            break;
        case 13:
            $actor = 'vyvoj';
            $actor_label = 'Vývoj';
            $dept_actions['vyvoj'] = 'Schválit nutriční hodnoty (NUTRI OK)';
            $dept_next['nakup'] = 'Objednat vzorek';
            $kontext_short = 'čeká Vývoj · nutri';
            break;
        case 8:
            $actor = 'nakup';
            $actor_label = 'Nákup';
            $dept_actions['nakup'] = 'Potvrdit objednávku vzorku (OBJEDNÁNO)';
            $dept_next['nakup'] = 'Potvrdit Dorazilo';
            $kontext_short = 'čeká Nákup · objednávka';
            break;
        case 11:
            $actor = 'nakup';
            $actor_label = 'Nákup';
            $dept_actions['nakup'] = 'Potvrdit Dorazilo vzorku';
            $dept_next['vyvoj'] = 'Technologický test';
            if (!$has_lab) {
                $dept_next['nakup'] = 'Nahrát COA';
            }
            $kontext_short = 'čeká Nákup · Dorazilo';
            break;
        case 10:
        case 4:
            $actor = 'vyvoj';
            $actor_label = 'Vývoj';
            $dept_actions['vyvoj'] = 'Technologický test (TEST OK / KO)';
            if (!$has_lab) {
                $dept_actions['nakup'] = 'Nahrát COA (analýzu)';
            }
            $kontext_short = 'čeká Vývoj · lab';
            break;
        case 9:
            $actor = 'nakup';
            $actor_label = 'Nákup';
            $dept_actions['nakup'] = 'Doplnit dokumentaci';
            $dept_next['kvalita'] = 'Znovu schválit TDS';
            $kontext_short = 'čeká Nákup · doplnit docs';
            break;
        default:
            $kontext_short = 'stav #' . $st;
    }

    return compact('actor', 'actor_label', 'dept_actions', 'dept_next', 'kontext_short');
}

/** @return 'A'|'B'|'C'|null */
function digest_offer_band_for_channel($channel, array $wf) {
    if (!empty($wf['dept_actions'][$channel])) {
        return 'A';
    }
    if ($wf['actor'] !== 'none' && $wf['actor'] !== $channel) {
        return 'B';
    }
    if (!empty($wf['dept_next'][$channel])) {
        return 'C';
    }
    return null;
}

function digest_band_stav_text($channel, $band, array $wf) {
    if ($band === 'A') {
        return $wf['dept_actions'][$channel];
    }
    if ($band === 'B') {
        $act = $wf['dept_actions'][$wf['actor']] ?? 'řeší';
        return 'Čeká ' . $wf['actor_label'] . ': ' . $act;
    }
    if ($band === 'C') {
        return 'U vás potom: ' . $wf['dept_next'][$channel];
    }
    return '';
}

function digest_cert_badges_html(array $row) {
    $parts = [];
    if (!empty($row['priorita'])) $parts[] = '<span class="label label-danger" style="font-size:9px;">URG</span>';
    if (!empty($row['bio'])) $parts[] = '<span class="label label-success" style="font-size:9px;">BIO</span>';
    if (!empty($row['vegan'])) $parts[] = '<span class="label label-success" style="font-size:9px;">VEG</span>';
    if (!empty($row['bezlepek'])) $parts[] = '<span class="label label-warning" style="font-size:9px;">BL</span>';
    if (!empty($row['kosher'])) $parts[] = '<span class="label label-default" style="font-size:9px;">K</span>';
    if (!empty($row['halal'])) $parts[] = '<span class="label label-default" style="font-size:9px;">H</span>';
    return $parts ? ' ' . implode(' ', $parts) : '';
}

/**
 * Načte otevřené požadavky + aktivní nabídky pro pipeline.
 *
 * @return array<int, array{request:array, offers:array<int,array>}>
 */
function digest_load_open_requests_with_offers($conn) {
    $users_tbl = function_exists('digest_users_table') ? digest_users_table() : 'users';
    $produkty_extra = digest_produkty_sql_extra($conn);
    $snooze_sql = digest_snooze_sql_select($conn);
    $delegace_sql = function_exists('wf_delegace_sql_select') ? wf_delegace_sql_select($conn) : '';

    $sql = "SELECT p.id, p.priorita, p.datumPozadavek, p.bio, p.vegan,
            p.bezlepek, p.kosher, p.halal, p.id_status AS req_status,
            $snooze_sql
            s.nazev AS surovina_nazev, u_nak.jmeno AS nakupci_jmeno,
            pn.id AS id_nabidka, pn.id_status AS nabidka_status, pn.seznam_souboru,
            $delegace_sql
            d.nazev AS dodavatel_nazev,
            (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ')
             FROM pozadavky_zakaznici pz
             JOIN zakaznici z ON pz.id_zakaznik = z.id
             WHERE pz.id_pozadavek = p.id) AS zakaznici_seznam,
            $produkty_extra
            (SELECT COUNT(*) FROM pozadavky_nabidky px
             WHERE px.id_pozadavek = p.id AND px.id_status NOT IN (5, 7, 15)) AS pocet_aktivnich
            FROM pozadavky p
            INNER JOIN suroviny s ON p.id_surovina = s.id
            LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id AND pn.id_status NOT IN (5, 7, 15)
            LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
            LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
            WHERE p.id_status NOT IN (5, 6, 7, 8)
            ORDER BY p.priorita DESC, p.datumPozadavek ASC, pn.id ASC";

    $res = mysqli_query($conn, $sql);
    $map = [];
    if (!$res) {
        return $map;
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $rid = (int)$row['id'];
        if (!isset($map[$rid])) {
            $map[$rid] = [
                'request' => [
                    'id' => $rid,
                    'priorita' => (int)$row['priorita'],
                    'datumPozadavek' => $row['datumPozadavek'],
                    'bio' => (int)$row['bio'],
                    'vegan' => (int)$row['vegan'],
                    'bezlepek' => (int)$row['bezlepek'],
                    'kosher' => (int)$row['kosher'],
                    'halal' => (int)$row['halal'],
                    'surovina_nazev' => $row['surovina_nazev'],
                    'nakupci_jmeno' => $row['nakupci_jmeno'],
                    'zakaznici_seznam' => $row['zakaznici_seznam'],
                    'produkty_seznam' => $row['produkty_seznam'] ?? null,
                    'souhrn_snooze_do' => $row['souhrn_snooze_do'] ?? null,
                    'souhrn_snooze_poznamka' => $row['souhrn_snooze_poznamka'] ?? null,
                    'pocet_aktivnich' => (int)$row['pocet_aktivnich'],
                ],
                'offers' => [],
            ];
        }
        if (!empty($row['id_nabidka'])) {
            $st = (int)$row['nabidka_status'];
            $has_spec = digest_offer_has_spec($row['seznam_souboru'] ?? '');
            $has_lab = digest_offer_has_lab($row['seznam_souboru'] ?? '');
            $wf = digest_offer_workflow($st, $has_spec, $has_lab);
            $delegace_row = [
                'wf_delegace_komu' => $row['wf_delegace_komu'] ?? null,
                'wf_delegace_od' => $row['wf_delegace_od'] ?? null,
                'wf_delegace_duvod' => $row['wf_delegace_duvod'] ?? null,
            ];
            if (function_exists('wf_delegace_apply_workflow')) {
                $wf = wf_delegace_apply_workflow($wf, $delegace_row);
            }
            $map[$rid]['offers'][(int)$row['id_nabidka']] = [
                'id_nabidka' => (int)$row['id_nabidka'],
                'nabidka_status' => $st,
                'dodavatel_nazev' => trim($row['dodavatel_nazev'] ?? '') ?: '—',
                'has_spec' => $has_spec,
                'has_lab' => $has_lab,
                'workflow' => $wf,
                'wf_delegace_komu' => $delegace_row['wf_delegace_komu'],
                'wf_delegace_od' => $delegace_row['wf_delegace_od'],
                'wf_delegace_duvod' => $delegace_row['wf_delegace_duvod'],
            ];
        }
    }

    return $map;
}

function digest_load_f1_no_offer_requests($conn, array $ping_ids) {
    $users_tbl = function_exists('digest_users_table') ? digest_users_table() : 'users';
    $produkty_extra = digest_produkty_sql_extra($conn);
    $snooze_sql = digest_snooze_sql_select($conn);

    $sql = "SELECT p.id, p.priorita, p.datumPozadavek, p.bio, p.vegan, p.bezlepek, p.kosher, p.halal,
            $snooze_sql
            s.nazev AS surovina_nazev, u_nak.jmeno AS nakupci_jmeno,
            (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ')
             FROM pozadavky_zakaznici pz
             JOIN zakaznici z ON pz.id_zakaznik = z.id
             WHERE pz.id_pozadavek = p.id) AS zakaznici_seznam,
            $produkty_extra
            (SELECT COUNT(*) FROM pozadavky_nabidky px WHERE px.id_pozadavek = p.id) AS pocet_nabidek
            FROM pozadavky p
            INNER JOIN suroviny s ON p.id_surovina = s.id
            LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
            WHERE p.id_status NOT IN (5, 6, 7, 8)
              AND NOT EXISTS (
                SELECT 1 FROM pozadavky_nabidky pn
                WHERE pn.id_pozadavek = p.id AND pn.id_status NOT IN (5, 7, 15)
              )
            ORDER BY p.priorita DESC, p.datumPozadavek ASC";

    $rows = [];
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $row['vyzadano'] = !empty($ping_ids[(int)$row['id']]);
            $row['pocet_aktivnich'] = 0;
            $rows[] = $row;
        }
    }
    return $rows;
}

function digest_build_kontext_line(array $offers, $primary_nabidka_id) {
    $parts = [];
    foreach ($offers as $off) {
        if ((int)$off['id_nabidka'] === (int)$primary_nabidka_id) {
            continue;
        }
        $parts[] = $off['dodavatel_nazev'] . ' #' . $off['id_nabidka'] . ' · ' . ($off['workflow']['kontext_short'] ?? '');
    }
    if (empty($parts)) {
        return '';
    }
    return '🔥 ' . implode(' · ', $parts);
}

/**
 * Seskupí nabídky do řádků požadavků pro daný pás.
 *
 * @return array<int, array>
 */
function digest_aggregate_band_items($channel, $band, array $items, array $req_map) {
    $by_req = [];
    foreach ($items as $it) {
        $rid = (int)$it['id'];
        if (!isset($by_req[$rid]) || (int)($it['digest_score'] ?? 0) > (int)($by_req[$rid]['digest_score'] ?? 0)) {
            $by_req[$rid] = $it;
        }
    }

    $rows = [];
    foreach ($by_req as $rid => $row) {
        $offers = $req_map[$rid]['offers'] ?? [];
        $primary_nid = (int)($row['id_nabidka'] ?? 0);
        $kontext = digest_build_kontext_line(array_values($offers), $primary_nid);
        if ($kontext !== '') {
            $row['digest_kontext'] = $kontext;
        }
        if ($primary_nid > 0 && !empty($offers[$primary_nid])) {
            $row['digest_primary_dod'] = $offers[$primary_nid]['dodavatel_nazev'];
            $row['digest_primary_nabidka'] = $primary_nid;
        }
        $rows[] = $row;
    }
    return $rows;
}

/**
 * @return array{bands:array, batches:array}
 */
function digest_build_pipeline($conn, $channel) {
    $ping_ids = digest_ping_ids($conn);
    $req_map = digest_load_open_requests_with_offers($conn);

    $bands_raw = ['A' => [], 'B' => [], 'C' => []];
    $batch_tds = [];
    $batch_coa = [];

    foreach ($req_map as $rid => $pack) {
        $req = $pack['request'];
        $base = array_merge($req, [
            'id' => $rid,
            'id_nabidka' => 0,
        ]);

        if ($channel === 'nakup' && empty($pack['offers'])) {
            continue;
        }

        foreach ($pack['offers'] as $nid => $off) {
            $wf = $off['workflow'];
            $band = digest_offer_band_for_channel($channel, $wf);
            if ($band === null) {
                continue;
            }

            $row = array_merge($base, [
                'id_nabidka' => $nid,
                'nabidka_status' => $off['nabidka_status'],
                'stav_text' => digest_band_stav_text($channel, $band, $wf),
                'digest_band' => $band,
                'wf_delegace_komu' => $off['wf_delegace_komu'] ?? null,
                'wf_delegace_od' => $off['wf_delegace_od'] ?? null,
                'wf_delegace_duvod' => $off['wf_delegace_duvod'] ?? null,
            ]);

            $bands_raw[$band][] = $row;

            if ($channel === 'nakup') {
                $st = $off['nabidka_status'];
                if (in_array($st, [3, 14, 9], true) && !$off['has_spec']) {
                    $batch_tds[] = array_merge($row, [
                        'stav_text' => 'Nahrát TDS · ' . $off['dodavatel_nazev'] . ' #' . $nid,
                    ]);
                }
                if (in_array($st, [10, 4], true) && !$off['has_lab']) {
                    $batch_coa[] = array_merge($row, [
                        'stav_text' => 'Nahrát COA · ' . $off['dodavatel_nazev'] . ' #' . $nid,
                    ]);
                }
            }
        }
    }

    if ($channel === 'nakup') {
        foreach (digest_load_f1_no_offer_requests($conn, $ping_ids) as $req) {
            if (!empty($req['vyzadano'])) {
                $req['stav_text'] = 'Dohledat další nabídku (ping od vývoje/kvality)';
            } else {
                $pocet = (int)($req['pocet_nabidek'] ?? 0);
                $req['stav_text'] = $pocet > 0
                    ? 'Vložit novou nabídku (předchozí KO)'
                    : (empty($req['nakupci_jmeno'])
                        ? 'Převzít požadavek a vložit nabídku'
                        : 'Vložit první nabídku dodavatele');
            }
            $req['digest_band'] = 'A';
            $bands_raw['A'][] = $req;
        }
    }

    foreach (['A', 'B', 'C'] as $b) {
        digest_enrich_rows($conn, $bands_raw[$b], $channel);
        digest_sort_rows($bands_raw[$b]);
        $bands_raw[$b] = digest_aggregate_band_items($channel, $b, $bands_raw[$b], $req_map);
    }

    foreach ([&$batch_tds, &$batch_coa] as &$batch) {
        if (!empty($batch)) {
            digest_enrich_rows($conn, $batch, $channel);
            digest_sort_rows($batch);
        }
    }
    unset($batch);

    $batches = [];
    if ($channel === 'nakup') {
        if (!empty($batch_tds)) {
            $batches[] = ['title' => 'Chybí TDS', 'color' => '#e67e22', 'rows' => $batch_tds, 'dimmed' => true];
        }
        if (!empty($batch_coa)) {
            $batches[] = ['title' => 'Chybí COA (lab analýza)', 'color' => '#c0392b', 'rows' => $batch_coa, 'dimmed' => true];
        }
    }

    if ($channel === 'vyvoj' || $channel === 'portfolio') {
        $orphans = digest_load_portfolio_orphans($conn);
        if (!empty($orphans)) {
            digest_enrich_rows($conn, $orphans, $channel === 'portfolio' ? 'portfolio' : 'vyvoj');
            digest_sort_rows($orphans);
            $batches[] = [
                'title' => 'Sirotci — propojit k produktu',
                'color' => '#8e44ad',
                'rows' => $orphans,
                'dimmed' => true,
            ];
        }
    }

    if ($channel === 'vyvoj') {
        $snooze_rows = [];
        foreach ($req_map as $rid => $pack) {
            $req = $pack['request'];
            if (!function_exists('digest_is_snooze_active') || !digest_is_snooze_active($req)) {
                continue;
            }
            $row = array_merge($req, [
                'id' => $rid,
                'stav_text' => 'Dlouhé dodání — snížená priorita v Souhrnu',
                'ceka_detail' => digest_ceka_detail('nakup', $req),
            ]);
            $snooze_rows[] = $row;
        }
        if (!empty($snooze_rows)) {
            digest_enrich_rows($conn, $snooze_rows, 'vyvoj');
            digest_sort_rows($snooze_rows);
            $batches[] = [
                'title' => 'Dlouhé dodání (informativně)',
                'color' => '#95a5a6',
                'rows' => $snooze_rows,
                'dimmed' => true,
            ];
        }
    }

    return ['bands' => $bands_raw, 'batches' => $batches];
}
