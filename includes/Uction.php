<?php
include_once("./db_connect.php");
$array = array();
$updateField = "";

if ($_POST['action'] == 'edit' && $_POST['id']) {
    // ID a hodnoty pro jistotu ošetříme, aby kód nespadl při speciálních znacích
    $id = mysqli_real_escape_string($conn, $_POST['id']);

    if(isset($_POST['jmeno']))   { $array[] = "jmeno='" . mysqli_real_escape_string($conn, $_POST['jmeno']) . "'"; }
    if(isset($_POST['login']))   { $array[] = "login='" . mysqli_real_escape_string($conn, $_POST['login']) . "'"; }
    if(isset($_POST['heslo']))   { $array[] = "heslo='" . mysqli_real_escape_string($conn, $_POST['heslo']) . "'"; }
    if(isset($_POST['admin']))   { $array[] = "admin='" . mysqli_real_escape_string($conn, $_POST['admin']) . "'"; }

    // PŘIDANÉ NOVÉ ROLE:
    if(isset($_POST['vyvoj']))   { $array[] = "vyvoj='" . mysqli_real_escape_string($conn, $_POST['vyvoj']) . "'"; }
    if(isset($_POST['orders']))  { $array[] = "orders='" . mysqli_real_escape_string($conn, $_POST['orders']) . "'"; }
    if(isset($_POST['kvalita'])) { $array[] = "kvalita='" . mysqli_real_escape_string($conn, $_POST['kvalita']) . "'"; }

    if (count($array) == 0) {
        die(json_encode(array("message" => "No object modified", "status" => 0)));
    }

    $updateField = implode(', ', $array);
    $sqlQuery = "UPDATE users SET $updateField WHERE id='$id'";

    if(mysqli_query($conn, $sqlQuery)) {
        $data = array("message" => "Record Updated", "status" => 1);
        echo json_encode($data);
    } else {
        die("database error:". mysqli_error($conn));
    }
}

if ($_POST['action'] == 'delete' && $_POST['id']) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $sqlQuery = "DELETE FROM users WHERE id='$id'";

    if(mysqli_query($conn, $sqlQuery)) {
        $data = array("message" => "Record Deleted", "status" => 1);
        echo json_encode($data);
    } else {
        die("database error:". mysqli_error($conn));
    }
}
?>