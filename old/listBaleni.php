<?php

$sqlQuery1 = "SELECT 
predano.id as prid, 
users.id as uid, 
users.jmeno as balic, 
zaznamy.id as zid, 
zaznamy.datum as zdatum, 
zaznamy.pocet as zpocet, 
stroje.id as sid, 
stroje.nazev as snazev, 
folie.nazev as fnazev, 
folie.id as fid, 
dodavatel.nazev as dnazev, 
folie.tloustka as ftloustka, 
folie.rozmer as frozmer, 
znacka.nazev as znazev, 
produkt.nazev as pnazev, 
predano.predano as ppredano,
zaznamy.teplota as teplota,
zaznamy.teplota2 as teplota2,
zaznamy.teplota3 as teplota3,
zaznamy.kleste as kleste,
zaznamy.kleste2 as kleste2,
zaznamy.pritlak as pritlak,
zaznamy.pritlak2 as pritlak2,
zaznamy.pritlak3 as pritlak3,
zaznamy.poznamka as poznamka,
zaznamy.delka as delka

	FROM zaznamy 
	INNER JOIN folie ON folie.id=zaznamy.id_folie
	INNER JOIN produkt ON zaznamy.id_produkt=produkt.id
	INNER JOIN znacka ON produkt.id_znacka=znacka.id
	INNER JOIN dodavatel ON folie.id_dodavatel=dodavatel.id
	INNER JOIN stroje ON stroje.id=zaznamy.id_stroje
	INNER JOIN predano ON predano.id=zaznamy.id_predano
	INNER JOIN users ON zaznamy.id_balic=users.id
	
	ORDER BY zaznamy.id DESC;";
if (empty($_POST['zid'])){
	$_POST['zid']=0;
}

