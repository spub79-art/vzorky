<?php
include_once("db_connect.php");
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

$poznamka = isset($_POST['poznamka']) ? mysqli_real_escape_string($conn, trim($_POST['poznamka'])) : '';

$q = "UPDATE pozadavky SET 
        bio=$bio, vegan=$vegan, bezlepek=$bezlepek, kosher=$kosher, halal=$halal, 
        priorita=$priorita, poznamka='$poznamka'
      WHERE id=$id";

if (mysqli_query($conn, $q)) {

    // Nejprve smažeme všechny staré vazby na zákazníky pro tento požadavek
    mysqli_query($conn, "DELETE FROM pozadavky_zakaznici WHERE id_pozadavek = $id");

    // Nyní vložíme nové vazby ze Select2 (stejně jako při zakládání)
    $zakaznici = isset($_POST['zakaznici']) ? $_POST['zakaznici'] : [];
    if (!is_array($zakaznici)) $zakaznici = [];

    foreach ($zakaznici as $z_val) {
        $z_val = trim($z_val);
        if (empty($z_val)) continue;

        // Pokud to není číslo, uživatel napsal úplně nový název
        if (!is_numeric($z_val)) {
            $z_safe = mysqli_real_escape_string($conn, $z_val);
            $check_z = mysqli_query($conn, "SELECT id FROM zakaznici WHERE nazev = '$z_safe' LIMIT 1");
            if (mysqli_num_rows($check_z) > 0) {
                $zr = mysqli_fetch_assoc($check_z);
                $z_id = $zr['id'];
            } else {
                mysqli_query($conn, "INSERT INTO zakaznici (nazev) VALUES ('$z_safe')");
                $z_id = mysqli_insert_id($conn);
            }
        } else {
            $z_id = (int)$z_val;
        }

        // Vložíme do vazební tabulky
        mysqli_query($conn, "INSERT IGNORE INTO pozadavky_zakaznici (id_pozadavek, id_zakaznik) VALUES ($id, $z_id)");
    }

    echo "OK";
} else {
    echo "Chyba SQL: " . mysqli_error($conn);
}
?>