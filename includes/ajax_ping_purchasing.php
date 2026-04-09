<?php
session_start();
include_once("db_connect.php");

if (empty($_SESSION['username'])) die("Nepovolený přístup.");

$id_pozadavek = intval($_POST['id'] ?? 0);
$surovina = trim($_POST['surovina'] ?? 'Neznámá surovina');
$kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo');

if ($id_pozadavek > 0) {

    // 1. Zápis do poznámek (historie požadavku)
    $text_poznamky = "🔔 Uživatel vyžádal dohledání další nabídky.";
    $uid = $_SESSION['uid'] ?? 0;

    $stmt = $conn->prepare("INSERT INTO board_poznamky (typ_entity, id_entity, text_poznamky, id_user, autor_jmeno) VALUES ('pozadavek', ?, ?, ?, ?)");
    $stmt->bind_param("isis", $id_pozadavek, $text_poznamky, $uid, $kdo);
    $stmt->execute();

    // 2. Odeslání Telegramu
    include_once("telegram.php");

    $msg = "🔔 <b>VÝVOJ/KVALITA: Žádost o další nabídku!</b>\n\n";
    $msg .= "<b>Surovina:</b> " . htmlspecialchars($surovina) . "\n";
    $msg .= "<b>Vyžádal/a:</b> " . htmlspecialchars($kdo) . "\n\n";
    $msg .= "Byly zamítnuty všechny dosavadní možnosti, nebo je potřeba další alternativa.";

    $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $id_pozadavek;
    $msg .= "\n👉 <a href='" . $link . "'>Otevřít detail</a>";

    sendTelegram($msg, 'nakup');

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba ID.";
}
?>