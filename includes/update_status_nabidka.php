<?php
ob_start();
session_start();
include("db_connect.php");

$id = intval($_POST['id']); // ID nabídky (pozadavky_nabidky)
$status = $_POST['status'];
$poznamka = mysqli_real_escape_string($conn, $_POST['poznamka']);
$qty = isset($_POST['qty']) ? mysqli_real_escape_string($conn, $_POST['qty']) : '';
$sarze = isset($_POST['sarze']) ? mysqli_real_escape_string($conn, $_POST['sarze']) : '';

// NOVÉ: Kódy pro Informační systém (přichází při statusu 6)
$skupzbo = isset($_POST['skupzbo']) ? mysqli_real_escape_string($conn, $_POST['skupzbo']) : '';
$regcis = isset($_POST['regcis']) ? mysqli_real_escape_string($conn, $_POST['regcis']) : '';

if ($id > 0) {
    // Nejdříve zjistíme vazby: ID požadavku a následně ID suroviny
    $res_info = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_nabidky WHERE id = $id");
    $row_info = mysqli_fetch_assoc($res_info);
    $id_pozadavek = $row_info['id_pozadavek'];

    $id_surovina = 0;
    if ($id_pozadavek) {
        $res_sur = mysqli_query($conn, "SELECT id_surovina FROM pozadavky WHERE id = $id_pozadavek");
        $row_sur = mysqli_fetch_assoc($res_sur);
        $id_surovina = $row_sur['id_surovina'];
    }

    // 1. AKTUALIZACE NABÍDKY
    if ($status === 'no_change') {
        $sql = "UPDATE pozadavky_nabidky SET 
                poznamka_cena = '$poznamka', 
                sarze = '$sarze',
                updated_at = NOW() 
                WHERE id = $id";
    } elseif ($status == 9) {
        // Při vyžádání doplnění dokumentace
        $sql = "UPDATE pozadavky_nabidky SET 
                id_status = 9, 
                poznamka_cena = '$poznamka', 
                updated_at = NOW() WHERE id = $id";
    } else {
        // Standardní změna stavu (včetně posunu zpět na 8 pro velký vzorek nebo finálního 6)
        $sql = "UPDATE pozadavky_nabidky SET 
                id_status = " . intval($status) . ", 
                poznamka_cena = '$poznamka', 
                pozadovane_mnozstvi = IF('$qty' != '', '$qty', pozadovane_mnozstvi),
                sarze = IF('$sarze' != '', '$sarze', sarze),
                updated_at = NOW() 
                WHERE id = $id";
    }
    mysqli_query($conn, $sql);

    // 2. ZÁPIS KÓDŮ DO IS (Jen při finálním schválení - status 6)
    if (intval($status) == 6 && $id_surovina > 0) {
        mysqli_query($conn, "UPDATE suroviny SET 
                             skupzbo = '$skupzbo', 
                             regcis = '$regcis' 
                             WHERE id = $id_surovina");
    }

    // 3. SYNCHRONIZACE HLAVNÍHO STATUSU POŽADAVKU
    $s = (int)$status;
    $new_main_status = null;

    // Fáze 2: Dokumenty a objednávání (včetně re-objednávky velkého vzorku)
    if (in_array($s, [3, 8, 9, 11])) {
        $new_main_status = 3;
    }
    // Fáze 3: Testování
    elseif (in_array($s, [10, 4])) {
        $new_main_status = 10;
    }
    // Hotovo: Surovina schválena pro výrobu
    elseif ($s == 6) {
        $new_main_status = 6;
    }

    if ($new_main_status !== null && $id_pozadavek) {
        mysqli_query($conn, "UPDATE pozadavky SET id_status = $new_main_status WHERE id = $id_pozadavek");
    }

    // Zápis času poslední změny pro auto-refresh ostatních uživatelů
    file_put_contents('last_change.txt', time());
    echo "OK";
}
ob_end_flush();
?>