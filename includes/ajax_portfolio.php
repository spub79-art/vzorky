<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once("permissions.php");
include_once("portfolio_helpers.php");

header('Content-Type: application/json; charset=utf-8');

if (!userCanPortfolio()) {
    http_response_code(403);
    echo json_encode(['error' => 'Nepovolený přístup']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

function pf_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function pf_esc($conn, $s) {
    return mysqli_real_escape_string($conn, $s);
}

function pf_sirotcinec_count($conn) {
    $terminal = '5,6,7';
    if (pf_has_table($conn, 'pozadavky_produkty')) {
        $sql = "SELECT COUNT(*) AS c FROM pozadavky p
                WHERE p.id_status NOT IN ($terminal)
                AND NOT EXISTS (
                    SELECT 1 FROM pozadavky_produkty pp
                    INNER JOIN produkty pr ON pr.id = pp.id_produkt AND pr.ukonceny = 0
                    WHERE pp.id_pozadavek = p.id
                )";
    } else {
        $sql = "SELECT COUNT(*) AS c FROM pozadavky p WHERE p.id_status NOT IN ($terminal)";
    }
    $res = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($res);
    return (int)($row['c'] ?? 0);
}

if ($action === 'tree') {
    $has_pf_cols = pf_has_column($conn, 'produkty', 'id_zakaznik');
    $has_poznamka = pf_has_column($conn, 'produkty', 'poznamka');

    $zakaznici = [];
    $res_z = mysqli_query($conn, "SELECT id, nazev FROM zakaznici ORDER BY nazev ASC");
    while ($z = mysqli_fetch_assoc($res_z)) {
        $zakaznici[] = [
            'id' => (int)$z['id'],
            'nazev' => $z['nazev'],
            'pocet_produktu' => 0,
            'pocet_urgent' => 0,
        ];
    }

    $produkty = [];
    $suroviny_by_prod = [];

    if ($has_pf_cols) {
        $pozn_col = $has_poznamka ? ', p.poznamka' : '';
        $sql_p = "SELECT p.id, p.id_zakaznik, p.nazev, p.priorita, p.ukonceny{$pozn_col},
                         (SELECT COUNT(*) FROM produkty_suroviny ps WHERE ps.id_produkt = p.id) AS pocet_surovin
                  FROM produkty p
                  ORDER BY p.priorita DESC, p.nazev ASC";
    } else {
        $sql_p = "SELECT p.id, NULL AS id_zakaznik, p.nazev, 0 AS priorita, 0 AS ukonceny,
                         '' AS poznamka,
                         (SELECT COUNT(*) FROM produkty_suroviny ps WHERE ps.id_produkt = p.id) AS pocet_surovin
                  FROM produkty p
                  ORDER BY p.nazev ASC";
    }

    $res_p = mysqli_query($conn, $sql_p);
    while ($p = mysqli_fetch_assoc($res_p)) {
        $id_z = $p['id_zakaznik'] !== null ? (int)$p['id_zakaznik'] : 0;
        $item = [
            'id' => (int)$p['id'],
            'id_zakaznik' => $id_z,
            'nazev' => $p['nazev'],
            'priorita' => (int)$p['priorita'],
            'ukonceny' => (int)$p['ukonceny'],
            'pocet_surovin' => (int)$p['pocet_surovin'],
        ];
        if ($has_poznamka) {
            $item['poznamka'] = $p['poznamka'] ?? '';
        }
        $produkty[] = $item;
    }

    $res_s = mysqli_query($conn, "SELECT ps.id AS link_id, ps.id_produkt, s.id AS id_surovina, s.nazev, s.nazev_en
                                  FROM produkty_suroviny ps
                                  JOIN suroviny s ON ps.id_surovina = s.id
                                  ORDER BY s.nazev ASC");
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
        ];
    }

    $suroviny_catalog = [];
    $res_cat = mysqli_query($conn, "SELECT id, nazev, nazev_en FROM suroviny ORDER BY nazev ASC");
    while ($c = mysqli_fetch_assoc($res_cat)) {
        $suroviny_catalog[] = [
            'id' => (int)$c['id'],
            'nazev' => $c['nazev'],
            'nazev_en' => $c['nazev_en'] ?? '',
        ];
    }

    $produkty_by_id = [];
    foreach ($produkty as $idx => $p) {
        $produkty_by_id[(int)$p['id']] = &$produkty[$idx];
    }
    pf_enrich_readiness($conn, $suroviny_by_prod, $produkty_by_id);

    $open_requests_by_surovina = pf_load_open_requests_by_surovina($conn);
    $linked_requests_by_prod = pf_load_linked_requests_by_product($conn);

    foreach ($produkty as $idx => $p) {
        if (!isset($produkty[$idx]['readiness_summary'])) {
            $produkty[$idx]['readiness_summary'] = [
                'total' => 0, 'ok' => 0, 'blocking' => 0, 'missing' => 0, 'blocks_product' => false,
            ];
        }
    }

    $z_map = [];
    foreach ($zakaznici as $i => $z) {
        $z_map[$z['id']] = $i;
    }

    $neprirazeno = 0;
    $urgent_neprirazeno = 0;

    foreach ($produkty as $p) {
        if ($p['ukonceny']) {
            continue;
        }
        if ($p['id_zakaznik'] === 0) {
            $neprirazeno++;
            if ($p['priorita']) {
                $urgent_neprirazeno++;
            }
            continue;
        }
        if (isset($z_map[$p['id_zakaznik']])) {
            $idx = $z_map[$p['id_zakaznik']];
            $zakaznici[$idx]['pocet_produktu']++;
            if ($p['priorita']) {
                $zakaznici[$idx]['pocet_urgent']++;
            }
        }
    }

    pf_json([
        'ok' => true,
        'migration_ok' => $has_pf_cols,
        'notes_ok' => $has_poznamka && pf_has_table($conn, 'produkty_komentare'),
        'sirotcinec_count' => pf_sirotcinec_count($conn),
        'zakaznici' => $zakaznici,
        'neprirazeno' => ['pocet_produktu' => $neprirazeno, 'pocet_urgent' => $urgent_neprirazeno],
        'produkty' => $produkty,
        'suroviny' => $suroviny_by_prod,
        'suroviny_catalog' => $suroviny_catalog,
        'open_requests_by_surovina' => $open_requests_by_surovina,
        'linked_requests_by_prod' => $linked_requests_by_prod,
    ]);
}

if ($action === 'orphans') {
    $terminal = '5,6,7';
    $filter = trim($_GET['q'] ?? '');
    $where = "p.id_status NOT IN ($terminal)";
    if (pf_has_table($conn, 'pozadavky_produkty')) {
        $where .= " AND NOT EXISTS (
            SELECT 1 FROM pozadavky_produkty pp
            INNER JOIN produkty pr ON pr.id = pp.id_produkt AND pr.ukonceny = 0
            WHERE pp.id_pozadavek = p.id
        )";
    }
    if ($filter !== '') {
        $f = pf_esc($conn, $filter);
        $where .= " AND (s.nazev LIKE '%$f%' OR s.nazev_en LIKE '%$f%' OR p.id LIKE '%$f%')";
    }
    $sql = "SELECT p.id, p.id_status, p.priorita, p.bio, p.vegan, p.bezlepek, p.kosher, p.halal,
                   s.nazev AS surovina, s.nazev_en AS surovina_en,
                   cs.nazev AS status_nazev, cs.barva_hex
            FROM pozadavky p
            INNER JOIN suroviny s ON s.id = p.id_surovina
            LEFT JOIN ciselnik_statusu cs ON cs.id = p.id_status
            WHERE $where
            ORDER BY p.priorita DESC, p.id DESC
            LIMIT 500";
    $res = mysqli_query($conn, $sql);
    $items = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $items[] = [
            'id' => (int)$r['id'],
            'id_status' => (int)$r['id_status'],
            'priorita' => (int)$r['priorita'],
            'bio' => (int)$r['bio'],
            'vegan' => (int)$r['vegan'],
            'bezlepek' => (int)$r['bezlepek'],
            'kosher' => (int)$r['kosher'],
            'halal' => (int)$r['halal'],
            'surovina' => $r['surovina'],
            'surovina_en' => $r['surovina_en'] ?? '',
            'status_nazev' => $r['status_nazev'] ?? '',
            'barva_hex' => $r['barva_hex'] ?? '#ccc',
        ];
    }
    pf_json(['ok' => true, 'items' => $items, 'count' => count($items)]);
}

