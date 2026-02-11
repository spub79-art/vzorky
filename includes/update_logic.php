<?php
session_start();
include_once("includes/db_connect.php");

// Tato logika obslouží SetEditable z indexu
if (isset($_POST['action']) && $_POST['action'] == 'edit_inline') {
    $id = (int)$_POST['id'];
    $table = mysqli_real_escape_string($conn, $_POST['table']);

    // Tady by se dynamicky sestavil UPDATE podle tabulky...
    // Pro začátek jen logujeme
    echo json_encode(["status" => 1, "msg" => "Zatím uloženo testovacím skriptem"]);
}