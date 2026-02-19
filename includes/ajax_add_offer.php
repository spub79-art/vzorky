<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['orders']) && empty($_SESSION['adm'])) die("Chyba: Nepovolený přístup.");

$id_pozadavek = (int)$_POST['id_pozadavek'];
$dodavatel_raw = trim($_POST['dodavatel_raw']);
$cena_clean = floatval(str_replace(',', '.', $_POST['cena']));

if ($id_pozadavek === 0 || empty($dodavatel_raw)) die("Chyba dat");

// Zjištění/Vytvoření dodavatele
$id_dodavatel = 0;
if (is_numeric($dodavatel_raw)) {
    $id_dodavatel = (int)$dodavatel_raw;
} else {
    $stmt = $conn->prepare("SELECT id FROM dodavatele WHERE nazev = ?");
    $stmt->bind_param("s", $dodavatel_raw);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $id_dodavatel = $row['id'];
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO dodavatele (nazev) VALUES (?)");
        $stmt_ins->bind_param("s", $dodavatel_raw);
        $stmt_ins->execute();
        $id_dodavatel = $conn->insert_id;
    }
}

// Vložení nabídky
$sql = "INSERT INTO pozadavky_nabidky (id_pozadavek, id_dodavatel, cena_nabidka, mena, id_status) VALUES (?, ?, ?, 'CZK', 2)";
$stmt_off = $conn->prepare($sql);
$stmt_off->bind_param("iid", $id_pozadavek, $id_dodavatel, $cena_clean);

if ($stmt_off->execute()) {
    // SIGNÁL PRO REFRESH
    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba: " . $stmt_off->error;
}
?>