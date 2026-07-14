<?php
session_start();
include_once('db_connect.php');
include_once('boardFunctions.php');
include_once('permissions.php');
include_once('digest_helpers.php');

if (empty($_SESSION['username'])) {
    die('Nepovolený přístup.');
}

if (!$can_nakup && !$is_adm) {
    die('Jen pro Nákup.');
}

if (!digest_has_snooze_columns($conn)) {
    die('Spusťte migraci migrate_souhrn_snooze.sql');
}

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'set';
$poznamka = trim($_POST['poznamka'] ?? '');
$days = (int)($_POST['days'] ?? 7);

if ($id <= 0) {
    die('Chyba ID.');
}

$kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo');

if ($action === 'clear') {
    mysqli_query($conn, "UPDATE pozadavky SET souhrn_snooze_do = NULL, souhrn_snooze_poznamka = NULL WHERE id = $id");
    zapis_do_historie($conn, $id, 0, 'komentar', "Zrušeno snížení priority v Souhrnu (dlouhé dodání). Autor: $kdo");
    @file_put_contents('last_change.txt', time());
    echo 'OK';
    exit;
}

if ($days <= 0) {
    $days = 7;
}
if ($days > 90) {
    $days = 90;
}

$until = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
$note_db = mysqli_real_escape_string($conn, mb_substr($poznamka, 0, 250));
$until_db = mysqli_real_escape_string($conn, $until);

mysqli_query($conn, "UPDATE pozadavky SET souhrn_snooze_do = '$until_db', souhrn_snooze_poznamka = " . ($note_db !== '' ? "'$note_db'" : 'NULL') . " WHERE id = $id");

$log = "Souhrn: snížena priorita (dlouhé dodání) do " . date('j.n.Y', strtotime($until));
if ($poznamka !== '') {
    $log .= " — " . $poznamka;
}
zapis_do_historie($conn, $id, 0, 'komentar', $log);

@file_put_contents('last_change.txt', time());
echo 'OK';
