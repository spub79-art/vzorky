<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['orders']) && empty($_SESSION['adm'])) die("Nepovolený přístup.");

$id_nabidka = (int)$_POST['id_nabidka'];
$cena_clean = floatval(str_replace(',', '.', $_POST['cena'] ?? '0'));
$mena = $_POST['mena'] ?? 'CZK'; // PŘIDÁNO: CHYTÁME MĚNU
$moq_raw = trim($_POST['moq_qty'] ?? '');
$moq_qty = ($moq_raw !== '') ? floatval(str_replace(',', '.', $moq_raw)) : null;
$moq_mj = trim($_POST['moq_mj'] ?? 'kg');
$poznamka_vstup = trim($_POST['poznamka_nakup'] ?? '');
$dodavatel_raw = trim($_POST['dodavatel_raw'] ?? '');

$user_id = !empty($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
$user_name = $_SESSION['username'] ?? 'Neznámý';

// Auditní záznam
$audit_entry = "\n[OPRAVA - {$user_name} - " . date("j.n. H:i") . "]: " . ($poznamka_vstup ?: 'Změna parametrů (cena/MOQ/dodavatel) - vráceno k novému schválení.');

// Zajištění existence dodavatele, pokud ho Nákup přepsal
$id_dodavatel = 0;
if (!empty($dodavatel_raw)) {
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
}

// Podle toho, zda máme ID dodavatele, poskládáme SQL
if ($id_dodavatel > 0) {
    // 8 parametrů (iddsssii) - PŘIDÁNA MĚNA
    $sql = "UPDATE pozadavky_nabidky SET 
            id_dodavatel = ?,
            cena_nabidka = ?, 
            moq_mnozstvi = ?, 
            moq_mj = ?, 
            mena = ?,
            poznamka_nakup = CONCAT(IFNULL(poznamka_nakup, ''), ?),
            id_user_posledni_zmena = ?,
            id_status = 2 
            WHERE id = ? AND id_status IN (2, 3)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iddsssii", $id_dodavatel, $cena_clean, $moq_qty, $moq_mj, $mena, $audit_entry, $user_id, $id_nabidka);
} else {
    // 7 parametrů (ddsssii) - PŘIDÁNA MĚNA
    $sql = "UPDATE pozadavky_nabidky SET 
            cena_nabidka = ?, 
            moq_mnozstvi = ?, 
            moq_mj = ?, 
            mena = ?,
            poznamka_nakup = CONCAT(IFNULL(poznamka_nakup, ''), ?),
            id_user_posledni_zmena = ?,
            id_status = 2 
            WHERE id = ? AND id_status IN (2, 3)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ddsssii", $cena_clean, $moq_qty, $moq_mj, $mena, $audit_entry, $user_id, $id_nabidka);
}

if ($stmt->execute()) {
    // Zjistíme detaily pro notifikaci a update hlavního požadavku
    $q_info = mysqli_query($conn, "SELECT pn.id_pozadavek, s.nazev as sur_nazev, d.nazev as dod_nazev 
                                   FROM pozadavky_nabidky pn 
                                   JOIN pozadavky p ON pn.id_pozadavek = p.id 
                                   JOIN suroviny s ON p.id_surovina = s.id 
                                   LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
                                   WHERE pn.id = $id_nabidka");
    $id_pozadavek = 0;
    $sur_nazev = "Neznámá surovina";
    $dod_nazev = "Neznámý dodavatel";

    if ($q_info && $r_info = mysqli_fetch_assoc($q_info)) {
        $id_pozadavek = $r_info['id_pozadavek'];
        $sur_nazev = $r_info['sur_nazev'];
        $dod_nazev = $r_info['dod_nazev'] ? $r_info['dod_nazev'] : $dodavatel_raw;
    }

    if ($id_pozadavek > 0) {
        mysqli_query($conn, "UPDATE pozadavky SET id_status = 2 WHERE id = $id_pozadavek");
    }

    // --- START: CHYTRÉ TELEGRAM NOTIFIKACE (Úprava nabídky) ---
    include_once("telegram.php");

    $kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo z nákupu');
    $cena_formatovana = number_format($cena_clean, (floor($cena_clean) == $cena_clean ? 0 : 2), ',', ' ') . " " . $mena;

    $msg = "✏️ <b>VÝVOJ: Nabídka byla upravena a vrácena ke schválení!</b>\n\n";
    $msg .= "📌 <b>ID:</b> Požadavek #$id_pozadavek | Nabídka #$id_nabidka\n";
    $msg .= "<b>Surovina:</b> " . htmlspecialchars($sur_nazev) . "\n";
    $msg .= "<b>Dodavatel:</b> " . htmlspecialchars($dod_nazev) . "\n";
    $msg .= "<b>Nová cena:</b> " . $cena_formatovana . "\n";
    if ($moq_qty > 0) {
        $msg .= "<b>MOQ:</b> $moq_qty " . htmlspecialchars($moq_mj) . "\n";
    }
    $msg .= "<b>Upravil/a:</b> " . htmlspecialchars($kdo) . "\n";
    $msg .= "\n<i>Prosím o opětovné schválení ceny (Tlačítko CENA OK).</i>";

    $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $id_pozadavek;
    $msg .= "\n\n👉 <a href='" . $link . "'>Otevřít detail v systému</a>";

    sendTelegram($msg, 'vyvoj');
    // --- KONEC: CHYTRÉ TELEGRAM NOTIFIKACE ---

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba databáze: " . $stmt->error;
}
?>