<?php
session_start();
include_once("db_connect.php");
if (empty($_SESSION['uid'])) die("Nepovolený přístup.");

$id = intval($_POST['id']);

// Ochrana: Lze smazat pouze ruční komentáře (normální i urgentní)! Systémová historie je svatá a nevratná.
$sql = "DELETE FROM historie_pozadavku WHERE id = $id AND typ_zaznamu IN ('komentar', 'komentar_urgentni')";

if (mysqli_query($conn, $sql)) {
    @file_put_contents('../last_change.txt', time()); // Upravena cesta k last_change.txt, pokud je script v includes/
    echo "OK";
} else {
    echo "Chyba: " . mysqli_error($conn);
}
?>