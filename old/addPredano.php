<?php

?>
<form action="#" method="POST">
  <div class="form-group">
    <label for="predano">Jméno osoby, které je možné předávat:</label>
    <input type="text" class="form-control" id="predano" name="predano" placeholder="" required>
  </div>
    <button type="submit" class="btn btn-primary">Přidat osobu které je možné předávat.</button>
</form>

<?php
if (isset($_POST['predano'])):
/*echo $konec;*/
$sql = "INSERT INTO predano (predano) VALUES ('".$_POST['predano']."')" ;
	if ($conn->query($sql) === TRUE) {
	echo "Nový záznam přidán";
	echo "<script>window.location.href='index.php?Predano=1';</script>";
	} else {
	echo "Error: " . $sql . "<br>" . $conn->error;
	}
/*mysql_free_result($resultSet);			*/
$conn->close();
endif;

?>
 