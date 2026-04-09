<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['orders']) && empty($_SESSION['adm'])) die("Nepovolený přístup.");

$id_pozadavek = (int)$_POST['id_pozadavek'];
$dodavatel_raw = trim($_POST['dodavatel_raw']);
$cena_clean = floatval(str_replace(',', '.', $_POST['cena'] ?? '0'));
$mena = $_POST['mena'] ?? 'CZK'; // CHYTÁME MĚNU

$moq_raw = trim($_POST['moq_qty'] ?? '');
$moq_qty = ($moq_raw !== '') ? floatval(str_replace(',', '.', $moq_raw)) : null;
$moq_mj = trim($_POST['moq_mj'] ?? 'kg');

$poznamka_vstup = trim($_POST['poznamka_nakup'] ?? '');
$user_id = !empty($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
$user_name = $_SESSION['username'] ?? 'Neznámý';
$datum = date("j.n. H:i");

$finalni_poznamka = !empty($poznamka_vstup) ? "[Nákup - {$user_name} - {$datum}]: {$poznamka_vstup}" : "";

// Dodavatel
$id_dodavatel = 0;
$stmt = $conn->prepare("SELECT id FROM dodavatele WHERE nazev = ?");
$stmt->bind_param("s", $dodavatel_raw);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $id_dodavatel = $row['id'];
} else {
    $stmt_ins = $conn->prepare("INSERT INTO dodavatele (nazev) VALUES (?)");
    $stmt_ins->bind_param("s", $dodavatel_raw);
    $stmt_ins->execute();
    $id_dodavatel = $conn->insert_id;
}

// SQL OPRAVENO: dynamická měna místo 'CZK' a 8 parametrů místo 7
$sql = "INSERT INTO pozadavky_nabidky 
        (id_pozadavek, id_dodavatel, cena_nabidka, moq_mnozstvi, moq_mj, mena, id_status, id_user_posledni_zmena, poznamka_nakup) 
        VALUES (?, ?, ?, ?, ?, ?, 2, ?, ?)";

$stmt_off = $conn->prepare($sql);
$stmt_off->bind_param("iiddssis", $id_pozadavek, $id_dodavatel, $cena_clean, $moq_qty, $moq_mj, $mena, $user_id, $finalni_poznamka);

if ($stmt_off->execute()) {

    // --- START: CHYTRÉ TELEGRAM NOTIFIKACE (Nová nabídka) ---
    include_once("telegram.php");
    $new_offer_id = $conn->insert_id;

    $sur_nazev = "Neznámá surovina";
    $q_sur = mysqli_query($conn, "SELECT s.nazev FROM pozadavky p JOIN suroviny s ON p.id_surovina = s.id WHERE p.id = $id_pozadavek");
    if ($q_sur && $r_sur = mysqli_fetch_assoc($q_sur)) {
        $sur_nazev = $r_sur['nazev'];
    }

    $kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo z nákupu');
    $cena_formatovana = number_format($cena_clean, (floor($cena_clean) == $cena_clean ? 0 : 2), ',', ' ') . " " . $mena;

    // BEZPEČNÉ HTML FORMÁTOVÁNÍ
    $msg = "💰 <b>VÝVOJ: Byla přidána nová nabídka!</b>\n\n";
    $msg .= "📌 <b>ID:</b> Požadavek #$id_pozadavek | Nabídka #$new_offer_id\n";
    $msg .= "<b>Surovina:</b> " . htmlspecialchars($sur_nazev) . "\n";
    $msg .= "<b>Dodavatel:</b> " . htmlspecialchars($dodavatel_raw) . "\n";
    $msg .= "<b>Cena:</b> " . $cena_formatovana . "\n";
    if ($moq_qty > 0) {
        $msg .= "<b>MOQ:</b> $moq_qty " . htmlspecialchars($moq_mj) . "\n";
    }
    $msg .= "<b>Přidal/a:</b> " . htmlspecialchars($kdo) . "\n";
    $msg .= "\n<i>Čeká se na schválení ceny (Tlačítko CENA OK).</i>";

    // AUTOMATICKÁ DETEKCE DOMÉNY A PŘIDÁNÍ KLIKACÍHO TEXTU
    $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $id_pozadavek;

    $msg .= "\n\n👉 <a href='" . $link . "'>Zobrazit nabídku v systému</a>";

    sendTelegram($msg, 'vyvoj');
    // --- KONEC: CHYTRÉ TELEGRAM NOTIFIKACE ---

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba: " . $stmt_off->error;
}
?>