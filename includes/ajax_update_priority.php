<?php
session_start();
include_once("db_connect.php");
include_once("boardFunctions.php"); // Načtení naší logovací funkce

if (empty($_SESSION['username'])) die("Nepovolený přístup.");

$id = intval($_POST['id'] ?? 0);
$priorita = intval($_POST['priorita'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE pozadavky SET priorita = ? WHERE id = ?");
    $stmt->bind_param("ii", $priorita, $id);

    if ($stmt->execute()) {
        // ZÁPIS DO UNIFIED TIMELINE
        $prio_text = ($priorita == 1) ? 'Nastaveno na URGENTNÍ' : 'Vráceno na Normální';
        zapis_do_historie($conn, $id, 0, 'urgence', "Změna priority: $prio_text", '', (string)$priorita);

        @file_put_contents('last_change.txt', time());
        echo "OK";
    } else {
        echo "Chyba DB: " . $conn->error;
    }
} else {
    echo "Chyba: Chybí ID.";
}
?>