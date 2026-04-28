<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

$id_dodavatel = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id_dodavatel <= 0) {
    die("<div class='alert alert-danger'>Neplatné ID dodavatele.</div>");
}

// Změnili jsme JOIN na LEFT JOIN, abychom viděli i nabídky, ke kterým už chybí požadavek (sirotky)
$sql = "SELECT pn.id AS nabidka_id, pn.id_status AS nabidka_status, pn.pozadovane_mnozstvi, pn.updated_at, 
               p.id AS pozadavek_id, p.zadavatel_jmeno AS vytvoril, s.nazev AS surovina_nazev
        FROM pozadavky_nabidky pn
        LEFT JOIN pozadavky p ON pn.id_pozadavek = p.id
        LEFT JOIN suroviny s ON p.id_surovina = s.id
        WHERE pn.id_dodavatel = $id_dodavatel
        ORDER BY pn.updated_at DESC";

$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) == 0) {
    echo "<div class='alert alert-warning' style='margin: 0;'><i class='fa fa-info-circle'></i> Tento dodavatel zatím neposkytl žádné nabídky ani vzorky.</div>";
    exit;
}

$status_map = [
    1 => '<span class="badge" style="background-color: #6c757d;">Fáze 1: Čeká na cenu</span>',
    2 => '<span class="badge" style="background-color: #f0ad4e;">Fáze 2: Čeká na TDS</span>',
    12 => '<span class="badge" style="background-color: #5bc0de;">Fáze 2: Kontrola TDS (Kvalita)</span>',
    13 => '<span class="badge" style="background-color: #5bc0de;">Fáze 2: Kontrola TDS (Vývoj)</span>',
    3 => '<span class="badge" style="background-color: #337ab7;">Fáze 3: Cena OK</span>',
    8 => '<span class="badge" style="background-color: #337ab7;">Fáze 3: Objednán vzorek</span>',
    4 => '<span class="badge" style="background-color: #f0ad4e;">Fáze 4: Testování v lab.</span>',
    10 => '<span class="badge" style="background-color: #f0ad4e;">Fáze 4: Testování v lab.</span>',
    6 => '<span class="badge" style="background-color: #5cb85c;">Fáze 6: Schváleno (Hotovo)</span>',
    5 => '<span class="badge" style="background-color: #d9534f;">Zamítnuto (KO)</span>',
    7 => '<span class="badge" style="background-color: #d9534f;">Zrušeno</span>'
];

$is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
$base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped" style="font-size: 13px; margin-bottom: 0;">';
echo '<thead style="background-color: #f4f6f9;"><tr>';
echo '<th>Surovina</th>';
echo '<th>Aktuální stav vzorku</th>';
echo '<th>Poptal/a</th>';
echo '<th>Poslední změna</th>';
echo '<th class="text-center">Akce</th>';
echo '</tr></thead><tbody>';

while ($row = mysqli_fetch_assoc($res)) {
    $status_id = (int)$row['nabidka_status'];
    $status_html = isset($status_map[$status_id]) ? $status_map[$status_id] : '<span class="badge" style="background-color: #999;">Neznámý stav ('.$status_id.')</span>';

    $datum = $row['updated_at'] ? date('d.m.Y', strtotime($row['updated_at'])) : '-';
    $vytvoril = htmlspecialchars($row['vytvoril'] ? $row['vytvoril'] : 'Neznámý');

    // Ošetření pro "sirotky" (pokud byla surovina smazána)
    $surovina = $row['surovina_nazev'] ? htmlspecialchars($row['surovina_nazev']) : '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Smazaný požadavek</span>';

    echo '<tr>';
    echo '<td style="vertical-align: middle;"><strong>' . $surovina . '</strong></td>';
    echo '<td style="vertical-align: middle;">' . $status_html . '</td>';
    echo '<td style="vertical-align: middle;">' . $vytvoril . '</td>';
    echo '<td style="vertical-align: middle; color: #777;">' . $datum . '</td>';

    // Pokud chybí hlavní požadavek, neukazujeme tlačítko detailu (protože by vedlo do prázdna)
    echo '<td class="text-center" style="vertical-align: middle;">';
    if ($row['pozadavek_id']) {
        $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $row['pozadavek_id'];
        echo '<a href="' . $link . '" class="btn btn-sm btn-default" style="border-color: #ccc; font-weight: bold;" target="_blank">
                <i class="fa fa-external-link text-primary"></i> Detail
              </a>';
    } else {
        echo '<span class="badge bg-danger">Chyba vazby</span>';
    }
    echo '</td>';
    echo '</tr>';
}

echo '</tbody></table></div>';
?>