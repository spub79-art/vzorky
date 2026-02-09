<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Připojení k DB - skript je v includes/, db_connect v rootu
include_once("../db_connect.php");

if (isset($_GET['id']) && isset($_GET['table'])) {
    $id = (int)$_GET['id'];
    $table = mysqli_real_escape_string($conn, $_GET['table']);

    // Tvoje role z authLF.php
    $is_admin = !empty($_SESSION['adm']);

    $allowed_tables = ['pozadavky', 'zakaznik', 'users'];
    if (!in_array($table, $allowed_tables)) { die("Nepovolená tabulka."); }

    // Logika pro ZÁKAZNÍKY (admin + kontrola vazeb)
    if ($table === 'zakaznik') {
        if (!$is_admin) { die("Chyba: Pouze admin maže zákazníky."); }

        $check = mysqli_query($conn, "SELECT id FROM pozadavky WHERE id_zakaznik = $id LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            die("Chyba: Zákazník má aktivní požadavky.");
        }
    }

    // Logika pro POŽADAVKY (admin nebo dnes)
    if ($table === 'pozadavky') {
        $res = mysqli_query($conn, "SELECT datumPozadavek FROM pozadavky WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $isToday = (date('Y-m-d') === date('Y-m-d', strtotime($row['datumPozadavek'])));

        if (!$is_admin && !$isToday) {
            die("Chyba: Historii maže jen admin.");
        }
    }

    $sql = "DELETE FROM $table WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        echo "OK";
    } else {
        echo "Chyba DB: " . mysqli_error($conn);
    }
}