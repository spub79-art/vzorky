<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['username'])) die("Nepřihlášen");

$id = (int)$_POST['id'];
$nazev = trim($_POST['nazev']);
$nazev_en = trim($_POST['nazev_en']);

if ($id === 0 || empty($nazev)) {
    die("Chybějící nebo neplatná data.");
}

$stmt = $conn->prepare("UPDATE suroviny SET nazev = ?, nazev_en = ? WHERE id = ?");
$stmt->bind_param("ssi", $nazev, $nazev_en, $id);

if ($stmt->execute()) {
    echo "OK";
} else {
    echo "Chyba databáze: " . $stmt->error;
}
?>