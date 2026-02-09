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
    <label for="typ">Název/Číslo produktu</label>
    <input type="text" class="form-control" id="typ" name="typ" placeholder="" required>
  </div>
  <?php 
  $sqlQuery = "SELECT id, nazev FROM dodavatel";
			$resultSet = mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));
			echo "<label for=\"dodavatel_id\" class=\"form-label\">Vyberte Dodavatele</label>";
			echo "<select id=\"dodavatel\" name=\"dodavatel\" required>";
			echo "<option selected disabled>Musíte vybrat dodavatele</option>";
			while( $dodavatel = mysqli_fetch_assoc($resultSet)) {
				echo "<option value='" . $dodavatel['id']. "'>" . $dodavatel['nazev'] . "</option>";
			}
			echo "</select>";	


?>
    <div class="form-row">
    <div class="form-group col-md-6">
      <label for="tloustka">Tloušťka µm</label>
      <input type="number" class="form-control" id="tloustka" name="tloustka" placeholder="" required>
    </div>
    <div class="form-group col-md-6">
      <label for="sirka">Šířka mm</label>
      <input type="number" class="form-control" id="sirka" name="sirka" placeholder="" required>
    </div>
  </div>
    <button type="submit" class="btn btn-primary">Přidat fólii</button>
</form>

<?php
if (isset($_POST['typ'])):
/*echo $konec;*/
$sql = "INSERT INTO folie (nazev, tloustka, rozmer, id_dodavatel) VALUES ('".$_POST['typ']."', '".$_POST['tloustka']."', '".$_POST['sirka']."', '".$_POST['dodavatel']."')" ;
	if ($conn->query($sql) === TRUE) {
	echo "Nový záznam přidán";
	echo "<script>window.location.href='index.php?Folie=1';</script>";
	} else {
	echo "Error: " . $sql . "<br>" . $conn->error;
	}
/*mysql_free_result($resultSet);			*/
$conn->close();
endif;

?>
 