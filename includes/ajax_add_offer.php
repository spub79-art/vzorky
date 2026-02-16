<?php
include_once("db_connect.php");
session_start();

// Kontrola práv
if (empty($_SESSION['orders']) && empty($_SESSION['adm'])) {
    die("Nepovolený přístup");
}

$id_pozadavek = intval($_POST['id_pozadavek']);
$id_dodavatel = intval($_POST['id_dodavatel']);
$cena = floatval(str_replace(',', '.', $_POST['cena']));
$mena = "CZK"; // Vždy CZK
$status = 2;   // Status 2 = Nová nabídka (čeká na Vývoj)

if ($id_pozadavek > 0 && $id_dodavatel > 0) {
    // Používáme váš sloupec datum_poptavky místo created_at
    $sql = "INSERT INTO pozadavky_nabidky (id_pozadavek, id_dodavatel, cena_nabidka, mena, id_status, datum_poptavky) 
            VALUES ($id_pozadavek, $id_dodavatel, $cena, '$mena', $status, NOW())";

    if (mysqli_query($conn, $sql)) {
        echo "OK";
    } else {
        echo "Chyba DB: " . mysqli_error($conn);
    }
} else {
    echo "Vyberte prosím dodavatele a zadejte cenu.";
}
?>