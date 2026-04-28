<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['username'])) die("Nepřihlášen");

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
$poznamka = trim($_POST['poznamka'] ?? '');

if ($id <= 0 || $status <= 0 || empty($poznamka)) die("Chybná data nebo chybí vysvětlení.");

$q = "UPDATE pozadavky SET id_status = $status WHERE id = $id";
if (mysqli_query($conn, $q)) {
    // 1. ZÁPIS DO HISTORIE
    $akce = ($status == 8) ? "Odloženo k ledu" : "Požadavek OŽIVEN";
    $text_historie = "⚙️ $akce: $poznamka";

    $uid = $_SESSION['uid'] ?? 0;
    $uname = is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username'];

    mysqli_query($conn, "INSERT INTO historie_pozadavku (id_pozadavek, id_nabidka, id_user, jmeno_user, text_hodnota, typ_zaznamu) 
                         VALUES ($id, 0, $uid, '$uname', '$text_historie', 'status')");

    // 2. TELEGRAM NOTIFIKACE
    include_once("telegram.php");
    $q_sur = mysqli_query($conn, "SELECT s.nazev FROM pozadavky p JOIN suroviny s ON p.id_surovina = s.id WHERE p.id = $id");
    $sur_nazev = ($r_sur = mysqli_fetch_assoc($q_sur)) ? $r_sur['nazev'] : "Neznámá surovina";

    $msg = ($status == 8) ? "🧊 <b>POŽADAVEK ODLOŽEN K LEDU</b>\n\n" : "🔥 <b>POŽADAVEK OŽIVEN</b>\n\n";
    $msg .= "<b>Surovina:</b> " . htmlspecialchars($sur_nazev) . "\n";
    $msg .= "<b>Zadal/a:</b> " . htmlspecialchars($uname) . "\n";
    $msg .= "<b>Důvod/Komentář:</b> " . htmlspecialchars($poznamka) . "\n";

    $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $link = "\n👉 <a href='" . $base_url . "/index.php?Pozadavek=1&req_id=" . $id . "'>Otevřít detail</a>";

    // Oznámíme Nákupu a Vývoji (ti jsou tam vždycky)
    sendTelegram($msg . $link, 'nakup');
    sendTelegram($msg . $link, 'vyvoj');

    // Pokud už byla nabídka v kontrole u Kvality (status 12 a vyšší), dáme jim taky vědět
    $q_st = mysqli_query($conn, "SELECT MAX(id_status) as max_st FROM pozadavky_nabidky WHERE id_pozadavek = $id");
    if ($r_st = mysqli_fetch_assoc($q_st)) {
        if ($r_st['max_st'] >= 12) sendTelegram($msg . $link, 'kvalita');
    }

    echo "OK";
} else {
    echo "Chyba databáze: " . mysqli_error($conn);
}
?>