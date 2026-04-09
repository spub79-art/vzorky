<?php
session_start();
include_once("db_connect.php");

// Ochrana přístupu
if (empty($_SESSION['username'])) {
    die("Nepovolený přístup.");
}

$id = intval($_POST['id'] ?? 0);
$reason = trim($_POST['poznamka'] ?? '');

if ($id > 0 && !empty($reason)) {
    $user_name = $_SESSION['username'] ?? 'Neznámý';
    $datum = date("j.n. H:i");
    $audit_note = "\n[ZRUŠENO - {$user_name} - {$datum}]: {$reason}";

    // 1. Zrušíme hlavní požadavek (status 5 = Zamítnuto/Zrušeno)
    $stmt = $conn->prepare("UPDATE pozadavky SET id_status = 5, poznamka = CONCAT(IFNULL(poznamka,''), ?) WHERE id = ?");
    $stmt->bind_param("si", $audit_note, $id);

    if ($stmt->execute()) {
        // 2. Pro jistotu zrušíme i všechny navázané nabídky, které nebyly uzavřeny
        $stmt_off = $conn->prepare("UPDATE pozadavky_nabidky SET id_status = 5, poznamka_nakup = CONCAT(IFNULL(poznamka_nakup,''), ?) WHERE id_pozadavek = ? AND id_status != 5");
        $stmt_off->bind_param("si", $audit_note, $id);
        $stmt_off->execute();

        // --- TELEGRAM NOTIFIKACE O ZRUŠENÍ ---
        include_once("telegram.php");

        // Zjistíme název suroviny pro hezčí zprávu
        $sur_nazev = "Neznámá surovina";
        $q_sur = mysqli_query($conn, "SELECT s.nazev FROM pozadavky p JOIN suroviny s ON p.id_surovina = s.id WHERE p.id = $id");
        if ($q_sur && $r_sur = mysqli_fetch_assoc($q_sur)) {
            $sur_nazev = $r_sur['nazev'];
        }

        $kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo');

        $msg = "❌ <b>NÁKUP/VÝVOJ: Požadavek byl ZRUŠEN!</b>\n\n";
        $msg .= "<b>Surovina:</b> " . htmlspecialchars($sur_nazev) . "\n";
        $msg .= "<b>Zrušil/a:</b> " . htmlspecialchars($kdo) . "\n";
        $msg .= "<b>Důvod:</b> " . htmlspecialchars($reason) . "\n";

        $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
        $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
        $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $id;
        $msg .= "\n👉 <a href='" . $link . "'>Otevřít detail v systému</a>";

        sendTelegram($msg, 'nakup');
        sendTelegram($msg, 'vyvoj'); // Posláno oběma stranám pro info

        file_put_contents('last_change.txt', time());
        echo "OK";
    } else {
        echo "Chyba databáze: " . $stmt->error;
    }
} else {
    echo "Chyba: Neplatné ID nebo chybí důvod zrušení.";
}
?>