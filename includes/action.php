<?php
include_once("./db_connect.php");
/*if ($_POST['action'] == 'edit' && $_POST['id']) {	
	$updateField='';
	if(isset($_POST['uzivatel'])) {
		$updateField.= "uzivatel='".$_POST['uzivatel']."'";
	} else if(isset($_POST['ip'])) {
		$updateField.= "ip='".$_POST['ip']."'";
	} else if(isset($_POST['povolene'])) {
		$updateField.= "povolene='".$_POST['povolene']."'";
	}*/
$array = array();
$updateField="";

if ($_POST['action'] == 'edit' && $_POST['id']) {
if(isset($_POST['uzivatel'])) { $array[] = "uzivatel='".$_POST['uzivatel']."'"; }
if(isset($_POST['ip'])) { $array[] = "ip='".$_POST['ip']."'";}
if(isset($_POST['povolene'])) { $array[] = "povolene='".$_POST['povolene']."'"; }
if(isset($_POST['poznamka'])) { $array[] = "poznamka='".$_POST['poznamka']."'"; }
if (count($array) == 0) { die("no object modified or other errors");}
$updateField = implode(', ', $array);}
if($updateField && $_POST['id']) {	
	
	
	if($updateField && $_POST['id']) {
		$sqlQuery = "UPDATE seznam SET $updateField WHERE id='" . $_POST['id'] . "'";	
		mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
		$data = array(
			"message"	=> "Record Updated",	
			"status" => 1
		);
		echo json_encode($data);		
	}
}
if ($_POST['action'] == 'delete' && $_POST['id']) {
	$sqlQuery = "DELETE FROM seznam WHERE id='" . $_POST['id'] . "'";	
	mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
	$data = array(
		"message"	=> "Record Deleted",	
		"status" => 1
	);
	echo json_encode($data);	
}
?>