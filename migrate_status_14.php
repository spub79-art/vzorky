<?php
// migrate_status_14.php — jednorázově spustit v prohlížeči po nasazení
include_once("includes/db_connect.php");

$nazev = 'Dokumentace bez ceny';
$barva = '#8e44ad';

$check = mysqli_query($conn, "SELECT id FROM ciselnik_statusu WHERE id = 14");
if ($check && mysqli_num_rows($check) > 0) {
    mysqli_query($conn, "UPDATE ciselnik_statusu SET nazev = '$nazev', barva_hex = '$barva' WHERE id = 14");
    echo "Status 14 už existuje — aktualizován název a barva.";
} else {
    mysqli_query($conn, "INSERT INTO ciselnik_statusu (id, nazev, barva_hex) VALUES (14, '$nazev', '$barva')");
    echo "Status 14 „$nazev“ byl přidán do číselníku.";
}
