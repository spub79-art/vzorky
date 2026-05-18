<?php
session_start();
include_once("db_connect.php");
if (empty($_SESSION['uid'])) die("Nepovolený přístup.");

$id = intval($_POST['id']);

// Nyní záznamy nemažeme fyzicky, pouze je označíme jako skryté.
// Odebrána restrikce na typ záznamu, takže uživatelé mohou skrýt i systémové zprávy.
$sql = "UPDATE historie_pozadavku SET skryto = 1 WHERE id = $id";

if (mysqli_query($conn, $sql)) {
    @file_put_contents('../last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba: " . mysqli_error($conn);
}
?>