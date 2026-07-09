<?php
/**
 * Sdílené funkce pro vazbu požadavků na vývojové produkty (Portfolio F2+).
 */

/** @return bool */
function pf_has_table($conn, $table) {
    $t = mysqli_real_escape_string($conn, $table);
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    return $res && mysqli_num_rows($res) > 0;
}

/** @return bool */
function pf_has_column($conn, $table, $column) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = @mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * Přidá surovinu do seznamu „co potřebujeme“ u vývojových produktů (Portfolio sloupec Suroviny).
 *
 * @param mysqli $conn
 * @param int $id_surovina
 * @param array $produkty_ids
 */
function pf_ensure_suroviny_on_produkty($conn, $id_surovina, $produkty_ids) {
    if (!pf_has_table($conn, 'produkty_suroviny')) {
        return;
    }

    $id_surovina = (int)$id_surovina;
    if ($id_surovina <= 0 || !is_array($produkty_ids)) {
        return;
    }

    foreach ($produkty_ids as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            mysqli_query($conn, "INSERT IGNORE INTO produkty_suroviny (id_produkt, id_surovina) VALUES ($pid, $id_surovina)");
        }
    }
}

/**
 * @return int
 */
function pf_get_request_surovina_id($conn, $id_pozadavek) {
    $id_pozadavek = (int)$id_pozadavek;
    if ($id_pozadavek <= 0) {
        return 0;
    }
    $res = mysqli_query($conn, "SELECT id_surovina FROM pozadavky WHERE id = $id_pozadavek LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int)$row['id_surovina'];
    }
    return 0;
}

/**
 * @param mysqli $conn
 * @param int $id_pozadavek
 * @param array $produkty_ids ID aktivních produktů z formuláře
 * @param int $id_surovina 0 = načíst z požadavku
 */
function pf_sync_request_produkty($conn, $id_pozadavek, $produkty_ids, $id_surovina = 0) {
    if (!pf_has_table($conn, 'pozadavky_produkty')) {
        return;
    }

    $id_pozadavek = (int)$id_pozadavek;
    if ($id_pozadavek <= 0) {
        return;
    }

    $preserve = [];
    $res = mysqli_query($conn, "SELECT pp.id_produkt FROM pozadavky_produkty pp
        INNER JOIN produkty pr ON pr.id = pp.id_produkt AND pr.ukonceny = 1
        WHERE pp.id_pozadavek = $id_pozadavek");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $preserve[] = (int)$row['id_produkt'];
        }
    }

    mysqli_query($conn, "DELETE FROM pozadavky_produkty WHERE id_pozadavek = $id_pozadavek");

    $to_insert = [];
    foreach ($produkty_ids as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            $to_insert[$pid] = true;
        }
    }
    foreach ($preserve as $pid) {
        $to_insert[$pid] = true;
    }

    foreach (array_keys($to_insert) as $pid) {
        mysqli_query($conn, "INSERT IGNORE INTO pozadavky_produkty (id_pozadavek, id_produkt) VALUES ($id_pozadavek, $pid)");
    }

    if ($id_surovina <= 0) {
        $id_surovina = pf_get_request_surovina_id($conn, $id_pozadavek);
    }
    pf_ensure_suroviny_on_produkty($conn, $id_surovina, $produkty_ids);
}

/**
 * Připojí produkty k existujícímu požadavku (např. při duplicitě).
 *
 * @param mysqli $conn
 * @param int $id_pozadavek
 * @param array $produkty_ids
 * @param int $id_surovina 0 = načíst z požadavku
 */
function pf_append_request_produkty($conn, $id_pozadavek, $produkty_ids, $id_surovina = 0) {
    if (!pf_has_table($conn, 'pozadavky_produkty')) {
        return;
    }

    $id_pozadavek = (int)$id_pozadavek;
    if ($id_pozadavek <= 0) {
        return;
    }

    foreach ($produkty_ids as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
            mysqli_query($conn, "INSERT IGNORE INTO pozadavky_produkty (id_pozadavek, id_produkt) VALUES ($id_pozadavek, $pid)");
        }
    }

    if ($id_surovina <= 0) {
        $id_surovina = pf_get_request_surovina_id($conn, $id_pozadavek);
    }
    pf_ensure_suroviny_on_produkty($conn, $id_surovina, $produkty_ids);
}

