<?php
include("db_connect.php");
session_start();
$uid = $_SESSION['uid'] ?? 0;

if ($uid > 0) {
    // Vložíme do tabulky všechna ID aktualit, která tam ještě nejsou pro tohoto uživatele
    mysqli_query($conn, "INSERT IGNORE INTO aktuality_cteni (id_uzivatel, id_aktualita) 
                         SELECT $uid, id FROM aktuality");
    echo "OK";
}
?>