<?php
include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("permissions.php");
if (session_status() === PHP_SESSION_NONE) session_start();

requireClaimNakupAccess();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) die('Chybné ID požadavku.');

$user_id = (int)($_SESSION['uid'] ?? 0);
if ($user_id <= 0) die('Nepřihlášený uživatel.');

$kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Neznámý');
$kdo_db = mysqli_real_escape_string($conn, $kdo);

$q = mysqli_query($conn, "SELECT p.id_nakupci, u.jmeno AS stary_nakupci 
    FROM pozadavky p 
    LEFT JOIN " . (defined('DB_TBL_USERS') ? DB_TBL_USERS : 'users') . " u ON p.id_nakupci = u.id 
    WHERE p.id = $id LIMIT 1");
if (!$q || !($row = mysqli_fetch_assoc($q))) die('Požadavek nenalezen.');

$stary_id = (int)($row['id_nakupci'] ?? 0);
if ($stary_id === $user_id) die('Tento požadavek už máte přiřazený.');

$stary_jmeno = trim($row['stary_nakupci'] ?? '');

if (!mysqli_query($conn, "UPDATE pozadavky SET id_nakupci = $user_id WHERE id = $id")) {
    die('Chyba databáze: ' . mysqli_error($conn));
}

if ($stary_id > 0) {
    $text = "Požadavek převzat od: $stary_jmeno.";
} else {
    $text = 'Požadavek převzat k řešení (Nákup).';
}
zapis_do_historie($conn, $id, 0, 'prirazeni', $text);

@file_put_contents('last_change.txt', time());
echo 'OK';