/**
 * @param mysqli $conn
 * @param int $id_pozadavek
 * @param array $zakaznici
 */
function pf_append_request_zakaznici($conn, $id_pozadavek, $zakaznici) {
    $id_pozadavek = (int)$id_pozadavek;
    if ($id_pozadavek <= 0 || !is_array($zakaznici)) {
        return;
    }

    foreach ($zakaznici as $zak_raw) {
        if (trim((string)$zak_raw) === '') {
            continue;
        }

        if (!is_numeric($zak_raw)) {
            $z_name = mysqli_real_escape_string($conn, $zak_raw);
            mysqli_query($conn, "INSERT INTO zakaznici (nazev) VALUES ('$z_name')");
            $id_zakaznik = mysqli_insert_id($conn);
        } else {
            $id_zakaznik = (int)$zak_raw;
        }

        if ($id_zakaznik > 0) {
            mysqli_query($conn, "INSERT IGNORE INTO pozadavky_zakaznici (id_pozadavek, id_zakaznik) VALUES ($id_pozadavek, $id_zakaznik)");
        }
    }
}

/**
 * @param mysqli $conn
 * @param int $id_pozadavek
 * @param array $zakaznici
 */
function pf_save_request_zakaznici($conn, $id_pozadavek, $zakaznici) {
    $id_pozadavek = (int)$id_pozadavek;
    if ($id_pozadavek <= 0 || !is_array($zakaznici)) {
        return;
    }

    mysqli_query($conn, "DELETE FROM pozadavky_zakaznici WHERE id_pozadavek = $id_pozadavek");
    pf_append_request_zakaznici($conn, $id_pozadavek, $zakaznici);
}

/**
 * @return array<int, string>
 */
function pf_parse_post_ids($raw) {
    if (!isset($raw)) {
        return [];
    }
    if (!is_array($raw)) {
        $raw = [$raw];
    }
    $out = [];
    foreach ($raw as $val) {
        $val = trim((string)$val);
        if ($val !== '' && is_numeric($val)) {
            $out[] = (int)$val;
        }
    }
    return array_values(array_unique($out));
}

/**
 * @return array
 */
function pf_parse_post_zakaznici($post) {
    $zakaznici = [];
    if (isset($post['zakaznici']) && is_array($post['zakaznici'])) {
        $zakaznici = $post['zakaznici'];
    } elseif (!empty($post['zakaznik'])) {
        $zakaznici = [trim((string)$post['zakaznik'])];
    }
    return $zakaznici;
}

/**
 * Má některý z aktivních produktů urgentní prioritu?
 *
 * @param mysqli $conn
 * @param array $produkty_ids
 * @return int 0|1
 */
function pf_max_priorita_produkty($conn, array $produkty_ids) {
    if (empty($produkty_ids) || !pf_has_column($conn, 'produkty', 'priorita')) {
        return 0;
    }
    $ids = implode(',', array_map('intval', $produkty_ids));
    if ($ids === '') {
        return 0;
    }
    $res = mysqli_query($conn, "SELECT MAX(priorita) AS m FROM produkty WHERE id IN ($ids) AND ukonceny = 0");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return ((int)($row['m'] ?? 0) === 1) ? 1 : 0;
    }
    return 0;
}

/**
 * Urgentní priorita z aktivních produktů přiřazených k požadavku.
 *
 * @return int 0|1
 */
function pf_priorita_from_linked_produkty($conn, $id_pozadavek) {
    if (!pf_has_table($conn, 'pozadavky_produkty') || !pf_has_column($conn, 'produkty', 'priorita')) {
        return 0;
    }
    $id_pozadavek = (int)$id_pozadavek;
    if ($id_pozadavek <= 0) {
        return 0;
    }
    $res = mysqli_query($conn, "SELECT MAX(pr.priorita) AS m
        FROM pozadavky_produkty pp
        INNER JOIN produkty pr ON pr.id = pp.id_produkt AND pr.ukonceny = 0
        WHERE pp.id_pozadavek = $id_pozadavek");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return ((int)($row['m'] ?? 0) === 1) ? 1 : 0;
    }
    return 0;
}

