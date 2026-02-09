<?php
include_once("./db_connect.php");

$array = array();
$updateField="";

if ($_POST['action'] == 'edit' && $_POST['id']) {
if(isset($_POST['nazev'])) { $array[] = "nazev='".$_POST['nazev']."'"; }
if (count($array) == 0) { die("no object modified or other errors");}
$updateField = implode(', ', $array);}
if($updateField && $_POST['id']) {	
	
	
	if($updateField && $_POST['id']) {
		$sqlQuery = "UPDATE znacka SET $updateField WHERE id='" . $_POST['id'] . "'";	
		mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
		$data = array(
			"message"	=> "Record Updated",	
			"status" => 1
		);
		echo json_encode($data);		
	}
}
if ($_POST['action'] == 'delete' && $_POST['id']) {
	$sqlQuery = "DELETE FROM znacka WHERE id='" . $_POST['id'] . "'";	
	mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
	$data = array(
		"message"	=> "Record Deleted",	
		"status" => 1
	);
	echo json_encode($data);	
}
?>