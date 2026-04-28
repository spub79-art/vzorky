<?php
// migrate_history.php
include_once("includes/db_connect.php");

echo "<h2>Migrace starých poznámek do nové historie</h2>";

// Vybereme všechny staré poznámky
$q = mysqli_query($conn, "SELECT * FROM board_poznamky ORDER BY vytvoreno ASC");
$uspesno = 0;
$chyb = 0;

while ($row = mysqli_fetch_assoc($q)) {
    $id_pozadavek = 0;
    $id_nabidka = 0;
    $typ_zaznamu = 'komentar';

    // Zjistíme IDčka
    if ($row['typ_entity'] == 'pozadavek') {
        $id_pozadavek = (int)$row['id_entity'];
    } elseif ($row['typ_entity'] == 'nabidka') {
        $id_nabidka = (int)$row['id_entity'];
        // K nabídce musíme dohledat ID jejího nadřazeného požadavku
        $q_req = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_nabidky WHERE id = $id_nabidka");
        if ($r_req = mysqli_fetch_assoc($q_req)) {
            $id_pozadavek = (int)$r_req['id_pozadavek'];
        }
    }

    // Data pro vložení
    $id_user = (int)$row['id_user'];
    $autor_jmeno = mysqli_real_escape_string($conn, $row['autor_jmeno']);
    $text = mysqli_real_escape_string($conn, $row['text_poznamky']);
    $puvodni_datum = $row['vytvoreno'];

    // Vložíme do nové tabulky (zachováváme $puvodni_datum!)
    $sql = "INSERT INTO historie_pozadavku 
            (id_pozadavek, id_nabidka, typ_zaznamu, id_user, jmeno_user, text_hodnota, vytvoreno) 
            VALUES 
            ($id_pozadavek, $id_nabidka, '$typ_zaznamu', $id_user, '$autor_jmeno', '$text', '$puvodni_datum')";

    if (mysqli_query($conn, $sql)) {
        $uspesno++;
    } else {
        $chyb++;
        echo "<span style='color:red;'>Chyba při kopírování ID {$row['id']}: " . mysqli_error($conn) . "</span><br>";
    }
}

echo "<h3>Hotovo!</h3>";
echo "Úspěšně přeneseno: <b>$uspesno</b> záznamů.<br>";
if ($chyb > 0) {
    echo "Chyb: <b>$chyb</b>.<br>";
}

echo "<br><p>Tento skript nyní můžete smazat z FTP, nebo jej spustit znovu později (pozor, může vytvořit duplicity, pokud ho pustíte 2x!).</p>";
?>