/**
 * Ruční priorita z formuláře + urgentní produkt(y).
 *
 * @return int 0|1
 */
function pf_effective_request_priorita($conn, $manual_priorita, array $produkty_ids = [], $id_pozadavek = 0) {
    $from_products = 0;
    if (!empty($produkty_ids)) {
        $from_products = pf_max_priorita_produkty($conn, $produkty_ids);
    } elseif ($id_pozadavek > 0) {
        $from_products = pf_priorita_from_linked_produkty($conn, $id_pozadavek);
    }
    return (((int)$manual_priorita === 1) || $from_products === 1) ? 1 : 0;
}

/**
 * Přepočítá prioritu otevřeného požadavku podle propojených aktivních produktů.
 */
function pf_sync_request_priorita_from_produkty($conn, $id_pozadavek, $log_change = true) {
    $id_pozadavek = (int)$id_pozadavek;
    if ($id_pozadavek <= 0) {
        return;
    }

    $res = mysqli_query($conn, "SELECT priorita FROM pozadavky WHERE id = $id_pozadavek AND id_status NOT IN (5,6,7) LIMIT 1");
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return;
    }

    $old_prio = (int)$row['priorita'];
    $new_prio = pf_priorita_from_linked_produkty($conn, $id_pozadavek);

    if ($old_prio === $new_prio) {
        return;
    }

    mysqli_query($conn, "UPDATE pozadavky SET priorita = $new_prio WHERE id = $id_pozadavek");

    if ($log_change) {
        if (!function_exists('zapis_do_historie')) {
            @include_once(__DIR__ . '/boardFunctions.php');
        }
        if (function_exists('zapis_do_historie')) {
            $txt = $new_prio ? 'URGENTNÍ (odvozeno od produktu)' : 'Normální (žádný urgentní produkt)';
            zapis_do_historie($conn, $id_pozadavek, 0, 'urgence', "Priorita požadavku: $txt", '', (string)$new_prio);
        }
    }

    $lc = __DIR__ . '/../last_change.txt';
    if (!file_exists($lc)) {
        $lc = 'last_change.txt';
    }
    @file_put_contents($lc, time());
}

/**
 * Přepočítá prioritu u všech otevřených požadavků navázaných na produkt.
 */
function pf_sync_requests_for_produkt($conn, $id_produkt) {
    if (!pf_has_table($conn, 'pozadavky_produkty')) {
        return;
    }
    $id_produkt = (int)$id_produkt;
    if ($id_produkt <= 0) {
        return;
    }
    $res = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_produkty WHERE id_produkt = $id_produkt");
    if (!$res) {
        return;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        pf_sync_request_priorita_from_produkty($conn, (int)$row['id_pozadavek']);
    }
}

// =============================================================================
// F8: Připravenost surovin u produktu (odvozeno z požadavku / nabídky)
// =============================================================================

/** @return string */
function pf_open_request_status_sql() {
    return '5,6,7';
}

/**
 * Otevřené požadavky seskupené podle id_surovina (pro katalog / kandidáty).
 *
 * @return array<int, array<int, array>>
 */
function pf_load_open_requests_by_surovina($conn) {
    $terminal = pf_open_request_status_sql();
    $out = [];
    $sql = "SELECT p.id, p.id_surovina, p.id_status, p.priorita, p.bio, p.vegan, p.bezlepek, p.kosher, p.halal,
            (SELECT pn.id_status FROM pozadavky_nabidky pn
             WHERE pn.id_pozadavek = p.id AND pn.id_status NOT IN (5, 7)
             ORDER BY pn.id DESC LIMIT 1) AS best_offer_status
            FROM pozadavky p
            WHERE p.id_status NOT IN ($terminal)
            ORDER BY p.priorita DESC, p.id DESC";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return $out;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $sid = (int)$row['id_surovina'];
        if ($sid <= 0) {
            continue;
        }
        $rd = pf_readiness_from_request_row((int)$row['id_status'], $row['best_offer_status']);
        $out[$sid][] = [
            'req_id' => (int)$row['id'],
            'req_status' => (int)$row['id_status'],
            'priorita' => (int)$row['priorita'],
            'bio' => (int)$row['bio'],
            'vegan' => (int)$row['vegan'],
            'bezlepek' => (int)$row['bezlepek'],
            'kosher' => (int)$row['kosher'],
            'halal' => (int)$row['halal'],
            'label' => $rd['label'],
            'phase' => (int)$rd['phase'],
        ];
    }
    return $out;
}