if ($action === 'product_extras') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        pf_json(['error' => 'Neplatný produkt.']);
    }
    $poznamka = '';
    if (pf_has_column($conn, 'produkty', 'poznamka')) {
        $res = mysqli_query($conn, "SELECT poznamka FROM produkty WHERE id=$id");
        $row = mysqli_fetch_assoc($res);
        $poznamka = $row['poznamka'] ?? '';
    }
    $komentare = [];
    if (pf_has_table($conn, 'produkty_komentare')) {
        $res = mysqli_query($conn, "SELECT id, jmeno_user, text_hodnota, vytvoreno
                                    FROM produkty_komentare WHERE id_produkt=$id
                                    ORDER BY vytvoreno ASC");
        while ($k = mysqli_fetch_assoc($res)) {
            $komentare[] = [
                'id' => (int)$k['id'],
                'jmeno' => $k['jmeno_user'],
                'text' => $k['text_hodnota'],
                'vytvoreno' => $k['vytvoreno'],
            ];
        }
    }
    pf_json(['ok' => true, 'poznamka' => $poznamka, 'komentare' => $komentare]);
}

if ($action === 'save_poznamka') {
    if (!pf_has_column($conn, 'produkty', 'poznamka')) {
        pf_json(['error' => 'Spusťte migraci migrate_portfolio_notes_sirotcinec.sql']);
    }
    $id = (int)($_POST['id_produkt'] ?? 0);
    $text = $_POST['poznamka'] ?? '';
    if ($id <= 0) {
        pf_json(['error' => 'Neplatný produkt.']);
    }
    $t = pf_esc($conn, $text);
    mysqli_query($conn, "UPDATE produkty SET poznamka='$t' WHERE id=$id");
    pf_json(['ok' => true]);
}

