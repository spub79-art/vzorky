<?php
include_once("db_connect.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_nabidka = mysqli_real_escape_string($conn, $_POST['id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $poznamka   = mysqli_real_escape_string($conn, $_POST['poznamka']);
    $qty        = mysqli_real_escape_string($conn, $_POST['qty']);

    // 1. Najdeme ID hlavního požadavku
    $res_p = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_nabidky WHERE id = '$id_nabidka'");
    $row_p = mysqli_fetch_assoc($res_p);
    $id_pozadavek = $row_p['id_pozadavek'];

    if (!$id_pozadavek) { echo "Chyba: Požadavek nenalezen"; exit; }

    // 2. Update nabídky
    if ($new_status !== 'no_change') {
        $sql = "UPDATE pozadavky_nabidky SET 
                id_status = '$new_status', 
                poznamka_vzorek = CONCAT(IFNULL(poznamka_vzorek,''), '\n', '$poznamka'),
                vzorek_dorazil = CASE WHEN '$qty' != '' THEN '$qty' ELSE vzorek_dorazil END,
                updated_at = NOW() 
                WHERE id = '$id_nabidka'";
    } else {
        $sql = "UPDATE pozadavky_nabidky SET 
                poznamka_vzorek = CONCAT(IFNULL(poznamka_vzorek,''), '\n', '$poznamka'),
                updated_at = NOW() 
                WHERE id = '$id_nabidka'";
    }
    mysqli_query($conn, $sql);

    // 3. SYNCHRONIZACE: Posuneme hlavní požadavek do správné fáze (sloupce)
    $main_st = 1; // Výchozí: Fáze 1 (Cena)
    if (in_array($new_status, [3, 8, 9])) $main_st = 3;  // Posun do Fáze 2 (Dokumenty)
    if ($new_status == 10)               $main_st = 10; // Posun do Fáze 3 (Testování)
    if ($new_status == 11)               $main_st = 11; // Archivace

    if ($new_status !== 'no_change') {
        mysqli_query($conn, "UPDATE pozadavky SET id_status = '$main_st' WHERE id = '$id_pozadavek'");
    }

    echo "OK";
}