/**
 * Požadavky již navázané na produkt: id_produkt => [id_pozadavek, ...]
 *
 * @return array<int, array<int, int>>
 */
function pf_load_linked_requests_by_product($conn) {
    $out = [];
    if (!pf_has_table($conn, 'pozadavky_produkty')) {
        return $out;
    }
    $res = mysqli_query($conn, 'SELECT id_produkt, id_pozadavek FROM pozadavky_produkty');
    if (!$res) {
        return $out;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $pid = (int)$row['id_produkt'];
        $rid = (int)$row['id_pozadavek'];
        if ($pid > 0 && $rid > 0) {
            $out[$pid][] = $rid;
        }
    }
    return $out;
}

/**
 * Otevřené požadavky na surovinu, které ještě nejsou navázané na daný produkt.
 *
 * @return array<int, array>
 */
function pf_open_requests_for_product_surovina($conn, $id_produkt, $id_surovina, array $open_by_sur = null, array $linked_by_prod = null) {
    $id_produkt = (int)$id_produkt;
    $id_surovina = (int)$id_surovina;
    if ($id_produkt <= 0 || $id_surovina <= 0) {
        return [];
    }
    if ($open_by_sur === null) {
        $open_by_sur = pf_load_open_requests_by_surovina($conn);
    }
    if ($linked_by_prod === null) {
        $linked_by_prod = pf_load_linked_requests_by_product($conn);
    }
    $linked = $linked_by_prod[$id_produkt] ?? [];
    $linked_set = array_flip($linked);
    $candidates = [];
    foreach ($open_by_sur[$id_surovina] ?? [] as $req) {
        if (!isset($linked_set[(int)$req['req_id']])) {
            $candidates[] = $req;
        }
    }
    return $candidates;
}

/**
 * @return array{state:string,label:string,blocking:bool,phase:int}
 */
function pf_readiness_from_request_row($req_status, $best_offer_status) {
    $st = (int)$req_status;
    $offer_st = ($best_offer_status !== null && $best_offer_status !== '') ? (int)$best_offer_status : null;

    if ($st === 6 || $offer_st === 6) {
        return ['state' => 'ok', 'label' => 'Vyřešeno', 'blocking' => false, 'phase' => 0];
    }

    if (!function_exists('nabidkaFaze2Statusy')) {
        @include_once(__DIR__ . '/boardFunctions.php');
    }
    $faze2 = function_exists('nabidkaFaze2Statusy') ? nabidkaFaze2Statusy() : [3, 8, 9, 11, 12, 13, 14];

    if (in_array($st, [10], true) || ($offer_st !== null && in_array($offer_st, [10, 4], true))) {
        return ['state' => 'samples', 'label' => 'Ve vzorkách / lab', 'blocking' => true, 'phase' => 3];
    }

    if ($st === 3 || ($offer_st !== null && in_array($offer_st, $faze2, true))) {
        return ['state' => 'docs', 'label' => 'Dokumenty / objednávka', 'blocking' => true, 'phase' => 2];
    }

    if (in_array($st, [5, 7, 8], true)) {
        return ['state' => 'nakup', 'label' => 'Požadavek pozastaven / KO', 'blocking' => true, 'phase' => 1];
    }

    return ['state' => 'nakup', 'label' => 'Čeká nákup', 'blocking' => true, 'phase' => 1];
}

/**
 * @return int vyšší = lepší kandidát požadavku
 */
