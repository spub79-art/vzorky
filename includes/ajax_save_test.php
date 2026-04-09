<?php
include_once("db_connect.php");
session_start();

$id = intval($_POST['id']);
$text = mysqli_real_escape_string($conn, $_POST['text']);

if ($id > 0) {
    mysqli_query($conn, "UPDATE technologicke_testy SET vysledek_text = '$text' WHERE id = $id");
    echo "OK";
}
?>