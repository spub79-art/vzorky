<?php

?>
<form action="#" method="POST">
  <div class="form-group">
    <label for="stroj">Název stroje</label>
    <input type="text" class="form-control" id="stroj" name="stroj" placeholder="" required>
  </div>
    <button type="submit" class="btn btn-primary">Přidat stroj</button>
</form>

<?php
if (isset($_POST['stroj'])):
/*echo $konec;*/
$sql = "INSERT INTO stroje (nazev) VALUES ('".$_POST['stroj']."')" ;
	if ($conn->query($sql) === TRUE) {
	echo "Nový záznam přidán";
	echo "<script>window.location.href='index.php?Stroje=1';</script>";
	} else {
	echo "Error: " . $sql . "<br>" . $conn->error;
	}
/*mysql_free_result($resultSet);			*/
$conn->close();
endif;

?>
 