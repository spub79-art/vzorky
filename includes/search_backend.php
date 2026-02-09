<?php
include_once("./db_connect.php");

$term = isset($_GET['term']) ? mysqli_real_escape_string($conn, $_GET['term']) : '';
$table = isset($_GET['table']) ? mysqli_real_escape_string($conn, $_GET['table']) : '';

$allowed_tables = ['zakaznik', 'stroje', 'folie', 'suroviny'];

if (!in_array($table, $allowed_tables)) {
    die("");
}

if (!empty($term)) {
    // Trik pro ignorování diakritiky: Převedeme oba porovnávané řetězce do ASCII
    // a nahradíme znaky s háčky/čárkami jejich základem.
    // Pro MySQL je nejjednodušší použít porovnávání (Collation) 'utf8mb4_general_ci'
    // nebo 'utf8_general_ci', které diakritiku při hledání ignoruje samo o sobě.

    if ($table === 'suroviny') {
        $query = "SELECT DISTINCT nazev FROM (
                    SELECT nazev FROM suroviny 
                    WHERE nazev COLLATE utf8mb4_general_ci LIKE '%$term%'
                    UNION
                    SELECT nazev FROM pozadavky 
                    WHERE nazev COLLATE utf8mb4_general_ci LIKE '%$term%'
                  ) AS kombinace 
                  ORDER BY nazev ASC 
                  LIMIT 10";
    } else {
        $query = "SELECT nazev FROM $table 
                  WHERE nazev COLLATE utf8mb4_general_ci LIKE '%$term%' 
                  ORDER BY nazev ASC 
                  LIMIT 10";
    }

    $res = mysqli_query($conn, $query);

    if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            $name = htmlspecialchars($row['nazev']);
            echo "<a href='#' class='list-group-item list-group-item-action'>{$name}</a>";
        }
    } else {
        echo "<div class='list-group-item text-muted small'>Nová položka: '" . htmlspecialchars($term) . "'</div>";
    }
}
?>