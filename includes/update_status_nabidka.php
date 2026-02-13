<?php
session_start();
include("db_connect.php"); // Předpokládám, že db_connect je ve stejné složce (includes)

if (empty($_SESSION['vyvoj']) && empty($_SESSION['adm'])) {
    die("Nepovolený přístup.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $status = (int)$_POST['status'];

    if ($id > 0 && $status > 0) {
        $sql = "UPDATE pozadavky_nabidky SET id_status = $status WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            // Pokud schválil vývoj (status 3), posuneme i hlavní požadavek
            if ($status === 3) {
                $res = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_nabidky WHERE id = $id");
                $row = mysqli_fetch_assoc($res);
                if ($row) {
                    mysqli_query($conn, "UPDATE pozadavky SET id_status = 3 WHERE id = " . (int)$row['id_pozadavek']);
                }
            }
            echo "OK";
        } else {
            echo "Chyba DB: " . mysqli_error($conn);
        }
    } else {
        echo "Chybné ID nebo Status.";
    }
}