<?php
?>
<form action="#" method="POST">
  <div class="form-group">
    <label for="znacka">Značka</label>
    <input type="text" class="form-control" id="znacka" name="znacka" placeholder="" required>
  </div>
      
    <button type="submit" class="btn btn-primary">Přidat značku</button>
</form>

<?php
if (isset($_POST['znacka'])):
/*echo $konec;*/
$sql = "INSERT INTO znacka(nazev) VALUES ('".$_POST['znacka']."')" ;
	if ($conn->query($sql) === TRUE) {
	echo "Nový záznam přidán";
	 echo "<script>window.location.href='index.php?Produkty=1';</script>";
	
	} else {
	echo "Error: " . $sql . "<br>" . $conn->error;
	}
mysql_free_result($resultSet);			
$conn->close();

endif;

?>
 