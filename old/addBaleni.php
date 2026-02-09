<?php
/*vyber folie*/
$sql = "SELECT folie.id as fid, folie.nazev as fnazev, folie.tloustka as ftloustka, folie.rozmer as frozmer, dodavatel.nazev as dnazev FROM folie INNER JOIN dodavatel ON folie.id_dodavatel=dodavatel.id;";
/*vyber stroje*/
$sql .= "SELECT stroje.id as sid, stroje.nazev as snazev FROM stroje;";
/*vyber komu predali*/
$sql .= "SELECT predano.id as prid, predano.predano as ppredano FROM predano;";
/*vyber znacky*/
$sql .= "SELECT znacka.id as zid, znacka.nazev as znazev FROM znacka;";




$p=1;
$ftd=0;
$zaznam=0;
?>

<?php

echo "<form class=\"form-horizontal\" action=\"#\" method=\"POST\">";
echo "<div class=\"table-responsive\">";
 echo "<table id=\"addBaleni\"class=\"table table-bordered table-striped table-highlight\">";
	 echo "<thead>";
	 echo "<th>Fólie (číslo)</th>";
	 echo "<th>Balička</th>";
	 echo "<th>Předáno</th>";
	 echo "<th>Firma</th>";
	 echo "<th>Parametry</th>";
	 echo "</thead><tr>";
	 echo "<td class=\"folie\">";
// Execute multi query
if ($conn -> multi_query($sql)) {
  do {
	
    // Store first result set
    if ($result = $conn -> store_result()) {
		while ($row = $result -> fetch_row()) {
        /*prvni dotaz - vyber folie*/
		if ($p==1){
		$zaznam=$zaznam+1;
		if ($zaznam==1){
			echo "<div class=\"search-box\">";
			echo "	<input type=\"text\" name=\"fid\" autocomplete=\"off\" placeholder=\"Zadej číslo fólie\" required/>";
			echo "<div class=\"result\"></div>";
    echo "</div>";

		}
		}
		/*druhy dotaz - vyber stroj*/
		else if ($p==2){
		$zaznam=$zaznam+1;
		echo "<div class=\"stroj\" ". $row[0] .">";
		echo "<label class=\"form-check-label\" for=\"stroj\">";
		echo "<input class=\"form-check-input\" type=\"radio\" name=\"stroj\" value=\"" . $row[0]. "\" required>";
			echo	$row[1];
			echo "</label>";	
			echo "</div>";
		/*printf("%s\n", $row[0]);/**/
		/*printf("%s\n", $row[1]);/**/

		}
		else if ($p==3){
		$zaznam=$zaznam+1;
		echo "<div class=\"predano\" ". $row[0] .">";
		echo "<label class=\"form-check-label\" for=\"predano\">";
		echo "<input class=\"form-check-input\" type=\"radio\" name=\"predano\" value=\"" . $row[0]. "\" required>";
			echo	$row[1];
			echo "</label>";	
			echo "</div>";
		}
		else if ($p==4){
		$zaznam=$zaznam+1;
		
		/*echo "<div class=\"znacka\">";*/
		/*echo "<label class=\"znacka\" for=\"znacka\">";*/
		echo "<input class=\"znacka\" type=\"radio\" name=\"znacka\" value=\"" . $row[0]. "\" required>";
		
			echo	$row[1] . "<br/>";
			/*echo "</label>";*/
				
			/*echo "</div>";*/

		}
		
		else if ($p==5){
			$zaznam=$zaznam+1;
		
		}
		
      }
	 /*echo "</tr>";*/
	 if ($p==4){echo "</div><div class=\"resultZ\"></div>";}
     $result -> free_result();
	 $zaznam=0;
	 $p=$p+1;
    }
    // if there are more result-sets, the print a divider
    if ($conn -> more_results()) {
      /*printf("-------------\n");*/
	
	echo "</datalist></td><td class=\""; 
		if($p==2){echo "stroj\">";}
		elseif($p==3){echo "predano\">";}
		elseif($p==5){echo "produkt\">";}
		elseif($p==4){echo "znacka\"><div class=\"znacky\">";} 
		
    }
	else{
		$p=0;
	}
     //Prepare next result set
  } while ($conn -> next_result());
  
/*if ($p==4){echo "</div>";}*/
  echo "</td>";
  echo "<td class=\"ks\"><div class=\"ks\"><br/>";
		/*Ks*/
		echo "<label class=\"form-check-label\" for=\"ks\">Ks</label><br/>";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"ks\" required><br/>";
		/*Délka*/
		echo "<label class=\"form-check-label\" for=\"delka\">Délka fólie</label><br/>";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"delka\" required><br/>";
		/*Teplota*/
		echo "<label class=\"parametry3\" for=\"teplota\">Teplota<br/>";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"teplota\">";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"teplota2\">";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"teplota3\">";
		echo "</label>";
		/*čelisti*/
		echo "<label class=\"parametry2\" for=\"teplota\">Teplota čelistí<br/>";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"kleste\">";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"kleste2\">";
				echo "</label>";
		/*pritlak*/
		echo "<label class=\"parametry3\" for=\"teplota\">Tlaky<br/>";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"pritlak\">";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"pritlak2\">";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"pritlak3\">";
		echo "</label>";
		/*Inert*/
		/*echo "<label class=\"form-check-label\" for=\"inert\">Inert</label>";
		echo "<input class=\"form-check-input\" type=\"number\" name=\"inert\">";*/
			
			echo "</label>";
			?>
			    <div id="readroot" style="display: none">

            <input type="button" value="Odebrat Inert"
                   onclick="this.parentNode.parentNode.removeChild(this.parentNode);" />
			<span class="inertlabel">O2/CO2</span><br/>
            
			<input class="form-check-input" type="number" step="0.01" name="inert[0][0]" value="O2">
			 
			<input class="form-check-input" type="number" step="0.01" name="inert[0][1]" value="CO2">
			

        </div>

        

            <span id="writeroot"></span>

            <input type="button" onclick="moreFields()" value="Přidat další měření Inertu" />
            <?php /*<input type="submit" value="Send form" /> */?>

        
			
			<?php
			echo "</div></td>";
  
 
}

