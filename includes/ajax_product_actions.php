<?php
include_once("db_connect.php");
session_start();

$action = $_REQUEST['action'] ?? '';

if ($action == 'save_prod') {
    $id = intval($_POST['id']);
    $nazev = mysqli_real_escape_string($conn, $_POST['nazev']);
    $skup = mysqli_real_escape_string($conn, $_POST['skupzbo']);
    $reg = mysqli_real_escape_string($conn, $_POST['regcis']);

    if ($id > 0) $sql = "UPDATE produkty SET nazev='$nazev', skupzbo='$skup', regcis='$reg' WHERE id=$id";
    else $sql = "INSERT INTO produkty (nazev, skupzbo, regcis) VALUES ('$nazev', '$skup', '$reg')";

    if (mysqli_query($conn, $sql)) echo "OK"; else echo mysqli_error($conn);
}

if ($action == 'get_materials') {
    $pid = intval($_GET['id']);
    $res = mysqli_query($conn, "SELECT ps.id, s.nazev, s.skupzbo, s.regcis 
                                FROM produkty_suroviny ps 
                                JOIN suroviny s ON ps.id_surovina = s.id 
                                WHERE ps.id_produkt = $pid");
    echo "<table class='table table-condensed'>";
    while($r = mysqli_fetch_assoc($res)) {
        echo "<tr><td>{$r['nazev']} <small>({$r['skupzbo']}-{$r['regcis']})</small></td>
              <td class='text-right'><button class='btn btn-xs btn-danger btn-unlink-sur' data-id='{$r['id']}'><i class='glyphicon glyphicon-remove'></i></button></td></tr>";
    }
    echo "</table>";
}

if ($action == 'link_material') {
    $pid = intval($_POST['id_produkt']);
    $sid = intval($_POST['id_surovina']);
    mysqli_query($conn, "INSERT IGNORE INTO produkty_suroviny (id_produkt, id_surovina) VALUES ($pid, $sid)");
    echo "OK";
}

if ($action == 'unlink_material') {
    $id = intval($_POST['id']);
    mysqli_query($conn, "DELETE FROM produkty_suroviny WHERE id=$id");
    echo "OK";
}
?>