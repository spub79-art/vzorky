<?php
include_once("./db_connect.php");

$array = array();
$updateField="";

if ($_POST['action'] == 'edit' && $_POST['id']) {
if(isset($_POST['datum'])) { $array[] = "datum='".$_POST['datum']."'"; }
if(isset($_POST['ks'])) { $array[] = "pocet='".$_POST['ks']."'";}
if(isset($_POST['idstroj'])) { $array[] = "id_stroje='".$_POST['idstroj']."'"; }
if(isset($_POST['idbalic'])) { $array[] = "id_balic='".$_POST['idbalic']."'"; }
if(isset($_POST['idpredano'])) { $array[] = "id_predano='".$_POST['idpredano']."'"; }
if(isset($_POST['idF'])) { $array[] = "id_folie='".$_POST['idF']."'"; }
if(isset($_POST['delka'])) { $array[] = "delka='".$_POST['delka']."'"; }
if(isset($_POST['inert'])) { $array[] = "inert='".$_POST['inert']."'"; }
if(isset($_POST['t1'])) { $array[] = "teplota='".$_POST['t1']."'"; }
if(isset($_POST['t2'])) { $array[] = "teplota2='".$_POST['t2']."'"; }
if(isset($_POST['t3'])) { $array[] = "teplota3='".$_POST['t3']."'"; }
if(isset($_POST['k1'])) { $array[] = "kleste='".$_POST['k1']."'"; }
if(isset($_POST['k2'])) { $array[] = "kleste2='".$_POST['k2']."'"; }
if(isset($_POST['p1'])) { $array[] = "pritlak='".$_POST['p1']."'"; }
if(isset($_POST['p2'])) { $array[] = "pritlak2='".$_POST['p2']."'"; }
if(isset($_POST['p3'])) { $array[] = "pritlak3='".$_POST['p3']."'"; }
if(isset($_POST['poznamka'])) { $array[] = "poznamka='".$_POST['poznamka']."'"; }
if (count($array) == 0) { die("no object modified or other errors");}
$updateField = implode(', ', $array);}
if($updateField && $_POST['id']) {	
	
	
	if($updateField && $_POST['id']) {
		$sqlQuery = "UPDATE zaznamy SET $updateField WHERE id='" . $_POST['id'] . "'";	
		mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
		$data = array(
			"message"	=> "Record Updated",	
			"status" => 1
		);
		echo json_encode($data);		
	}
}
if ($_POST['action'] == 'delete' && $_POST['id']) {
	$sqlQuery = "DELETE FROM zaznamy WHERE id='" . $_POST['id'] . "'";	
	mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
	$data = array(
		"message"	=> "Record Deleted",	
		"status" => 1
	);
	echo json_encode($data);	
}
?>