function pf_request_pick_score($req_status, $req_id) {
    $st = (int)$req_status;
    if (!in_array($st, [5, 6, 7, 8], true)) {
        return 3000000 + (int)$req_id;
    }
    if ($st === 6) {
        return 2000000 + (int)$req_id;
    }
    return 1000000 + (int)$req_id;
}

/**
 * Doplní ke každé surovině u produktu stav připravenosti + souhrn na produktu.
 *
 * @param mysqli $conn
 * @param array<int, array> $suroviny_by_prod
 * @param array<int, array> $produkty_by_id  id => &produkt pole z $produkty
 */
function pf_enrich_readiness($conn, array &$suroviny_by_prod, array &$produkty_by_id) {
    $missing = [
        'state' => 'missing',
        'label' => 'Chybí požadavek',
        'blocking' => true,
        'phase' => 0,
        'req_id' => 0,
    ];

    if (empty($suroviny_by_prod) || !pf_has_table($conn, 'pozadavky_produkty')) {
        foreach ($suroviny_by_prod as $pid => &$surs) {
            foreach ($surs as &$s) {
                $s['readiness'] = $missing;
                $s['candidate_requests'] = [];
            }
            unset($s);
            if (isset($produkty_by_id[$pid])) {
                $produkty_by_id[$pid]['readiness_summary'] = pf_readiness_summary($surs, $produkty_by_id[$pid]);
            }
        }
        unset($surs);
        return;
    }

    $prod_ids = array_keys($suroviny_by_prod);
    $ids_str = implode(',', array_map('intval', $prod_ids));
    if ($ids_str === '') {
        return;
    }

    $open_by_sur = pf_load_open_requests_by_surovina($conn);
    $linked_by_prod = pf_load_linked_requests_by_product($conn);

    $req_map = [];
    $sql = "SELECT pp.id_produkt, p.id AS req_id, p.id_surovina, p.id_status AS req_status,
            p.priorita, p.bio, p.vegan, p.bezlepek, p.kosher, p.halal,
            (SELECT pn.id_status FROM pozadavky_nabidky pn
             WHERE pn.id_pozadavek = p.id AND pn.id_status NOT IN (5, 7)
             ORDER BY pn.id DESC LIMIT 1) AS best_offer_status
            FROM pozadavky_produkty pp
            INNER JOIN pozadavky p ON p.id = pp.id_pozadavek
            WHERE pp.id_produkt IN ($ids_str)";

    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $key = (int)$row['id_produkt'] . ':' . (int)$row['id_surovina'];
            $score = pf_request_pick_score($row['req_status'], $row['req_id']);
            if (!isset($req_map[$key]) || $score > $req_map[$key]['_score']) {
                $req_map[$key] = [
                    '_score' => $score,
                    'req_id' => (int)$row['req_id'],
                    'req_status' => (int)$row['req_status'],
                    'best_offer_status' => $row['best_offer_status'],
                    'priorita' => (int)$row['priorita'],
                    'bio' => (int)$row['bio'],
                    'vegan' => (int)$row['vegan'],
                    'bezlepek' => (int)$row['bezlepek'],
                    'kosher' => (int)$row['kosher'],
                    'halal' => (int)$row['halal'],
                ];
            }
        }
    }

    foreach ($suroviny_by_prod as $pid => &$surs) {
        foreach ($surs as &$s) {
            $key = (int)$pid . ':' . (int)$s['id_surovina'];
            if (isset($req_map[$key])) {
                $r = $req_map[$key];
                $rd = pf_readiness_from_request_row($r['req_status'], $r['best_offer_status']);
                $rd['req_id'] = $r['req_id'];
                $rd['priorita'] = $r['priorita'];
                $rd['bio'] = $r['bio'];
                $rd['vegan'] = $r['vegan'];
                $rd['bezlepek'] = $r['bezlepek'];
                $rd['kosher'] = $r['kosher'];
                $rd['halal'] = $r['halal'];
                $s['readiness'] = $rd;
            } else {
                $s['readiness'] = $missing;
                $s['candidate_requests'] = pf_open_requests_for_product_surovina(
                    $conn, (int)$pid, (int)$s['id_surovina'], $open_by_sur, $linked_by_prod
                );
            }
        }
        unset($s);
        if (isset($produkty_by_id[$pid])) {
            $produkty_by_id[$pid]['readiness_summary'] = pf_readiness_summary($surs, $produkty_by_id[$pid]);
        }
    }
    unset($surs);
}

