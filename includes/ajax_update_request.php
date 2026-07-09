<?php
include_once("db_connect.php");
include_once("portfolio_helpers.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['username'])) die("Nepřihlášen");

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) die("Neplatné ID.");

$bio = isset($_POST['bio']) ? (int)$_POST['bio'] : 0;
$vegan = isset($_POST['vegan']) ? (int)$_POST['vegan'] : 0;
$bezlepek = isset($_POST['bezlepek']) ? (int)$_POST['bezlepek'] : 0;
$kosher = isset($_POST['kosher']) ? (int)$_POST['kosher'] : 0;
$halal = isset($_POST['halal']) ? (int)$_POST['halal'] : 0;
$priorita = isset($_POST['priorita']) ? (int)$_POST['priorita'] : 0;
$produkty_ids = pf_parse_post_ids($_POST['produkty'] ?? []);
$priorita = pf_effective_request_priorita($conn, $priorita, $produkty_ids);

$poznamka = isset($_POST['poznamka']) ? mysqli_real_escape_string($conn, trim($_POST['poznamka'])) : '';

$mnozstvi_raw = trim($_POST['mnozstvi'] ?? '');
$mnozstvi = ($mnozstvi_raw !== '') ? floatval(str_replace(',', '.', $mnozstvi_raw)) : 0;
$mj = mysqli_real_escape_string($conn, trim($_POST['mj'] ?? 'kg'));

$q = "UPDATE pozadavky SET 
        bio=$bio, vegan=$vegan, bezlepek=$bezlepek, kosher=$kosher, halal=$halal, 
        priorita=$priorita, Mnozstvi=$mnozstvi, mj='$mj', poznamka='$poznamka'
      WHERE id=$id";

if (mysqli_query($conn, $q)) {

    pf_save_request_zakaznici($conn, $id, pf_parse_post_zakaznici($_POST));
    pf_sync_request_produkty($conn, $id, $produkty_ids);

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba SQL: " . mysqli_error($conn);
}
?>