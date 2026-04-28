<?php
session_start();
include_once("db_connect.php");
include_once("boardFunctions.php"); // Načtení naší logovací funkce

if (empty($_SESSION['username'])) die("Nepovolený přístup.");

$id_pozadavek = intval($_POST['id'] ?? 0);
$surovina = trim($_POST['surovina'] ?? 'Neznámá surovina');
$kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo');

if ($id_pozadavek > 0) {

    // Zjistíme fázi a kdo to brzdí
    $q = mysqli_query($conn, "SELECT MAX(id_status) as max_st FROM pozadavky_nabidky WHERE id_pozadavek = $id_pozadavek AND id_status NOT IN (5, 7)");
    $r = mysqli_fetch_assoc($q);
    $max_st = intval($r['max_st'] ?? 0);

    $cilova_skupina = 'nakup';
    $komu_zprava = "NÁKUPU";

    if ($max_st == 12) {
        $cilova_skupina = 'kvalita';
        $komu_zprava = "KVALITĚ";
    } elseif (in_array($max_st, [4, 10, 13])) {
        $cilova_skupina = 'vyvoj';
        $komu_zprava = "VÝVOJI";
    }

    // ZÁPIS DO UNIFIED TIMELINE
    zapis_do_historie($conn, $id_pozadavek, 0, 'urgence', "Uživatel urgoval řešení (upozorněna skupina: $komu_zprava).");

    // Odeslání Telegramu
    include_once("telegram.php");
    $msg = "⚡ <b>URGENCE POŽADAVKU!</b>\n\n<b>Surovina:</b> " . htmlspecialchars($surovina) . "\n<b>Urguje:</b> " . htmlspecialchars($kdo) . "\n\nTento požadavek čeká na vaši akci. Prosím, podívejte se na to.";

    $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $id_pozadavek;
    $msg .= "\n👉 <a href='" . $link . "'>Otevřít detail</a>";

    sendTelegram($msg, $cilova_skupina);

    @file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba ID.";
}
?>