/*$conn -> close();*/

echo "</table>";
echo "<label class=\"form-check-label\" for=\"poznamka\">Poznámka</label>";
echo "<textarea class=\"form-control\" id=\"poznamka\" rows=\"7\" name=\"poznamka\"></textarea>"
?>

</div>
<button type="submit" id="addBaleni" class="btn btn-success active">Přidat balení</button>
</form>

<?php
if (isset($_POST['fid'])){
/*echo $konec;*/
$sql = "INSERT INTO zaznamy (id_folie, id_produkt, id_stroje, id_balic, id_predano, pocet, teplota,teplota2,teplota3,kleste,kleste2,pritlak,pritlak2,pritlak3, delka, poznamka) VALUES ('".$_POST['fid']."', '".$_POST['produkt']."', '".$_POST['stroj']."', '".$_SESSION["uid"][0]."', '".$_POST["predano"][0]."', '".$_POST["ks"]."', '".$_POST["teplota"]."', '".$_POST["teplota2"]."', '".$_POST["teplota3"]."', '".$_POST["kleste"]."', '".$_POST["kleste2"]."', '".$_POST["pritlak"]."', '".$_POST["pritlak2"]."', '".$_POST["pritlak3"]."', '".$_POST["delka"]."', '".$_POST["poznamka"]."');";
/*print_r($_POST);*/

if ( isset( $_POST['inert'] ) )
{
    /*echo '<table>';*/
    foreach ( $_POST['inert'] as $array[] )
    {
        // here you have access to $diam['top'] and $diam['bottom']
        /*echo '<tr>';
        echo '  <td>', $diam['O2'], '</td>';
        echo '  <td>', $diam['CO2'], '</td>';
        echo '</tr>';*/
    }
    /*echo '</table>';*/
}
	/*print_r ($diam);*/
/*echo $sql;*/
	if ($conn->query($sql) === TRUE) {
	/*	printf("New record has ID %d.\n", $mysqli->insert_id);
	echo "Nový záznam přidán";*/
	echo "<script>window.location.href='index.php?Baleni=1';</script>";
	
        /* Returns the auto generated id used in the last query */
        /*printf("%d\n", $conn->insert_id);*/

		$id1 = $conn->insert_id;
		/*$conn->next_result();
		$id2 = $conn->insert_id;*/

		/*print_r ($o2);*/
		

/*print_r ($array);*/
$fields = implode(', ', array_shift($array));

$values = array();

foreach ($array as $rowValues) {
	    foreach ($rowValues as $key => $rowValue) {
         $rowValues[$key] = ($rowValues[$key]);
        
    }
	
	
    $values[] = "(" . $id1 ."," . implode(', ', $rowValues) . ")";
}

	$query = "INSERT INTO inert (id_baleni,o2,co2 ) VALUES " . implode (', ', $values);
/*echo $query . "<br/>";*/


		/*$sql->next_result();*/
		
		/*echo $columns = implode(", ",array_keys($o2));
		$escaped_values = array_map('mysql_real_escape_string', array_values($o2));
		$values  = implode(", ", $escaped_values);		
		echo $values;
		echo $columns;*/
		/*$sql2 .= "INSERT INTO inert (id_baleni, o2, co2) VALUES ($id1, $values);";
		
		echo "a sqlko2: " . $sql2;
			*/if ($conn->query($query) ===TRUE){
				echo $sql2;		
			}
	else {
		echo "Error: " . $sql2 . "<br>" . $conn->error;
	}
	

	
	
	
	} else {
	echo "Error: " . $sql . "<br>" . $conn->error;
	}
	
/*mysql_free_result($resultSet);			*/
$conn->close();
}

?>
 