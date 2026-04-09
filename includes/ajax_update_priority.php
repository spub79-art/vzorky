<?php
session_start();
include_once("db_connect.php");

if (empty($_SESSION['username'])) die("Nepovolený přístup.");

$id = intval($_POST['id'] ?? 0);
$priorita = intval($_POST['priorita'] ?? 0);

if ($id > 0) {
    // Uložíme novou prioritu do databáze (přepíše 0 na 1, nebo 1 na 0)
    $stmt = $conn->prepare("UPDATE pozadavky SET priorita = ? WHERE id = ?");
    $stmt->bind_param("ii", $priorita, $id);

    if ($stmt->execute()) {
        // Změníme čas poslední úpravy, aby se to všem překreslilo
        @file_put_contents('../last_change.txt', time()); // nebo 'last_change.txt' podle toho, jak máš cesty
        echo "OK";
    } else {
        echo "Chyba DB: " . $conn->error;
    }
} else {
    echo "Chyba: Chybí ID.";
}
?>