<?php
// Soubor: includes/delete_file.php
include_once("db_connect.php");

// Povolit jen pro Admina (1) nebo Nákup (orders=1)
session_start();
include_once("permissions.php");
if (!userCanNakup()) {
    http_response_code(403);
    die("Nemáte oprávnění mazat soubory.");
}

$id_nabidka = intval($_POST['id']);
$filename = $_POST['file'];

if ($id_nabidka == 0 || empty($filename)) die("Chyba dat.");

// 1. Zjistit informace pro cestu k souboru
$res = mysqli_query($conn, "
    SELECT p.id AS pripad_id, s.nazev AS surovina_nazev, pn.seznam_souboru
    FROM pozadavky_nabidky pn
    JOIN pozadavky p ON pn.id_pozadavek = p.id
    JOIN suroviny s ON p.id_surovina = s.id
    WHERE pn.id = $id_nabidka
");
$data = mysqli_fetch_assoc($res);

if (!$data) die("Nabídka nenalezena.");

// 2. Smazání z Nextcloudu
// Název souboru v DB je "nazev.pdf|typ". Pro Nextcloud potřebujeme jen "nazev.pdf".
$parts = explode('~', $filename);
$real_filename = $parts[0];

$folder_name = $data['pripad_id'] . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['surovina_nazev']);

$nc_user = "aplikace_poptavky";
$nc_pass = "vase-vygenerovane-app-heslo"; // <--- ZDE DÁT SPRÁVNÉ HESLO
$nc_root = "https://nextcloud.lifefood.eu/remote.php/dav/files/$nc_user/DOKUMENTACE_VZORKU/";
$full_url = $nc_root . rawurlencode($folder_name) . "/" . rawurlencode($real_filename);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $full_url);
curl_setopt($ch, CURLOPT_USERPWD, $nc_user . ":" . $nc_pass);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 204 = Smazáno, 404 = Už tam není (taky OK, smažeme z DB)
if ($http_code == 204 || $http_code == 404) {

    // 3. Aktualizace DB
    $current_files = explode('^', $data['seznam_souboru']);
    // Odstraníme ten konkrétní soubor z pole
    $new_files = array_diff($current_files, [$filename]);
    $new_files_str = implode('^', $new_files);

    $sql_upd = "UPDATE pozadavky_nabidky SET seznam_souboru = '" . mysqli_real_escape_string($conn, $new_files_str) . "' WHERE id = $id_nabidka";
    mysqli_query($conn, $sql_upd);

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba Nextcloud: $http_code";
}
?>