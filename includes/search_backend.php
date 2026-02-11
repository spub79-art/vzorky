<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (file_exists("db_connect.php")) {
    include_once("db_connect.php");
} elseif (file_exists("../db_connect.php")) {
    include_once("../db_connect.php");
} else {
    die(json_encode(["error" => "db_connect.php nenalezen"]));
}

mysqli_set_charset($conn, "utf8mb4");

$term = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : '';
$table = isset($_GET['table']) ? mysqli_real_escape_string($conn, $_GET['table']) : '';

$allowed_tables = ['zakaznik', 'stroje', 'folie', 'suroviny'];

if (!in_array($table, $allowed_tables) || empty($term)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// JEDNODUCHÁ LOGIKA: Hledáme už jen v číselnících,
// protože v pozadavcích už textové názvy nemáme.
$query = "SELECT id, nazev FROM `$table` 
          WHERE nazev LIKE '%$term%' 
          ORDER BY nazev ASC 
          LIMIT 10";

$res = mysqli_query($conn, $query);
$results = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $results[] = [
            'id'    => $row['id'],    // Skutečné ID z databáze
            'label' => $row['nazev'], // Text pro zobrazení v našeptávači
            'value' => $row['nazev']  // Text pro vložení do inputu
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($results);