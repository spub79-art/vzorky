<?php
session_start();
include("db_connect.php");

if (empty($_SESSION['vyvoj']) && empty($_SESSION['adm']) && empty($_SESSION['orders']) && empty($_SESSION['kvalita'])) {
    die("Nepovolený přístup.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];
    $poznamka = (isset($_POST['poznamka']) && $_POST['poznamka'] !== 'undefined') ? mysqli_real_escape_string($conn, $_POST['poznamka']) : '';
    $datum_objednani = isset($_POST['datum_objednani']) ? mysqli_real_escape_string($conn, $_POST['datum_objednani']) : null;

    if ($id > 0 && $status > 0) {
        if ($status === 4) {
            $sql = "UPDATE pozadavky_nabidky SET id_status = 4, vzorek_objednan = '$datum_objednani', poznamka_vzorek = '$poznamka', updated_at = NOW() WHERE id = $id";
        } elseif ($status === 7) {
            $sql = "UPDATE pozadavky_nabidky SET id_status = 7, poznamka_cena = '$poznamka', updated_at = NOW() WHERE id = $id";
        } else {
            $sql = "UPDATE pozadavky_nabidky SET id_status = $status, poznamka_vzorek = '$poznamka', updated_at = NOW() WHERE id = $id";
        }

        if (mysqli_query($conn, $sql)) {
            $res = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_nabidky WHERE id = $id");
            $row = mysqli_fetch_assoc($res);
            $id_hlavni = $row ? (int)$row['id_pozadavek'] : 0;

            if ($id_hlavni > 0) {
                if (in_array($status, [3, 4, 8, 6])) {
                    mysqli_query($conn, "UPDATE pozadavky SET id_status = $status WHERE id = $id_hlavni");
                }
            }
            echo "OK";
        } else {
            echo "Chyba DB: " . mysqli_error($conn);
        }
    }
}