<?php
include_once("db_connect.php");
session_start();

if (empty($_SESSION['username'])) die("Nepřihlášen");

$sur_raw = $_POST['id_surovina'];
$poznamka = mysqli_real_escape_string($conn, $_POST['poznamka']);
$priorita = intval($_POST['priorita']);
$bio = !empty($_POST['bio']) ? 1 : 0;
$vegan = !empty($_POST['vegan']) ? 1 : 0;
$bezlepek = !empty($_POST['bezlepek']) ? 1 : 0;
$kosher = !empty($_POST['kosher']) ? 1 : 0;
$halal    = intval($_POST['halal']);

// LOGIKA PRO NOVOU SUROVINU
if (!is_numeric($sur_raw)) {
    $sur_name = mysqli_real_escape_string($conn, $sur_raw);
    mysqli_query($conn, "INSERT INTO suroviny (nazev) VALUES ('$sur_name')");
    $id_surovina = mysqli_insert_id($conn);
} else {
    $id_surovina = intval($sur_raw);
}

if ($id_surovina == 0) die("Chyba suroviny");

$sql = "INSERT INTO pozadavky (id_surovina, id_status, bio, vegan, bezlepek, kosher, halal, priorita, poznamka, datumPozadavek) 
        VALUES ($id_surovina, 1, $bio, $vegan, $bezlepek, $kosher, $halal, $priorita, '$poznamka', NOW())";

if (mysqli_query($conn, $sql)) {
    file_put_contents('../last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba: " . mysqli_error($conn);
}