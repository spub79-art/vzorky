<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

// Vytáhneme CZ i EN název
$sql = "SELECT s.nazev AS surovina_cz, s.nazev_en AS surovina_en, 
               p.bio, p.vegan, p.bezlepek, p.kosher, p.halal
        FROM pozadavky p
        JOIN suroviny s ON p.id_surovina = s.id
        WHERE p.id_status NOT IN (5, 6, 7)
        ORDER BY s.nazev ASC";

$result = mysqli_query($conn, $sql);
$shanime_unikatni = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Priorita: 1. Anglický název, 2. Český název
    $display_name = !empty($row['surovina_en']) ? trim($row['surovina_en']) : trim($row['surovina_cz']);

    // Mezinárodní certifikáty
    $spec = [];
    if ($row['bio']) $spec[] = 'Organic';
    if ($row['vegan']) $spec[] = 'Vegan';
    if ($row['bezlepek']) $spec[] = 'Gluten-Free';
    if ($row['kosher']) $spec[] = 'Kosher';
    if ($row['halal']) $spec[] = 'Halal';

    $spec_str = empty($spec) ? "" : " (" . implode(', ', $spec) . ")";
    $polozka = "- " . $display_name . $spec_str;

    $shanime_unikatni[$polozka] = true;
}

if (empty($shanime_unikatni)) {
    echo "<div class='alert alert-success'><i class='glyphicon glyphicon-ok'></i> Žádné aktivní poptávky.</div>";
    exit;
}

$final_text = "Hello,\n\nWe are currently looking for suppliers for the following raw materials:\n\n";
$final_text .= implode("\n", array_keys($shanime_unikatni));
$final_text .= "\n\nIf you have any of these available, please let us know the price, MOQ, and lead time.\n\nBest regards,\nLifefood Purchasing Team";
?>

<div class="row">
    <div class="col-md-12">
        <div class="alert alert-info" style="padding: 10px; margin-bottom: 15px;">
            <i class="glyphicon glyphicon-info-sign"></i>
            Seznam prioritně používá <strong>anglické názvy</strong> z číselníku surovin. Pokud u suroviny anglický název chybí, použije se český.
        </div>
        <textarea id="exportTextarea" class="form-control" rows="15" style="font-family: monospace; resize: vertical;"><?= $final_text ?></textarea>
    </div>
</div>