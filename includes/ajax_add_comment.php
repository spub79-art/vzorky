<?php
include_once("db_connect.php");
include_once("telegram.php");
include_once("boardFunctions.php"); // NOVÉ: Načtení logovací funkce
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['uid'])) die("Nepovolený přístup.");

$id_entity = (int)$_POST['id_entity'];
$typ_entity = in_array($_POST['typ_entity'], ['pozadavek', 'nabidka']) ? $_POST['typ_entity'] : 'pozadavek';
$text = trim($_POST['text']);
$uid = (int)$_SESSION['uid'];
$is_urgent = (isset($_POST['is_urgent']) && $_POST['is_urgent'] == 1);
$zaznam_typ = $is_urgent ? 'komentar_urgentni' : 'komentar';

$jmeno = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Neznámý');

if ($id_entity > 0 && !empty($text)) {

    // Zjištění správných ID pro logování
    $id_pozadavek = 0;
    $id_nabidka = 0;

    if ($typ_entity == 'pozadavek') {
        $id_pozadavek = $id_entity;
    } else {
        $id_nabidka = $id_entity;
        $q_req = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_nabidky WHERE id = $id_nabidka");
        if ($r = mysqli_fetch_assoc($q_req)) {
            $id_pozadavek = $r['id_pozadavek'];
        }
    }

    // ========================================================
    // NOVÉ: Zápis do sjednocené historie (Nahrazuje board_poznamky)
    // ========================================================
    zapis_do_historie($conn, $id_pozadavek, $id_nabidka, $zaznam_typ, $text);

    // --- START: TELEGRAM NOTIFIKACE ---
    $msg = "";
    $channel = 'vyvoj';
    $req_id = 0;

    if ($typ_entity == 'pozadavek') {
        $req_id = $id_entity;
        $q = mysqli_query($conn, "SELECT s.nazev FROM pozadavky p JOIN suroviny s ON p.id_surovina = s.id WHERE p.id = $id_entity");
        $row = mysqli_fetch_assoc($q);
        $sur_nazev = $row['nazev'] ?? 'Neznámá surovina';

        $msg = "💬 <b>Nový komentář k zadání</b>\n";
        $msg .= "📌 <b>Surovina:</b> " . htmlspecialchars($sur_nazev) . " (#$id_entity)\n";
    } else {
        $q = mysqli_query($conn, "SELECT d.nazev as dod, s.nazev as sur, p.id as req_id 
                                  FROM pozadavky_nabidky pn 
                                  JOIN pozadavky p ON pn.id_pozadavek = p.id 
                                  JOIN suroviny s ON p.id_surovina = s.id 
                                  LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id 
                                  WHERE pn.id = $id_entity");
        $row = mysqli_fetch_assoc($q);
        $req_id = $row['req_id'] ?? 0;
        $sur_nazev = $row['sur'] ?? 'Neznámá surovina';
        $dod_nazev = $row['dod'] ?? 'Neznámý dodavatel';

        $msg = "💬 <b>Nový komentář k nabídce</b>\n";
        $msg .= "📌 <b>Surovina:</b> " . htmlspecialchars($sur_nazev) . " (Požadavek #$req_id)\n";
        $msg .= "🏢 <b>Dodavatel:</b> " . htmlspecialchars($dod_nazev) . "\n";
    }

    $msg .= "👤 <b>Autor:</b> " . htmlspecialchars($jmeno) . "\n";
    $msg .= "📝 <i>\"" . htmlspecialchars($text) . "\"</i>";

    $is_dev = (defined('IS_DEV') && IS_DEV) || (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $msg .= "\n\n👉 <a href='".$base_url."/index.php?Pozadavek=1&req_id=".$req_id."'>Otevřít diskuzi</a>";

    sendTelegram($msg, $channel);
    // --- KONEC: TELEGRAM NOTIFIKACE ---

    @file_put_contents('last_change.txt', time());
    echo "OK";

} else {
    echo "Chyba: Prázdný text nebo ID.";
}
?>