<?php
include_once("./db_connect.php");

$array = array();
$updateField="";

if ($_POST['action'] == 'edit' && $_POST['id']) {
if(isset($_POST['typ'])) { $array[] = "nazev='".$_POST['typ']."'"; }
/*if(isset($_POST['dodavatel'])) { $array[] = "id_dodavatel='".$_POST['dodavatel']."'";}*/
if(isset($_POST['tloustka'])) { $array[] = "tloustka='".$_POST['tloustka']."'"; }
if(isset($_POST['sire'])) { $array[] = "rozmer='".$_POST['sire']."'"; }
if (count($array) == 0) { die("no object modified or other errors");}

$updateField = implode(', ', $array);}
if($updateField && $_POST['id']) {	
	
	
	if($updateField && $_POST['id']) {
		$sqlQuery = "UPDATE folie SET $updateField WHERE id='" . $_POST['id'] . "'";	
		mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
		$data = array(
			"message"	=> "Record Updated",	
			"status" => 1
		);
		echo json_encode($data);		
	}
}
if ($_POST['action'] == 'delete' && $_POST['id']) {
	$sqlQuery = "DELETE FROM folie WHERE id='" . $_POST['id'] . "'";	
	mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
	$data = array(
		"message"	=> "Record Deleted",	
		"status" => 1
	);
	echo json_encode($data);	
}
?>