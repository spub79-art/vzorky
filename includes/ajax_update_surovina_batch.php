<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['username'])) die("Nepřihlášen");

// JS posílá data jako JSON string, musíme je rozkódovat do PHP pole
$json_data = isset($_POST['data']) ? $_POST['data'] : '';
$data = json_decode($json_data, true);

if (empty($data) || !is_array($data)) {
    die("Žádná data k uložení.");
}

$is_adm = !empty($_SESSION['adm']);

// Projdeme všechny suroviny z pole
foreach ($data as $item) {
    if (!isset($item['id'])) continue;

    $id = (int)$item['id'];
    if ($id <= 0) continue;

    $updates = [];
    $types = "";
    $params = [];

    // Český název (dovolíme do DB zapsat jen adminovi)
    if (isset($item['nazev']) && $is_adm) {
        $updates[] = "nazev = ?";
        $types .= "s";
        $params[] = trim($item['nazev']);
    }

    // Anglický název
    if (isset($item['nazev_en'])) {
        $updates[] = "nazev_en = ?";
        $types .= "s";
        $params[] = trim($item['nazev_en']);
    }

    // Informační systém - SkupZbo
    if (isset($item['skupzbo'])) {
        $updates[] = "skupzbo = ?";
        $types .= "s";
        $params[] = trim($item['skupzbo']);
    }

    // Informační systém - RegCis
    if (isset($item['regcis'])) {
        $updates[] = "regcis = ?";
        $types .= "s";
        $params[] = trim($item['regcis']);
    }

    // Pokud se reálně něco upravilo, pošleme update do DB
    if (!empty($updates)) {
        $sql = "UPDATE suroviny SET " . implode(", ", $updates) . " WHERE id = ?";
        $types .= "i";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
        }
    }
}

echo "OK";
?>