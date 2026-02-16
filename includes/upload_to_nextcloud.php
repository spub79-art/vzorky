<?php
include_once("db_connect.php");

$id_nabidka = $_POST['id_nabidka'] ?? 0;
if (empty($_FILES['file']) || $id_nabidka == 0) {
    die("CHYBA: Chybi soubor nebo ID");
}

// 1. ZÍSKÁNÍ INFORMACÍ O PŘÍPADU (pro název složky)
$res = mysqli_query($conn, "
    SELECT p.id, s.nazev 
    FROM pozadavky_nabidky pn
    JOIN pozadavky p ON pn.id_pozadavek = p.id
    JOIN suroviny s ON p.id_surovina = s.id
    WHERE pn.id = " . intval($id_nabidka)
);
$data = mysqli_fetch_assoc($res);
$pripad_id = $data['id'] ?? 'Unknown';
$surovina_nazev = $data['nazev'] ?? 'Surovina';

// Očištění názvu pro URL (odstranění diakritiky a mezer)
$folder_name = $pripad_id . "_" . preg_replace('/[^a-zA-Z0-0_-]/', '_', $surovina_nazev);

$fileName = $_FILES['file']['name'];
$tempPath = $_FILES['file']['tmp_name'];

// 2. KONFIGURACE
$nc_user = "aplikace_poptavky";
$nc_pass = "vase-vygenerovane-app-heslo";
$nc_root = "https://nextcloud.lifefood.eu/remote.php/dav/files/aplikace_poptavky/DOKUMENTACE_VZORKU/";
$folder_url = $nc_root . rawurlencode($folder_name) . "/";
$full_url = $folder_url . rawurlencode($fileName);

// 3. VYTVOŘENÍ SLOŽKY (MKCOL)
// WebDAV vrátí 201 (Created) nebo 405 (Already exists) - obojí je pro nás OK
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $folder_url);
curl_setopt($ch, CURLOPT_USERPWD, $nc_user . ":" . $nc_pass);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_exec($ch);
curl_close($ch);

// 4. NAHRÁNÍ SOUBORU (PUT)
$ch = curl_init();
$fh = fopen($tempPath, 'r');
curl_setopt($ch, CURLOPT_URL, $full_url);
curl_setopt($ch, CURLOPT_USERPWD, $nc_user . ":" . $nc_pass);
curl_setopt($ch, CURLOPT_PUT, true);
curl_setopt($ch, CURLOPT_INFILE, $fh);
curl_setopt($ch, CURLOPT_INFILESIZE, filesize($tempPath));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fh);

// 5. ZÁPIS PŘÍMÉHO LINKU DO DB
if ($httpCode >= 200 && $httpCode < 300) {
    // Generujeme link přímo do složky pro webové rozhraní
    $web_view_link = "https://nextcloud.lifefood.eu/index.php/apps/files/?dir=/DOKUMENTACE_VZORKU/" . rawurlencode($folder_name);

    mysqli_query($conn, "UPDATE pozadavky_nabidky SET link_dokumentace = '$web_view_link' WHERE id = " . intval($id_nabidka));
    echo "OK";
} else {
    echo "Chyba: " . $httpCode;
}
?>