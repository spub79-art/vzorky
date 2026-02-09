<?php
include_once("../db_connect.php");

// Kontrola, zda máme ID a název tabulky
if (isset($_GET['id']) && isset($_GET['table'])) {
    $id = (int)$_GET['id']; // Přetypování na int pro bezpečnost
    $table = mysqli_real_escape_string($conn, $_GET['table']);

    // Seznam povolených tabulek (bezpečnostní pojistka)
    $allowed_tables = ['pozadavky', 'zakaznik', 'suroviny'];

    if (in_array($table, $allowed_tables)) {

        // Pokud jde o požadavky, můžeme zde volitelně přidat kontrolu data
        // (aby nešlo smazat starý záznam přímým voláním URL)

        $sql = "DELETE FROM $table WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            // Úspěch - přesměrování zpět s parametrem pro zobrazení zprávy
            header("Location: ../index.php?Pozadavek=1&msg=deleted");
            exit();
        } else {
            die("Chyba při mazání: " . mysqli_error($conn));
        }
    } else {
        die("Nepovolená tabulka.");
    }
} else {
    die("Chybějící parametry.");
}
?>