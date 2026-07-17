<?php
session_start();
if (empty($_SESSION['username'])) {
    die('Nepovolený přístup.');
}

include_once(__DIR__ . '/db_connect.php');
include_once(__DIR__ . '/boardFunctions.php');
include_once(__DIR__ . '/permissions.php');

$perms = loadSessionPermissions();
$is_adm = !empty($perms['is_adm']);
$is_vyvoj = !empty($perms['is_vyvoj']);
$can_nakup = !empty($perms['can_nakup']);
$is_kvalita = !empty($perms['is_kvalita']);

if (!$is_adm && !$is_vyvoj && !$can_nakup && !$is_kvalita) {
    die('Nemáte oprávnění.');
}

$id = (int)($_POST['id'] ?? 0);
$req_id_post = (int)($_POST['req_id'] ?? 0);
$action = trim($_POST['action'] ?? 'set');
$poznamka = trim($_POST['poznamka'] ?? '');

$res = mysqli_query($conn, "SELECT pn.*, p.id AS req_id, s.nazev AS surovina_nazev, d.nazev AS dodavatel_nazev
    FROM pozadavky_nabidky pn
    INNER JOIN pozadavky p ON p.id = pn.id_pozadavek
    LEFT JOIN suroviny s ON s.id = p.id_surovina
    LEFT JOIN dodavatele d ON d.id = pn.id_dodavatel
    WHERE pn.id = $id LIMIT 1");
$row = ($id > 0 && $res) ? mysqli_fetch_assoc($res) : null;

if ($action === 'revive_all') {
    $req_id = $req_id_post > 0 ? $req_id_post : (int)($row['req_id'] ?? 0);
    if ($req_id <= 0) {
        die('Chyba ID požadavku.');
    }
    $q = mysqli_query($conn, "SELECT id FROM pozadavky_nabidky WHERE id_pozadavek = $req_id AND id_status = " . STATUS_NABIDKA_ODLOZENO);
    $count = 0;
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            if (nabidka_odleduj($conn, (int)$r['id'], $poznamka !== '' ? $poznamka : 'Hromadné odledování')) {
                $count++;
            }
        }
    }
    if ($count > 0) {
        zapis_do_historie($conn, $req_id, 0, 'status', "Odledováno $count nabídek k ledu", '', '');
    }
    @file_put_contents('last_change.txt', time());
    echo 'OK:' . $count;
    exit;
}

if ($id <= 0 || !$row) {
    die('Chyba ID nabídky.');
}

$req_id = (int)$row['req_id'];
$cur_st = (int)$row['id_status'];
$dod = trim($row['dodavatel_nazev'] ?? 'Dodavatel');

if ($action === 'win') {
    if (!$is_vyvoj && !$is_adm) {
        die('Vítěznou nabídku může potvrdit Vývoj nebo admin.');
    }
    if ($cur_st !== 6) {
        die('Nejdřív schvalte TEST OK — vítězná nabídka musí být ve stavu schváleno.');
    }
    $note = $poznamka !== '' ? $poznamka : "Vyhrává nabídka #$id ($dod)";
    $others = mysqli_query($conn, "SELECT id FROM pozadavky_nabidky
        WHERE id_pozadavek = $req_id AND id != $id AND id_status NOT IN (5, 7, " . STATUS_NABIDKA_ODLOZENO . ", 6)");
    $count = 0;
    if ($others) {
        while ($o = mysqli_fetch_assoc($others)) {
            if (nabidka_odesli_k_ledu($conn, (int)$o['id'], $note, $req_id)) {
                $count++;
            }
        }
    }
    zapis_do_historie($conn, $req_id, $id, 'status', "🏆 Vítězná nabídka — $count alternativ k ledu. $note");
    @file_put_contents('last_change.txt', time());
    echo 'OK:' . $count;
    exit;
}

if ($action === 'revive') {
    if (!nabidka_odleduj($conn, $id, $poznamka !== '' ? $poznamka : 'Odledováno')) {
        die('Nabídku nelze odledovat.');
    }
    @file_put_contents('last_change.txt', time());
    echo 'OK';
    exit;
}

// --- set (jedna nabídka k ledu) ---
if ($poznamka === '') {
    die('Napište krátký důvod (např. vyhrává jiná nabídka).');
}
if ($cur_st === 6) {
    die('Schválenou (vítěznou) nabídku nelze odložit.');
}
if (nabidkaJeSkryta($cur_st)) {
    die('Nabídku v tomto stavu nelze odložit.');
}
if (!nabidka_odesli_k_ledu($conn, $id, $poznamka, $req_id)) {
    die('Nepodařilo se odložit k ledu.');
}
@file_put_contents('last_change.txt', time());
echo 'OK';