/**
 * @param array $surs
 * @param array $produkt
 * @return array{total:int,ok:int,blocking:int,missing:int}
 */
function pf_readiness_summary(array $surs, array $produkt) {
    $sum = ['total' => count($surs), 'ok' => 0, 'blocking' => 0, 'missing' => 0];
    foreach ($surs as $s) {
        $rd = $s['readiness'] ?? null;
        if (!$rd) {
            continue;
        }
        if (($rd['state'] ?? '') === 'ok') {
            $sum['ok']++;
        } elseif (($rd['state'] ?? '') === 'missing') {
            $sum['missing']++;
            $sum['blocking']++;
        } elseif (!empty($rd['blocking'])) {
            $sum['blocking']++;
        }
    }
    $sum['blocks_product'] = !empty($produkt['priorita']) && $sum['blocking'] > 0 && empty($produkt['ukonceny']);
    return $sum;
}

/**
 * Nejvyšší fáze workflow z aktivních nabídek požadavku (pro readiness).
 */
function pf_best_offer_status_for_request(array $offers) {
    if (!function_exists('nabidkaFaze2Statusy')) {
        @include_once(__DIR__ . '/boardFunctions.php');
    }
    $faze2 = function_exists('nabidkaFaze2Statusy') ? nabidkaFaze2Statusy() : [3, 8, 9, 11, 12, 13, 14];
    $best = null;
    $best_phase = -1;
    foreach ($offers as $off) {
        $st = (int)($off['id_status'] ?? 0);
        if (in_array($st, [5, 7], true)) {
            continue;
        }
        $phase = 1;
        if (in_array($st, [10, 4], true)) {
            $phase = 3;
        } elseif (in_array($st, $faze2, true)) {
            $phase = 2;
        }
        if ($phase > $best_phase) {
            $best_phase = $phase;
            $best = $st;
        }
    }
    return $best;
}

/**
 * Kontext propojených vývojových produktů pro detail požadavku (modal nástěnky).
 *
 * @return array{products:array,zakaznici:array,has_links:bool}
 */
