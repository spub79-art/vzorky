<?php

$sqlQuery1 = "SELECT folie.id as fid, folie.nazev as fnazev, folie.tloustka as ftloustka, folie.rozmer as frozmer, dodavatel.nazev as dnazev FROM folie INNER JOIN dodavatel ON folie.id_dodavatel=dodavatel.id;";
$resultSet1 = mysqli_query($conn, $sqlQuery1) or die("database error:". mysqli_error($conn));

	echo "<table id=\"editableTable\" class=\"table table-striped data-sortable dataTable no-footer table-bordered\">";
	echo "<thead><th>Id</th><th>Typ</th><th>Dodavatel</th><th>Tloušťka</th><th>Šíře</th></thead>";
	while( $folie = mysqli_fetch_assoc($resultSet1)) {
		echo "<tr>";	
				echo "<td>" . $folie['fid']. "</td><td>" . $folie['fnazev']. "</td><td>" . $folie['dnazev'] . "</td><td>" . $folie['ftloustka'] . "</td><td>" . $folie['frozmer'] . "</td>";
		echo "</tr>";							
}
	echo "</table>";

?>
 