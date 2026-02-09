<?php

$sqlQuery1 = "SELECT stroje.id as sid, stroje.nazev as snazev FROM stroje;";
$resultSet1 = mysqli_query($conn, $sqlQuery1) or die("database error:". mysqli_error($conn));

	echo "<table id=\"editableTable\" class=\"table table-striped data-sortable dataTable no-footer table-bordered\">";
	echo "<thead class=\"orig\"><th>Id</th><th>Stroj</th></thead>";
	while( $stroj = mysqli_fetch_assoc($resultSet1)) {
		echo "<tr>";	
				echo "<td>" . $stroj['sid']. "</td><td>" . $stroj['snazev']. "</td>";
		echo "</tr>";							
}
	echo "</table>";

?>
 