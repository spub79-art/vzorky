<?php
session_start();
if (empty($_SESSION['username'])) die("Nepovolený přístup.");

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) die("Chyba ID.");

include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("telegram.php");

$kdo = htmlspecialchars(is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username']);
$sur = htmlspecialchars(trim($_POST['surovina'] ?? 'Neznámá'));

// CHYTÁME POZNÁMKU Z NOVÉHO PROMPTU
$poznamka = htmlspecialchars(trim($_POST['poznamka'] ?? ''));

// 1. Sestavení textu pro historii na nástěnce
$hist_text = "Uživatel vyžádal dohledání další alternativy/nabídky.";
if (!empty($poznamka)) {
    $hist_text .= "\nPoznámka k dohledání: " . $poznamka;
}

// Zápis do historie
zapis_do_historie($conn, $id, 0, 'urgence', $hist_text);

// 2. Sestavení a odeslání Telegram zprávy
$url = "https://docs.lifefood.eu/" . (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') ? "dev-vzorky" : "vzorky");

$msg = "🔔 <b>VÝVOJ/KVALITA: Žádost o další nabídku!</b>\n\n<b>Surovina:</b> $sur\n<b>Vyžádal/a:</b> $kdo\n";

if (!empty($poznamka)) {
    // Pokud uživatel něco napsal, pošleme to do Telegramu
    $msg .= "\n💬 <b>Zpráva pro Nákup:</b>\n<i>$poznamka</i>\n";
} else {
    // Pokud to nechal prázdné, pošleme univerzální text
    $msg .= "\nByly zamítnuty všechny dosavadní možnosti, nebo je potřeba další alternativa.\n";
}

$msg .= "\n👉 <a href='$url/index.php?Pozadavek=1&req_id=$id'>Otevřít detail</a>";

sendTelegram($msg, 'nakup');
@file_put_contents('last_change.txt', time());

echo "OK";
?>