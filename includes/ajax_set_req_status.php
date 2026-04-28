<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['username'])) die("Nepřihlášen");

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

if ($id <= 0 || $status <= 0) die("Chybná data");

$q = "UPDATE pozadavky SET id_status = $status WHERE id = $id";
if (mysqli_query($conn, $q)) {
    // Zápis do historie ať víme, kdo to uspal / probudil
    $text = ($status == 8) ? "Odloženo k ledu (Čeká na lepší časy)." : "Požadavek oživen a vrácen mezi aktivní k řešení.";
    $uid = $_SESSION['uid'] ?? 0;
    $uname = is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username'];

    mysqli_query($conn, "INSERT INTO historie_pozadavku (id_pozadavek, id_nabidka, id_user, jmeno_user, text_hodnota, typ_zaznamu) 
                         VALUES ($id, 0, $uid, '$uname', '$text', 'system')");
    echo "OK";
} else {
    echo "Chyba databáze: " . mysqli_error($conn);
}
?>