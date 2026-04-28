<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['username'])) die("Nepřihlášen");

$data = isset($_POST['data']) ? $_POST['data'] : [];

if (empty($data) || !is_array($data)) {
    die("Žádná data k uložení.");
}

// Projdeme všechny upravené suroviny
foreach ($data as $id => $cols) {
    $id = (int)$id;
    if ($id <= 0) continue;

    $updates = [];
    $types = "";
    $params = [];

    // Zkontrolujeme, jestli se upravil Český název
    if (isset($cols['cz'])) {
        $updates[] = "nazev = ?";
        $types .= "s";
        $params[] = trim($cols['cz']);
    }

    // Zkontrolujeme, jestli se upravil Anglický název
    if (isset($cols['en'])) {
        $updates[] = "nazev_en = ?";
        $types .= "s";
        $params[] = trim($cols['en']);
    }

    // Pokud se reálně něco zapsalo, pošleme update do DB
    if (!empty($updates)) {
        $sql = "UPDATE suroviny SET " . implode(", ", $updates) . " WHERE id = ?";
        $types .= "i";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        // ...$params je moderní PHP fígl pro předání libovolného počtu argumentů z pole
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }
}

echo "OK";
?>