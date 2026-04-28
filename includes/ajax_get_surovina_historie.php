<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

$id_surovina = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id_surovina <= 0) {
    die("<div class='alert alert-danger'>Neplatné ID suroviny.</div>");
}

$sql = "SELECT d.nazev AS dodavatel_nazev, pn.id_status, pn.poznamka_cena, pn.updated_at, p.id AS pozadavek_id
        FROM pozadavky_nabidky pn
        JOIN pozadavky p ON pn.id_pozadavek = p.id
        LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
        WHERE p.id_surovina = $id_surovina
        ORDER BY pn.updated_at DESC";

$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) == 0) {
    echo "<div class='alert alert-warning m-0'><i class='fa fa-info-circle'></i> U této suroviny zatím neevidujeme žádné historické pokusy.</div>";
    exit;
}

$status_map = [
    1 => '<span class="badge badge-status-1">Fáze 1: Čeká na cenu</span>',
    2 => '<span class="badge badge-status-2">Fáze 2: Čeká na TDS</span>',
    12 => '<span class="badge badge-status-12">Fáze 2: Kontrola TDS</span>',
    13 => '<span class="badge badge-status-13">Fáze 2: Nutriční hod.</span>',
    3 => '<span class="badge badge-status-3">Fáze 3: Cena OK</span>',
    8 => '<span class="badge badge-status-8">Fáze 3: Objednán vzorek</span>',
    4 => '<span class="badge badge-status-4">Fáze 4: Lab. test</span>',
    10 => '<span class="badge badge-status-10">Fáze 4: Lab. test</span>',
    6 => '<span class="badge badge-status-6"><i class="fa fa-check"></i> Schváleno (Hotovo)</span>',
    5 => '<span class="badge badge-status-5"><i class="fa fa-times"></i> Zamítnuto (KO)</span>',
    7 => '<span class="badge badge-status-7"><i class="fa fa-ban"></i> Zrušeno</span>'
];

$is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
$base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";

echo '<div class="table-responsive">';
echo '<table class="table table-hover table-striped table-history">';
echo '<thead><tr>';
echo '<th>Dodavatel vzorku</th>';
echo '<th>Konečný výsledek</th>';
echo '<th>Klíčová poznámka / Důvod</th>';
echo '<th>Poslední akce</th>';
echo '<th class="text-center">Akce</th>';
echo '</tr></thead><tbody>';

while ($row = mysqli_fetch_assoc($res)) {
    $status_id = (int)$row['id_status'];
    $status_html = isset($status_map[$status_id]) ? $status_map[$status_id] : '<span class="badge bg-secondary">Neznámý stav</span>';

    $datum = $row['updated_at'] ? date('d.m.Y', strtotime($row['updated_at'])) : '-';
    $dodavatel = $row['dodavatel_nazev'] ? htmlspecialchars($row['dodavatel_nazev']) : '<span class="text-muted">Neznámý dodavatel</span>';

    $poznamka_raw = strip_tags($row['poznamka_cena']);
    $poznamka_clean = (mb_strlen($poznamka_raw) > 70) ? mb_substr($poznamka_raw, 0, 70) . '...' : $poznamka_raw;
    if (empty(trim($poznamka_clean))) $poznamka_clean = '<span class="text-empty-note">Bez poznámky</span>';

    $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $row['pozadavek_id'];

    echo '<tr>';
    echo '<td><strong>' . $dodavatel . '</strong></td>';
    echo '<td>' . $status_html . '</td>';
    echo '<td class="td-note-truncate" title="'.htmlspecialchars($poznamka_raw).'">' . $poznamka_clean . '</td>';
    echo '<td class="text-muted">' . $datum . '</td>';
    echo '<td class="text-center">
            <a href="' . $link . '" class="btn btn-sm btn-default btn-detail-link" target="_blank">
                <i class="fa fa-external-link text-primary"></i> Detail
            </a>
          </td>';
    echo '</tr>';
}

echo '</tbody></table></div>';
?>