<?php
session_start();
if (empty($_SESSION['username'])) {
    die('Nepovolený přístup.');
}

include_once(__DIR__ . '/db_connect.php');
include_once(__DIR__ . '/boardFunctions.php');
include_once(__DIR__ . '/permissions.php');
include_once(__DIR__ . '/telegram.php');

$perms = loadSessionPermissions();
$is_adm = !empty($perms['is_adm']);
$is_vyvoj = !empty($perms['is_vyvoj']);
$is_kvalita = !empty($perms['is_kvalita']);
$can_nakup = !empty($perms['can_nakup']);
$uid = (int)($perms['current_uid'] ?? 0);

if (!wf_delegace_has_columns($conn)) {
    die('Chybí migrace wf_delegace — spusťte migrate_workflow_delegace.sql');
}

$id = (int)($_POST['id_nabidka'] ?? 0);
$action = trim($_POST['action'] ?? 'set');
if ($id <= 0) {
    die('Chyba ID nabídky.');
}

$res = mysqli_query($conn, "SELECT pn.*, p.id AS req_id, s.nazev AS surovina_nazev, d.nazev AS dodavatel_nazev
    FROM pozadavky_nabidky pn
    INNER JOIN pozadavky p ON p.id = pn.id_pozadavek
    LEFT JOIN suroviny s ON s.id = p.id_surovina
    LEFT JOIN dodavatele d ON d.id = pn.id_dodavatel
    WHERE pn.id = $id LIMIT 1");
$row = $res ? mysqli_fetch_assoc($res) : null;
if (!$row) {
    die('Nabídka nenalezena.');
}

$req_id = (int)$row['req_id'];
$st = (int)$row['id_status'];
if (nabidkaJeZamitnuta($st)) {
    die('U zamítnuté nabídky nelze delegovat.');
}

$kdo = htmlspecialchars(is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username']);
$sur = htmlspecialchars($row['surovina_nazev'] ?? 'Neznámá');
$dod = htmlspecialchars($row['dodavatel_nazev'] ?? 'Dodavatel');

if ($action === 'clear') {
    $komu = $row['wf_delegace_komu'] ?? '';
    $od = $row['wf_delegace_od'] ?? '';
    $can_clear = $is_adm
        || ($komu === 'nakup' && $can_nakup)
        || ($komu === 'kvalita' && $is_kvalita)
        || ($komu === 'vyvoj' && $is_vyvoj)
        || ($od === 'vyvoj' && $is_vyvoj)
        || ($od === 'nakup' && $can_nakup)
        || ($od === 'kvalita' && $is_kvalita);
    if (!$can_clear) {
        die('Nemáte oprávnění zrušit delegaci.');
    }

    mysqli_query($conn, "UPDATE pozadavky_nabidky SET
        wf_delegace_komu = NULL,
        wf_delegace_od = NULL,
        wf_delegace_duvod = NULL,
        wf_delegace_vytvoreno = NULL
        WHERE id = $id");

    zapis_do_historie($conn, $req_id, $id, 'workflow_delegace', 'Delegace zrušena — workflow pokračuje u původního oddělení.');
    @file_put_contents('last_change.txt', time());
    echo 'OK';
    exit;
}

// --- set ---
$komu = trim($_POST['komu'] ?? 'nakup');
$duvod = trim($_POST['duvod'] ?? '');
if (!in_array($komu, ['nakup', 'kvalita'], true)) {
    die('Neplatné cílové oddělení.');
}
if ($duvod === '') {
    die('Vyplňte, co brání nebo co je potřeba vyřešit.');
}
if (mb_strlen($duvod) > 500) {
    die('Důvod je příliš dlouhý (max 500 znaků).');
}

$od = null;
if ($is_vyvoj) {
    $od = 'vyvoj';
} elseif ($is_kvalita) {
    $od = 'kvalita';
} elseif ($can_nakup) {
    $od = 'nakup';
} elseif ($is_adm) {
    $od = trim($_POST['od'] ?? 'vyvoj');
    if (!in_array($od, ['vyvoj', 'nakup', 'kvalita'], true)) {
        $od = 'vyvoj';
    }
}

if (!$od) {
    die('Nemáte oprávnění nastavit delegaci.');
}

$vyvoj_stavy = [2, 13, 10, 4];
$nakup_stavy = [3, STATUS_NABIDKA_BEZ_CENY, 9, 8, 11, 12, 13];
$kvalita_stavy = [12];

$allowed = false;
if ($od === 'vyvoj' && $is_vyvoj && in_array($st, $vyvoj_stavy, true)) {
    $allowed = true;
}
if ($od === 'nakup' && $can_nakup && in_array($st, $nakup_stavy, true)) {
    $allowed = true;
}
if ($od === 'kvalita' && $is_kvalita && in_array($st, $kvalita_stavy, true)) {
    $allowed = true;
}
if ($is_adm) {
    $allowed = true;
}
if (!$allowed) {
    die('V tomto stavu nelze delegovat z vašeho oddělení.');
}

$duvod_esc = mysqli_real_escape_string($conn, $duvod);
$komu_esc = mysqli_real_escape_string($conn, $komu);
$od_esc = mysqli_real_escape_string($conn, $od);

mysqli_query($conn, "UPDATE pozadavky_nabidky SET
    wf_delegace_komu = '$komu_esc',
    wf_delegace_od = '$od_esc',
    wf_delegace_duvod = '$duvod_esc',
    wf_delegace_vytvoreno = NOW()
    WHERE id = $id");

$hist = 'Předáno ' . wf_delegace_dept_label($komu) . ' (odbočka workflow). Důvod: ' . $duvod;
zapis_do_historie($conn, $req_id, $id, 'workflow_delegace', $hist);

$is_dev = (strpos($_SERVER['REQUEST_URI'] ?? '', 'dev-vzorky') !== false);
$url = 'https://docs.lifefood.eu/' . ($is_dev ? 'dev-vzorky' : 'vzorky');
$msg = "↪ <b>WORKFLOW: Předání úkolu</b>\n\n";
$msg .= "<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod · nab. #$id\n";
$msg .= "<b>Od:</b> " . wf_delegace_dept_label($od) . " ($kdo)\n";
$msg .= "<b>Řeší:</b> " . wf_delegace_dept_label($komu) . "\n";
$msg .= "\n💬 <b>Co je potřeba:</b>\n<i>" . htmlspecialchars($duvod) . "</i>\n";
$msg .= "\n<i>Stav nabídky se nemění — jde o odbočku, ne KO ani CENA OK.</i>";
$msg .= "\n👉 <a href='$url/index.php?Pozadavek=1&req_id=$req_id'>Otevřít detail</a>";

$tg_channel = ($komu === 'kvalita') ? 'kvalita' : 'nakup';
if ($komu === 'vyvoj') {
    $tg_channel = 'vyvoj';
}
sendTelegram($msg, $tg_channel);

@file_put_contents('last_change.txt', time());
echo 'OK';
