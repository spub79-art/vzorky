<?php

$sqlQuery1 = "SELECT predano.id as prid, predano.predano as ppredano FROM predano;";
$resultSet1 = mysqli_query($conn, $sqlQuery1) or die("database error:". mysqli_error($conn));

	echo "<table id=\"editableTable\" class=\"table table-striped data-sortable dataTable no-footer table-bordered\">";
	echo "<thead><th>Id</th><th>Předáno</th></thead>";
	while( $predano = mysqli_fetch_assoc($resultSet1)) {
		echo "<tr>";	
				echo "<td>" . $predano['prid']. "</td><td>" . $predano['ppredano']. "</td>";
		echo "</tr>";							
}
	echo "</table>";

?>
 