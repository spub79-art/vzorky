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

// Update základních dat požadavku
$sql = "UPDATE pozadavky SET 
        bio = ?, vegan = ?, bezlepek = ?, kosher = ?, halal = ?, 
        priorita = ?, poznamka = ?, id_user_vytvoril = ?
        WHERE id = ? AND id_status = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiiiisii", $bio, $vegan, $bezlepek, $kosher, $halal, $priorita, $poznamka_zadani, $user_id, $id);

if ($stmt->execute()) {

    // ========================================================
    // NOVÉ: SYNCHRONIZACE ZÁKAZNÍKŮ
    // ========================================================
    // 1. Smažeme staré vazby
    mysqli_query($conn, "DELETE FROM pozadavky_zakaznici WHERE id_pozadavek = $id");

    // 2. Projdeme a uložíme nové (a případně vytvoříme chybějící v číselníku)
    $zakaznici = isset($_POST['zakaznici']) && is_array($_POST['zakaznici']) ? $_POST['zakaznici'] : [];
    foreach ($zakaznici as $zak_raw) {
        if (trim($zak_raw) === '') continue;

        if (!is_numeric($zak_raw)) {
            $z_name = mysqli_real_escape_string($conn, $zak_raw);
            mysqli_query($conn, "INSERT INTO zakaznici (nazev) VALUES ('$z_name')");
            $id_zakaznik = mysqli_insert_id($conn);
        } else {
            $id_zakaznik = intval($zak_raw);
        }

        if ($id_zakaznik > 0) {
            mysqli_query($conn, "INSERT INTO pozadavky_zakaznici (id_pozadavek, id_zakaznik) VALUES ($id, $id_zakaznik)");
        }
    }

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba: " . $stmt->error;
}
?>