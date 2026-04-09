<?php
session_start();
include_once("db_connect.php");

if (empty($_SESSION['username'])) die("Nepovolený přístup.");

$id_pozadavek = intval($_POST['id'] ?? 0);
$surovina = trim($_POST['surovina'] ?? 'Neznámá surovina');
$kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo');

if ($id_pozadavek > 0) {

    // Zjistíme fázi a kdo to brzdí (podle maximálního aktivního statusu nabídky)
    $q = mysqli_query($conn, "SELECT MAX(id_status) as max_st FROM pozadavky_nabidky WHERE id_pozadavek = $id_pozadavek AND id_status NOT IN (5, 7)");
    $r = mysqli_fetch_assoc($q);
    $max_st = intval($r['max_st']);

    // Detekce zpožděné skupiny
    $cilova_skupina = 'nakup';
    $komu_zprava = "NÁKUPU";

    if ($max_st == 12) {
        $cilova_skupina = 'kvalita'; // 12 = Čeká na Kvalitu
        $komu_zprava = "KVALITĚ";
    } elseif (in_array($max_st, [4, 10, 13])) {
        $cilova_skupina = 'vyvoj'; // Testování / Nutriční (Vývoj)
        $komu_zprava = "VÝVOJI";
    }

    // 1. Zápis do poznámek, ať to na sebe žaluje
    $text_poznamky = "⚡ Uživatel urgoval řešení tohoto požadavku (upozorněna skupina: $komu_zprava).";
    $uid = $_SESSION['uid'] ?? 0;

    $stmt = $conn->prepare("INSERT INTO board_poznamky (typ_entity, id_entity, text_poznamky, id_user, autor_jmeno) VALUES ('pozadavek', ?, ?, ?, ?)");
    $stmt->bind_param("isis", $id_pozadavek, $text_poznamky, $uid, $kdo);
    $stmt->execute();

    // 2. Odeslání cíleného Telegramu
    include_once("telegram.php");

    $msg = "⚡ <b>URGENCE POŽADAVKU!</b>\n\n";
    $msg .= "<b>Surovina:</b> " . htmlspecialchars($surovina) . "\n";
    $msg .= "<b>Urguje:</b> " . htmlspecialchars($kdo) . "\n\n";
    $msg .= "Tento požadavek čeká na vaši akci. Prosím, podívejte se na to.";

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