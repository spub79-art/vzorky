<?php
/*$sqlQuery1 = "SELECT folie.id as fid, folie.nazev as fnazev, folie.tloustka as ftloustka, folie.rozmer as frozmer, dodavatel.nazev as dnazev FROM folie INNER JOIN dodavatel ON folie.id_dodavatel=dodavatel.id;";
$resultSet1 = mysqli_query($conn, $sqlQuery1) or die("database error:". mysqli_error($conn));

	echo "<table id=\"editableTable\" class=\"table table-bordered\">";
	echo "<thead><th>Id</th><th>Typ</th><th>Dodavatel</th><th>Tloušťka</th><th>Šíře</th></thead>";
	while( $folie = mysqli_fetch_assoc($resultSet1)) {
		echo "<tr>";	
				echo "<td>" . $folie['fid']. "</td><td>" . $folie['fnazev']. "</td><td>" . $folie['dnazev'] . "</td><td>" . $folie['ftloustka'] . "</td><td>" . $folie['frozmer'] . "</td>";
		echo "</tr>";							
}
	echo "</table>";*/
?>
<form action="#" method="POST">
  <div class="form-group">
    <label for="typ">Název produktu</label>
    <input type="text" class="form-control" id="produkt" name="produkt" placeholder="" required>
  </div>
  <?php 
  $sqlQuery = "SELECT id, nazev FROM znacka";
			$resultSet = mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));
			echo "<label for=\"znacka_id\" class=\"form-label\">Vyberte značku</label>";
			echo "<select id=\"znacka\" name=\"znacka\" required>";
			echo "<option selected disabled>Musíte vybrat značku</option>";
			while( $znacka = mysqli_fetch_assoc($resultSet)) {
				echo "<option value='" . $znacka['id']. "'>" . $znacka['nazev'] . "</option>";
			}
			echo "</select>";	


?>
    <div class="form-row">
   </div>
    <button type="submit" class="btn btn-primary">Přidat produkt</button>
</form>

<?php
if (isset($_POST['produkt'])):
/*echo $konec;*/
$sql = "INSERT INTO produkt (nazev, id_znacka) VALUES ('".$_POST['produkt']."', '".$_POST['znacka']."')" ;
	if ($conn->query($sql) === TRUE) {
	echo "Nový záznam přidán";
	echo "<script>window.location.href='index.php?Produkty=1';</script>";
	} else {
	echo "Error: " . $sql . "<br>" . $conn->error;
	}
/*mysql_free_result($resultSet);			*/
$conn->close();
endif;

?>
 