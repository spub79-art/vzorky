<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['uid'])) die("Nepovolený přístup.");

$id = (int)$_POST['id'];
$text = trim($_POST['text'] ?? '');
$user_id = (int)$_SESSION['uid'];
$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);

if (empty($text)) {
    die("Text nesmí být prázdný.");
}

// Zkontrolujeme, jestli záznam existuje a jestli na něj má uživatel právo (je autor, nebo je admin)
$q_check = mysqli_query($conn, "SELECT id_user FROM historie_pozadavku WHERE id = $id");
$row = mysqli_fetch_assoc($q_check);

if (!$row) {
    die("Záznam nenalezen.");
}

if (!$is_adm && $row['id_user'] != $user_id) {
    die("Nemáte oprávnění upravovat cizí komentáře.");
}

$text_safe = mysqli_real_escape_string($conn, $text);

$sql = "UPDATE historie_pozadavku SET text_hodnota = '$text_safe' WHERE id = $id";

if (mysqli_query($conn, $sql)) {
    @file_put_contents('../last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba databáze: " . mysqli_error($conn);
}
?>