function pf_request_detail_products_context($conn, $id_pozadavek, $id_surovina) {
    $id_pozadavek = (int)$id_pozadavek;
    $id_surovina = (int)$id_surovina;
    $zakaznici = [];

    $res_z = @mysqli_query($conn, "SELECT z.id, z.nazev
        FROM pozadavky_zakaznici pz
        JOIN zakaznici z ON z.id = pz.id_zakaznik
        WHERE pz.id_pozadavek = $id_pozadavek
        ORDER BY z.nazev ASC");
    if ($res_z) {
        while ($z = mysqli_fetch_assoc($res_z)) {
            $zakaznici[] = ['id' => (int)$z['id'], 'nazev' => $z['nazev']];
        }
    }

    if (!pf_has_table($conn, 'pozadavky_produkty')) {
        return ['products' => [], 'zakaznici' => $zakaznici, 'has_links' => false];
    }

    $has_poznamka = pf_has_column($conn, 'produkty', 'poznamka');
    $pozn_col = $has_poznamka ? ', pr.poznamka' : ", '' AS poznamka";

    $res_p = mysqli_query($conn, "SELECT pr.id, pr.nazev, pr.priorita, pr.ukonceny, pr.id_zakaznik{$pozn_col},
            z.nazev AS zakaznik_nazev
        FROM pozadavky_produkty pp
        JOIN produkty pr ON pr.id = pp.id_produkt
        LEFT JOIN zakaznici z ON z.id = pr.id_zakaznik
        WHERE pp.id_pozadavek = $id_pozadavek
        ORDER BY pr.ukonceny ASC, pr.priorita DESC, z.nazev ASC, pr.nazev ASC");

    if (!$res_p) {
        return ['products' => [], 'zakaznici' => $zakaznici, 'has_links' => false];
    }

    $products = [];
    $produkty_by_id = [];
    while ($row = mysqli_fetch_assoc($res_p)) {
        $pid = (int)$row['id'];
        $products[$pid] = [
            'id' => $pid,
            'nazev' => $row['nazev'],
            'priorita' => (int)$row['priorita'],
            'ukonceny' => (int)$row['ukonceny'],
            'id_zakaznik' => $row['id_zakaznik'] !== null ? (int)$row['id_zakaznik'] : 0,
            'zakaznik_nazev' => $row['zakaznik_nazev'] ?? '',
            'poznamka' => trim($row['poznamka'] ?? ''),
            'suroviny' => [],
            'komentare' => [],
            'readiness_summary' => ['total' => 0, 'ok' => 0, 'blocking' => 0, 'missing' => 0, 'blocks_product' => false],
        ];
        $produkty_by_id[$pid] = &$products[$pid];
    }

    if (empty($products)) {
        return ['products' => [], 'zakaznici' => $zakaznici, 'has_links' => false];
    }

    $suroviny_by_prod = [];
    if (pf_has_table($conn, 'produkty_suroviny')) {
        $ids = implode(',', array_map('intval', array_keys($products)));
        $res_s = mysqli_query($conn, "SELECT ps.id AS link_id, ps.id_produkt, s.id AS id_surovina, s.nazev, s.nazev_en
            FROM produkty_suroviny ps
            JOIN suroviny s ON s.id = ps.id_surovina
            WHERE ps.id_produkt IN ($ids)
            ORDER BY s.nazev ASC");
        if ($res_s) {
            while ($s = mysqli_fetch_assoc($res_s)) {
                $pid = (int)$s['id_produkt'];
                if (!isset($suroviny_by_prod[$pid])) {
                    $suroviny_by_prod[$pid] = [];
                }
                $suroviny_by_prod[$pid][] = [
                    'link_id' => (int)$s['link_id'],
                    'id_surovina' => (int)$s['id_surovina'],
                    'nazev' => $s['nazev'],
                    'nazev_en' => $s['nazev_en'] ?? '',
                    'is_current' => ((int)$s['id_surovina'] === $id_surovina),
                ];
            }
        }
        pf_enrich_readiness($conn, $suroviny_by_prod, $produkty_by_id);
        foreach ($products as $pid => &$prod) {
            $prod['suroviny'] = $suroviny_by_prod[$pid] ?? [];
            foreach ($prod['suroviny'] as &$sur) {
                $sur['is_current'] = ((int)$sur['id_surovina'] === $id_surovina);
            }
            unset($sur);
            $prod['readiness_summary'] = pf_readiness_summary($prod['suroviny'], $prod);
        }
        unset($prod);
    }

    if (pf_has_table($conn, 'produkty_komentare')) {
        $ids = implode(',', array_map('intval', array_keys($products)));
        $res_k = mysqli_query($conn, "SELECT id_produkt, jmeno_user, text_hodnota, vytvoreno
            FROM produkty_komentare
            WHERE id_produkt IN ($ids)
            ORDER BY vytvoreno DESC");
        $by_prod = [];
        if ($res_k) {
            while ($k = mysqli_fetch_assoc($res_k)) {
                $pid = (int)$k['id_produkt'];
                if (!isset($by_prod[$pid])) {
                    $by_prod[$pid] = [];
                }
                if (count($by_prod[$pid]) >= 8) {
                    continue;
                }
                $by_prod[$pid][] = [
                    'jmeno' => $k['jmeno_user'],
                    'text' => $k['text_hodnota'],
                    'vytvoreno' => $k['vytvoreno'],
                ];
            }
        }
        foreach ($products as $pid => &$prod) {
            $prod['komentare'] = array_reverse($by_prod[$pid] ?? []);
        }
        unset($prod);
    }

    return [
        'products' => array_values($products),
        'zakaznici' => $zakaznici,
        'has_links' => true,
    ];
}
