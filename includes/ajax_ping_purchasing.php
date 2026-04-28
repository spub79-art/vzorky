<?php
session_start();
if (empty($_SESSION['username'])) die("Nepovolený přístup.");

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) die("Chyba ID.");

include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("telegram.php");

// Zápis do historie
zapis_do_historie($conn, $id, 0, 'urgence', "Uživatel vyžádal dohledání další alternativy/nabídky.");

// Sestavení a odeslání Telegram zprávy
$kdo = htmlspecialchars(is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username']);
$sur = htmlspecialchars(trim($_POST['surovina'] ?? 'Neznámá'));
$url = "https://docs.lifefood.eu/" . (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') ? "dev-vzorky" : "vzorky");

$msg = "🔔 <b>VÝVOJ/KVALITA: Žádost o další nabídku!</b>\n\n<b>Surovina:</b> $sur\n<b>Vyžádal/a:</b> $kdo\n\nByly zamítnuty všechny dosavadní možnosti, nebo je potřeba další alternativa.\n👉 <a href='$url/index.php?Pozadavek=1&req_id=$id'>Otevřít detail</a>";

sendTelegram($msg, 'nakup');
@file_put_contents('last_change.txt', time());

echo "OK";
?><?php
session_start();
if (empty($_SESSION['username'])) die("Nepovolený přístup.");

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) die("Chyba ID.");

include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("telegram.php");

// Zápis do historie
zapis_do_historie($conn, $id, 0, 'urgence', "Uživatel vyžádal dohledání další alternativy/nabídky.");

// Sestavení a odeslání Telegram zprávy
$kdo = htmlspecialchars(is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username']);
$sur = htmlspecialchars(trim($_POST['surovina'] ?? 'Neznámá'));
$url = "https://docs.lifefood.eu/" . (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') ? "dev-vzorky" : "vzorky");

$msg = "🔔 <b>VÝVOJ/KVALITA: Žádost o další nabídku!</b>\n\n<b>Surovina:</b> $sur\n<b>Vyžádal/a:</b> $kdo\n\nByly zamítnuty všechny dosavadní možnosti, nebo je potřeba další alternativa.\n👉 <a href='$url/index.php?Pozadavek=1&req_id=$id'>Otevřít detail</a>";

sendTelegram($msg, 'nakup');
@file_put_contents('last_change.txt', time());

echo "OK";
?>