<?php
?>
<form action="#" method="POST">
  <div class="form-group">
    <label for="nazev">Název dodavatele</label>
    <input type="text" class="form-control" id="nazev" name="nazev" placeholder="" required>
  </div>
      
    <button type="submit" class="btn btn-primary">Přidat dodavatele</button>
</form>

<?php
if (isset($_POST['nazev'])):
/*echo $konec;*/
$sql = "INSERT INTO dodavatel(nazev) VALUES ('".$_POST['nazev']."')" ;
	if ($conn->query($sql) === TRUE) {
	echo "Nový záznam přidán";
	 echo "<script>window.location.href='index.php?Folie=1';</script>";
	
	} else {
	echo "Error: " . $sql . "<br>" . $conn->error;
	}
mysql_free_result($resultSet);			
$conn->close();

endif;

?>
 