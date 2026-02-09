<?php
/* Attempt MySQL server connection. Assuming you are running MySQL
server with default setting (user 'root' with no password) */

$servername = "localhost";
$username = "baleni";
$password = "baleni";
$dbname = "baleni";


$link = mysqli_connect($servername, $username, $password, $dbname);
 
// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
 
if(isset($_REQUEST["znacka"])){
    // Prepare a select statement
/*    $sql = "SELECT folie.id as fid, folie.nazev as fnazev, folie.tloustka as ftloustka, folie.rozmer as frozmer, dodavatel.nazev as dnazev FROM folie INNER JOIN dodavatel ON folie.id_dodavatel=dodavatel.id WHERE folie.id LIKE ?";*/
    $sql = "SELECT produkt.id as pid, produkt.nazev as pnazev, znacka.nazev as znazev, znacka.id as zid FROM produkt INNER JOIN znacka ON produkt.id_znacka=znacka.id WHERE znacka.id LIKE ?";

    if($stmt = mysqli_prepare($link, $sql)){
        // Bind variables to the prepared statement as parameters
	
	   mysqli_stmt_bind_param($stmt, "i", $param_znacka);
        
        // Set parameters
        $param_znacka = $_REQUEST["znacka"] . '%';
        // Attempt to execute the prepared statement
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            
            // Check number of rows in the result set
            if(mysqli_num_rows($result) > 0){
                // Fetch result rows as an associative array
				echo "<div class=\"produktV\">";
                while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                    /*echo "<li> Číslo produktu : " . $row['pid'] . "</li>";*/
					echo "<input class=\"znacka\" type=\"radio\" name=\"produkt\" value=\"" . $row['pid'] . "\" required>"; echo $row['pnazev'];
					/*echo "<li> Značka : " . $row['znazev'] . "</li>";*/
					echo "<br/>";
                }
            } else{
                echo "<p>Tato značka nemá zatím nastavené žádné produkty</p>";
            }
        } else{
            echo "ERROR: Could not able to execute $sql. " . mysqli_error($link);
        }
    }
     
    // Close statement
    mysqli_stmt_close($stmt);
}
 
// close connection
mysqli_close($link);
?>