$resultSet1 = mysqli_query($conn, $sqlQuery1) or die("database error:". mysqli_error($conn));

	echo "<div class=\"table-responsive-sm\">";
	echo "<table id=\"editableTable\" class=\"table table-striped data-sortable dataTable no-footer table-bordered\">";
	if (!empty($_SESSION['adm'])){
	
	echo "<thead>
			<th>Id</th>
			<th>Datum</th>
			<th>Značka</th>
			<th>Název</th>
			<th>ks</th>
			<th>Dodavatel</th>
			<th>IDf</th>
			<th>Název</th>
			<th>Tloušťka</th>
			<th>Rozměr</th>
			<th>Délka</th>
			<th>IDs</th>
			<th>Stroj</th>
			<th>IDt/a</th>
			<th>testoval/a</th>
			<th>IDP</th>
			<th>Předáno</th>
			<th>Poznámka</th>
			<th>Tepl</th>
			<th>T2</th>
			<th>T3</th>
			<th>Čel</th>
			<th>Č2</th>
			<th>Tlaky</th>
			<th>P2</th>
			<th>P3</th>
			
			</thead>";
	while( $zaznam = mysqli_fetch_assoc($resultSet1)) {
		
		echo "<tr>";	
				echo "
				<td class=\"zid\">" . $zaznam['zid']. "</td>
				<td class=\"zdatum\">" . $zaznam['zdatum']. "</td>
				<td class=\"znazev\">" . $zaznam['znazev'] . "</td>
				<td class=\"pnazev\">" . $zaznam['pnazev'] . "</td>
				<td class=\"zpocet\">" . $zaznam['zpocet'] . "</td>
				<td class=\"dnazev\">" . $zaznam['dnazev'] . "</td>
				<td class=\"fid\">" . $zaznam['fid'] . "</td>
				<td class=\"fnazev\">" . $zaznam['fnazev'] . "</td>
				<td class=\"floustka\">" . $zaznam['ftloustka'] . "</td>
				<td class=\"frozmer\">" . $zaznam['frozmer'] . "</td>
				<td class=\"delka\">" . $zaznam['delka'] . "</td>
				<td class=\"sid\">" . $zaznam['sid'] . "</td>
				<td class=\"snazev\">" . $zaznam['snazev'] . "</td>
				<td class=\"uid\">" . $zaznam['uid'] . "</td>
				<td class=\"balic\">" . $zaznam['balic'] . "</td>
				<td class=\"prid\">" . $zaznam['prid'] . "</td>
				<td class=\"ppredano\">" . $zaznam['ppredano'] . "</td>
				<td class=\"poznamka\">" . $zaznam['poznamka'] . "</td>
				<td class=\"teplota\">" . $zaznam['teplota'] . "</td>
				<td class=\"teplota2\">" . $zaznam['teplota2'] . "</td>
				<td class=\"teplota3\">" . $zaznam['teplota3'] . "</td>
				<td class=\"kleste\">" . $zaznam['kleste'] . "</td>
				<td class=\"kleste2\">" . $zaznam['kleste2'] . "</td>
				<td class=\"pritlak\">" . $zaznam['pritlak'] . "</td>
				<td class=\"pritlak2\">" . $zaznam['pritlak2'] . "</td>
				<td class=\"pritlak3\">" . $zaznam['pritlak3'] . "</td>"
			
				;
				
		echo "</tr>";							
	}}
	else{
		echo "<thead>
			<th class=\"rotate id\"><span>Id</span></th>
			<th class=\"rotate datum\"><div><span>Datum</span></div></th>
			<th class=\"rotate produkt\"><div><span>Produkt</span></div></th>
			<th class=\"rotate produkt\"><div><span>Délka</span></div></th>
			<th class=\"rotate folie\"><div><span>ID Fólie</span></div></th>
			<th class=\"rotate folie\"><div><span>Parametry fólie</span></div></th>
			<th class=\"rotate stroj\"><div><span>Nastavení</span></div></th>
			<th class=\"rotate stroj\"><div><span>Inert</span></div></th>
			<th class=\"rotate balic\"><div><span>testoval/a</span></div></th>
			<th class=\"rotate predano\"><div><span>Předáno</span></div></th>
			<th class=\"rotate ks\"><div><span>ks</span></div></th>
			<th class=\"rotate poznamka\"><div><span>Poznámka</span></div></th>
		</thead>";
	while( $zaznam = mysqli_fetch_assoc($resultSet1)) {
		$sqlQuery2 = "SELECT 
	inert.id_baleni as idb, 
	inert.o2 as o2, 
	inert.co2 as co2, 
	zaznamy.id as zid
	FROM inert
	INNER JOIN zaznamy ON zaznamy.id=inert.id_baleni
	WHERE inert.id_baleni=". $zaznam['zid'] ."
	ORDER BY zaznamy.id DESC;";
		$resultSet2 = mysqli_query($conn, $sqlQuery2) or die("database error:". mysqli_error($conn));	
		/*echo $zaznam['zid'] . " -tady- " . $_POST['zid'] . " -tady- " . $sqlQuery2;*/
		echo "<tr>";	
				echo "
				<td class=\"zid\">" . $zaznam['zid']. "</td>
				<td class=\"datum\">" . $zaznam['zdatum']. "</td>
				<td class=\"produkt\"><span class=\"znacka\">" . $zaznam['znazev'] . "</span><br/>
				<span class=\"pnazev\">" . $zaznam['pnazev'] . "</span></td>
				<td class=\"produkt\">" . $zaznam['delka'] . "mm</td>
				<td class=\"folie\">" . $zaznam['fid'] . "</td>
				<td class=\"folie\"><span class=\"dnazev\">" . $zaznam['dnazev'] . "</span><br/>
				<span class=\"fnazev\">" . $zaznam['fnazev'] . "</span><br/>
				<span class=\"ftloustka\">" . $zaznam['ftloustka'] . "um</span><br/>
				<span class=\"frozmer\">" . $zaznam['frozmer'] . "mm</span></td>
				<td class=\"stroj\"><span class=\"stroj\">" . $zaznam['snazev'] . "</span><br/> Teploty &#8451;:&nbsp;" . $zaznam['teplota'] . "&nbsp;/&nbsp;" . $zaznam['teplota2'] . "&nbsp;/&nbsp;" .$zaznam['teplota3'] . "<br/>Čelisti &#8451;:&nbsp;" . $zaznam['kleste'] . "&nbsp;/&nbsp;" . $zaznam['kleste2'] . "&nbsp;<br/>Tlaky:&nbsp;" . $zaznam['pritlak'] . "&nbsp;/&nbsp;" . $zaznam['pritlak2'] . "&nbsp;/&nbsp;". $zaznam['pritlak3']  . "</td>
				
				<td class=\"stroj\"><span class=\"inert\">O2/CO2</span><br/>"; while( $zaznam2 = mysqli_fetch_assoc($resultSet2)) 
					{ 
						
				echo $zaznam2['o2'] . "&nbsp;/&nbsp;" . $zaznam2['co2'] . "</br>";
					} 
				echo "</td>			 
				<td class=\"balic\">" . $zaznam['balic'] . "</td>
				<td class=\"predano\">" . $zaznam['ppredano'] . "</td>
				<td class=\"ks\">" . $zaznam['zpocet'] . "</td>
				<td class=\"poznamka\">" . $zaznam['poznamka'] . "</td>";
		echo "</tr>";							
	
	}}
		
	echo "</table>";
	echo "</div>";


?>
