<?php

$sqlQuery1 = "SELECT produkt.id as pid, produkt.nazev as pnazev, znacka.nazev as znazev FROM produkt INNER JOIN znacka ON produkt.id_znacka=znacka.id;";
$resultSet1 = mysqli_query($conn, $sqlQuery1) or die("database error:". mysqli_error($conn));

	echo "<table id=\"editableTable\" class=\"table table-striped data-sortable dataTable no-footer table-bordered\">";
	echo "<thead><th>Id</th><th>Produkt</th><th>Značka</th></thead>";
	while( $produkt = mysqli_fetch_assoc($resultSet1)) {
		echo "<tr>";	
				echo "<td>" . $produkt['pid']. "</td><td>" . $produkt['pnazev']. "</td><td>" . $produkt['znazev'] . "</td>";
		echo "</tr>";							
}
	echo "</table>";

?>
 