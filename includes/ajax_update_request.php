<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['vyvoj']) && empty($_SESSION['adm'])) die("Nepovolený přístup.");

$id = (int)$_POST['id'];
$bio = (int)$_POST['bio'];
$vegan = (int)$_POST['vegan'];
$bezlepek = (int)$_POST['bezlepek'];
$kosher = (int)$_POST['kosher'];
$halal = (int)$_POST['halal'];
$priorita = (int)$_POST['priorita'];
$poznamka_zadani = trim($_POST['poznamka'] ?? '');
$user_id = !empty($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;

// Jednoduchý UPDATE bez CONCAT (Čistý přepis textu zadání)
$sql = "UPDATE pozadavky SET 
        bio = ?, vegan = ?, bezlepek = ?, kosher = ?, halal = ?, 
        priorita = ?, poznamka = ?, id_user_vytvoril = ?
        WHERE id = ? AND id_status = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiiiisii", $bio, $vegan, $bezlepek, $kosher, $halal, $priorita, $poznamka_zadani, $user_id, $id);

if ($stmt->execute()) {
    file_put_contents('last_change.txt', time());
    echo "OK";
} else { echo "Chyba: " . $stmt->error; }
?>