<?php 
include("includes/db_connect.php");
$sqlQuery = "SELECT id, uzivatel, ip, povolene FROM seznam";
$resultSet = mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));
while( $IPHO = mysqli_fetch_assoc($resultSet) ) {echo $IPHO ['ip'] . "\n";}
?>
