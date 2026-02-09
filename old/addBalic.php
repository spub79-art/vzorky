<?php
if (!empty($_SERVER['HTTP_CLIENT_IP']))   
  {
    $ip_address = $_SERVER['HTTP_CLIENT_IP'];
  }
//whether ip is from proxy
elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  
  {
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
  }
//whether ip is from remote address
else
  {
    $ip_address = $_SERVER['REMOTE_ADDR'];
  }



$sqlQuery = "SELECT ip, poznamka FROM seznam";
$resultSet = mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));
			 while( $IPHO2 = mysqli_fetch_assoc($resultSet) /*&& $uzje==0*/ ) {
				/*echo ">" . $IPHO2 ['ip'] . "<>" . $ip_address . "<<br/>";*/
				if ($IPHO2 ['ip'] == $ip_address):
				 echo "IP adresu není možné přidat, <b> ".$ip_address."</b> je už přidaná s touto poznámkou : <b> ". $IPHO2 ['poznamka'] . "</b><br/>";
				 $konec=$konec+1;
				 else:
				 $konec=0;
				endif;
			}
			
			/* str_contains ( string $haystack , string $needle ) : bool*/
/*echo ">".$ip_address."<>" . strstr($ip_address,"192.168")."<";*/
/*				if (!strstr($ip_address,"192.168")){echo "neni";} else {echo "je";}*/
				if((strstr($ip_address,"192.168") OR (strstr($ip_address,"172.16") /*OR (!strstr($ip_address,"10."))*/)) AND $konec<1): 
				echo "Vaší adresu není možné přidat, adresa nelze přidat .. kontaktujte IT. Vaše adresa je: <b>" . $ip_address ."</b>";
					$konec=$konec+1;
				else: 
/*					echo "coto";*/
				endif;
				if ($konec < 1):
					echo "Vaše IP adresa: " . $ip_address;
					?> <form action="#" method="POST">
					<label for="poznamka">Do okénka napište odkud se připojujete (doma, chata ap.) a stiskněte tlačítko "Submit": </label><input id="poznamka" type="text" name="poznamka" /> 
					<input type="submit" name="Přidat adresu" value="Submit" />
					</form><?php
					
				endif;
					
				

if (isset($_POST['poznamka']) AND $konec<1):
/*echo $konec;*/
$sql = "INSERT INTO seznam (uzivatel, ip, poznamka) VALUES ('".$_SESSION["username"]."', '".$ip_address."', '".$_POST['poznamka']."')" ;
	if ($conn->query($sql) === TRUE) {
	echo "Nový záznam přidán";
	} else {
	echo "Error: " . $sql . "<br>" . $conn->error;
	}

$conn->close();
endif;

?>