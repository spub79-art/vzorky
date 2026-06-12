<?php
include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("permissions.php");
if (session_status() === PHP_SESSION_NONE) session_start();

requireNakupAccess();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) die('Chybné ID požadavku.');

$user_id = (int)($_SESSION['uid'] ?? 0);
$is_adm = !empty($_SESSION['adm']);

$q = mysqli_query($conn, "SELECT id_nakupci FROM pozadavky WHERE id = $id LIMIT 1");
if (!$q || !($row = mysqli_fetch_assoc($q))) die('Požadavek nenalezen.');

$stary_id = (int)($row['id_nakupci'] ?? 0);
if ($stary_id <= 0) die('Požadavek není nikomu přiřazený.');

if (!$is_adm && $stary_id !== $user_id) {
    die('Úkol může uvolnit jen přiřazený nákupčí nebo administrátor.');
}

if (!mysqli_query($conn, "UPDATE pozadavky SET id_nakupci = NULL WHERE id = $id")) {
    die('Chyba databáze: ' . mysqli_error($conn));
}

$kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Neznámý');
$text = $is_adm && $stary_id !== $user_id
    ? 'Přiřazení nákupčího zrušeno administrátorem.'
    : 'Nákupčí uvolnil/a požadavek (Vzdávám to).';
zapis_do_historie($conn, $id, 0, 'prirazeni', $text);

@file_put_contents('last_change.txt', time());
echo 'OK';
