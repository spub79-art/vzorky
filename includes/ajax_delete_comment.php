<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['uid'])) die("Nepovolený přístup.");

$id = (int)$_POST['id'];
$uid = (int)$_SESSION['uid'];
$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);

if ($id > 0) {
    if ($is_adm) {
        $stmt = $conn->prepare("DELETE FROM board_poznamky WHERE id = ?");
        $stmt->bind_param("i", $id);
    } else {
        $stmt = $conn->prepare("DELETE FROM board_poznamky WHERE id = ? AND id_user = ?");
        $stmt->bind_param("ii", $id, $uid);
    }

    if ($stmt->execute()) {
        file_put_contents('last_change.txt', time());
        echo "OK";
    } else {
        echo "Chyba: " . $stmt->error;
    }
}
?>