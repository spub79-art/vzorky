<?php
// Načtení hlavního configu (ať už skript voláme z indexu, nebo přímo přes AJAX)
require_once(__DIR__ . '/../config/config.php');

// Připojení k databázi pomocí konstant z configu
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Kontrola připojení
if (!$conn) {
    if (IS_DEV) {
        die("Kritická chyba (DEV): Nepodařilo se připojit k vývojové databázi. Detail: " . mysqli_connect_error());
    } else {
        die("Omlouváme se, systém je dočasně nedostupný z důvodu údržby databáze.");
    }
}

// Nastavení správného kódování
mysqli_set_charset($conn, "utf8mb4");
?>