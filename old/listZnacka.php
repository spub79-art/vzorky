<?php

$sqlQuery1 = "SELECT znacka.id as zid, znacka.nazev as znazev FROM znacka;";
$resultSet1 = mysqli_query($conn, $sqlQuery1) or die("database error:". mysqli_error($conn));

	echo "<table id=\"editableTable\" class=\"table table-striped data-sortable dataTable no-footer table-bordered\">";
	echo "<thead><th>ID</th><th>Značka</th></thead>";
	while( $znacka = mysqli_fetch_assoc($resultSet1)) {
		echo "<tr>";	
				echo "<td>" . $znacka['zid']. "</td><td>" . $znacka['znazev']. "</td>";
		echo "</tr>";							
}
	echo "</table>";

?>
 