if ($action === 'add_komentar') {
    if (!pf_has_table($conn, 'produkty_komentare')) {
        pf_json(['error' => 'Spusťte migraci migrate_portfolio_notes_sirotcinec.sql']);
    }
    $id = (int)($_POST['id_produkt'] ?? 0);
    $text = trim($_POST['text'] ?? '');
    if ($id <= 0 || $text === '') {
        pf_json(['error' => 'Vyplňte zprávu.']);
    }
    $uid = (int)($_SESSION['uid'] ?? 0);
    $jmeno = pf_esc($conn, $_SESSION['username'] ?? 'Uživatel');
    $t = pf_esc($conn, $text);
    mysqli_query($conn, "INSERT INTO produkty_komentare (id_produkt, id_user, jmeno_user, text_hodnota)
                         VALUES ($id, $uid, '$jmeno', '$t')");
    pf_json(['ok' => true, 'id' => (int)mysqli_insert_id($conn)]);
}

if ($action === 'link_pozadavek') {
    if (!pf_has_table($conn, 'pozadavky_produkty')) {
        pf_json(['error' => 'Spusťte migraci migrate_portfolio_notes_sirotcinec.sql']);
    }
    $pid = (int)($_POST['id_pozadavek'] ?? 0);
    $prod = (int)($_POST['id_produkt'] ?? 0);
    if ($pid <= 0 || $prod <= 0) {
        pf_json(['error' => 'Neplatná vazba.']);
    }
    mysqli_query($conn, "INSERT IGNORE INTO pozadavky_produkty (id_pozadavek, id_produkt) VALUES ($pid, $prod)");
    $sid = 0;
    $res_sur = mysqli_query($conn, "SELECT id_surovina FROM pozadavky WHERE id = $pid LIMIT 1");
    if ($res_sur && $row_sur = mysqli_fetch_assoc($res_sur)) {
        $sid = (int)$row_sur['id_surovina'];
    }
    if ($sid > 0) {
        mysqli_query($conn, "INSERT IGNORE INTO produkty_suroviny (id_produkt, id_surovina) VALUES ($prod, $sid)");
    }
    pf_sync_request_priorita_from_produkty($conn, $pid);
    pf_json(['ok' => true]);
}

if ($action === 'link_surovina') {
    if (!pf_has_table($conn, 'pozadavky_produkty')) {
        pf_json(['error' => 'Spusťte migraci migrate_portfolio_notes_sirotcinec.sql']);
    }
    $pid = (int)($_POST['id_produkt'] ?? 0);
    $sid = (int)($_POST['id_surovina'] ?? 0);
    $req_id = (int)($_POST['id_pozadavek'] ?? 0);
    if ($pid <= 0 || $sid <= 0) {
        pf_json(['error' => 'Neplatný produkt nebo surovina.']);
    }
    mysqli_query($conn, "INSERT IGNORE INTO produkty_suroviny (id_produkt, id_surovina) VALUES ($pid, $sid)");

    if ($req_id <= 0) {
        $candidates = pf_open_requests_for_product_surovina($conn, $pid, $sid);
        if (count($candidates) === 1) {
            $req_id = (int)$candidates[0]['req_id'];
        }
    }
    if ($req_id > 0) {
        $chk = mysqli_query($conn, "SELECT id FROM pozadavky WHERE id = $req_id AND id_surovina = $sid LIMIT 1");
        if (!$chk || !mysqli_fetch_assoc($chk)) {
            pf_json(['error' => 'Požadavek nepatří k této surovině.']);
        }
        mysqli_query($conn, "INSERT IGNORE INTO pozadavky_produkty (id_pozadavek, id_produkt) VALUES ($req_id, $pid)");
        pf_sync_request_priorita_from_produkty($conn, $req_id);
    }

    pf_json(['ok' => true, 'linked_req' => $req_id]);
}

if ($action === 'unlink_surovina') {
    $link_id = (int)($_POST['link_id'] ?? 0);
    if ($link_id <= 0) {
        pf_json(['error' => 'Neplatná vazba.']);
    }
    mysqli_query($conn, "DELETE FROM produkty_suroviny WHERE id=$link_id");
    if (mysqli_affected_rows($conn) < 1) {
        pf_json(['error' => 'Vazba už neexistuje — obnovte stránku.']);
    }
    pf_json(['ok' => true]);
}

if ($action === 'save_zakaznik') {
    $id = (int)($_POST['id'] ?? 0);
    $nazev = trim($_POST['nazev'] ?? '');
    if ($nazev === '') {
        pf_json(['error' => 'Zadejte název zákazníka.']);
    }
    $n = pf_esc($conn, $nazev);
    if ($id > 0) {
        mysqli_query($conn, "UPDATE zakaznici SET nazev='$n' WHERE id=$id");
        pf_json(['ok' => true, 'id' => $id, 'nazev' => $nazev]);
    }
    mysqli_query($conn, "INSERT INTO zakaznici (nazev) VALUES ('$n')");
    pf_json(['ok' => true, 'id' => (int)mysqli_insert_id($conn), 'nazev' => $nazev]);
}

if ($action === 'save_produkt') {
    if (!pf_has_column($conn, 'produkty', 'id_zakaznik')) {
        pf_json(['error' => 'Spusťte migraci migrate_portfolio_produkty.sql']);
    }
    $id = (int)($_POST['id'] ?? 0);
    $id_zakaznik = (int)($_POST['id_zakaznik'] ?? 0);
    $nazev = trim($_POST['nazev'] ?? '');
    $priorita = (int)($_POST['priorita'] ?? 0) ? 1 : 0;

    if ($nazev === '') {
        pf_json(['error' => 'Zadejte název produktu.']);
    }
    if ($id_zakaznik <= 0) {
        pf_json(['error' => 'Vyberte zákazníka.']);
    }

    $n = pf_esc($conn, $nazev);
    $zid_sql = $id_zakaznik > 0 ? $id_zakaznik : 'NULL';

    if ($id > 0) {
        $sql = "UPDATE produkty SET nazev='$n', id_zakaznik=$id_zakaznik, priorita=$priorita WHERE id=$id";
        mysqli_query($conn, $sql);
        pf_sync_requests_for_produkt($conn, $id);
        pf_json(['ok' => true, 'id' => $id]);
    }

    $sql = "INSERT INTO produkty (id_zakaznik, nazev, priorita, skupzbo, regcis, ukonceny)
            VALUES ($id_zakaznik, '$n', $priorita, '', '', 0)";
    mysqli_query($conn, $sql);
    pf_json(['ok' => true, 'id' => (int)mysqli_insert_id($conn)]);
}

if ($action === 'end_produkt') {
    if (!pf_has_column($conn, 'produkty', 'ukonceny')) {
        pf_json(['error' => 'Spusťte migraci migrate_portfolio_produkty.sql']);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        pf_json(['error' => 'Neplatný produkt.']);
    }
    if (pf_has_column($conn, 'produkty', 'ukonceno_datum')) {
        $ok = mysqli_query($conn, "UPDATE produkty SET ukonceny=1, ukonceno_datum=NOW() WHERE id=$id");
    } else {
        $ok = mysqli_query($conn, "UPDATE produkty SET ukonceny=1 WHERE id=$id");
    }
    if (!$ok) {
        pf_json(['error' => 'Chyba databáze: ' . mysqli_error($conn)]);
    }
    pf_sync_requests_for_produkt($conn, $id);
    pf_json(['ok' => true]);
}

if ($action === 'restore_produkt') {
    if (!pf_has_column($conn, 'produkty', 'ukonceny')) {
        pf_json(['error' => 'Spusťte migraci migrate_portfolio_produkty.sql']);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        pf_json(['error' => 'Neplatný produkt.']);
    }
    if (pf_has_column($conn, 'produkty', 'ukonceno_datum')) {
        $ok = mysqli_query($conn, "UPDATE produkty SET ukonceny=0, ukonceno_datum=NULL WHERE id=$id");
    } else {
        $ok = mysqli_query($conn, "UPDATE produkty SET ukonceny=0 WHERE id=$id");
    }
    if (!$ok) {
        pf_json(['error' => 'Chyba databáze: ' . mysqli_error($conn)]);
    }
    pf_sync_requests_for_produkt($conn, $id);
    pf_json(['ok' => true]);
}

if ($action === 'toggle_priorita') {
    if (!pf_has_column($conn, 'produkty', 'priorita')) {
        pf_json(['error' => 'Spusťte migraci migrate_portfolio_produkty.sql']);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        pf_json(['error' => 'Neplatný produkt.']);
    }
    $res = mysqli_query($conn, "SELECT priorita FROM produkty WHERE id=$id");
    $row = mysqli_fetch_assoc($res);
    if (!$row) {
        pf_json(['error' => 'Produkt nenalezen.']);
    }
    $new = ((int)$row['priorita'] === 1) ? 0 : 1;
    mysqli_query($conn, "UPDATE produkty SET priorita=$new WHERE id=$id");
    pf_sync_requests_for_produkt($conn, $id);
    pf_json(['ok' => true, 'priorita' => $new]);
}

pf_json(['error' => 'Neznámá akce']);
