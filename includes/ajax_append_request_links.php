<?php
include_once("db_connect.php");
include_once("portfolio_helpers.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['username'])) {
    die("Nepřihlášen");
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    die("Neplatné ID.");
}

$check = mysqli_query($conn, "SELECT id FROM pozadavky WHERE id = $id AND id_status NOT IN (6) LIMIT 1");
if (!$check || mysqli_num_rows($check) === 0) {
    die("Požadavek nenalezen.");
}

$zakaznici = pf_parse_post_zakaznici($_POST);
$produkty = pf_parse_post_ids($_POST['produkty'] ?? []);

pf_append_request_zakaznici($conn, $id, $zakaznici);
pf_append_request_produkty($conn, $id, $produkty);
pf_sync_request_priorita_from_produkty($conn, $id);

file_put_contents('last_change.txt', time());
echo "OK";
