<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once("db_connect.php");
include_once("boardFunctions.php");
$id_nabidka = $_POST['id_nabidka'] ?? 0;

if ($id_nabidka == 0) {
    http_response_code(400);
    die("CHYBA: Chybí ID nabídky.");
}

// 1. ZÍSKÁNÍ DAT O NABÍDCE (Včetně dodavatele)
$res = mysqli_query($conn, "
    SELECT p.id as id_pozadavek, s.nazev as surovina_nazev, pn.seznam_souboru, d.nazev as dodavatel_nazev 
    FROM pozadavky_nabidky pn
    JOIN pozadavky p ON pn.id_pozadavek = p.id
    JOIN suroviny s ON p.id_surovina = s.id
    LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
    WHERE pn.id = " . intval($id_nabidka)
);
$data = mysqli_fetch_assoc($res);

if (!$data) {
    http_response_code(404);
    die("CHYBA: Nabídka neexistuje.");
}

$id_pozadavek = $data['id_pozadavek'];
$surovina_nazev = $data['surovina_nazev'];
$dodavatel_nazev = $data['dodavatel_nazev'] ? $data['dodavatel_nazev'] : 'Neznamy_dodavatel';

// Názvy složek
$folder_req_name = $id_pozadavek . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $surovina_nazev);
$folder_off_name = $id_nabidka . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $dodavatel_nazev);

// Dosavadní soubory
$existing_files = [];
if (!empty($data['seznam_souboru'])) {
    $files_arr = explode('^', $data['seznam_souboru']);
    foreach ($files_arr as $f) {
        $parts = (strpos($f, '~') !== false) ? explode('~', $f) : explode('|', $f);
        $existing_files[] = $parts[0];
    }
}

// 2. KONFIGURACE CESTY (Používáme bezpečné konstanty NC_USER a NC_PASS)
$base_dir = (defined('IS_DEV') && IS_DEV) ? "DEVEL_DOKUMENTACE_VZORKU" : "DOKUMENTACE_VZORKU";

$nc_storage_root = "https://nextcloud.lifefood.eu/remote.php/dav/files/" . NC_USER . "/";
$nc_base_url = $nc_storage_root . $base_dir . "/";
$folder_req_url = $nc_base_url . rawurlencode($folder_req_name) . "/";
$folder_off_url = $folder_req_url . rawurlencode($folder_off_name) . "/"; // Zde se bude nahrávat

// 3. POMOCNÁ FUNKCE PRO VYTVOŘENÍ SLOŽKY
function create_nc_folder($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, NC_USER . ":" . NC_PASS);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode;
}

// Vytvoření struktury
create_nc_folder($nc_base_url);
create_nc_folder($folder_req_url);
create_nc_folder($folder_off_url);

// 4. NAHRÁNÍ SOUBORU
$errors = [];
$uploaded_db_strings = [];

function process_files($input_name, $type_tag, $folder_url) {
    global $errors, $uploaded_db_strings, $existing_files;

    if (empty($_FILES[$input_name]['name'][0])) return;

    foreach ($_FILES[$input_name]['name'] as $key => $name) {
        if (empty($name)) continue;

        if (in_array($name, $existing_files)) {
            $errors[] = "$name (Již existuje)";
            continue;
        }

        // 1. OCHRANA PROTI PŘEKROČENÍ LIMITU SERVERU A CHYBÁM PHP
        $fileError = $_FILES[$input_name]['error'][$key];
        if ($fileError !== UPLOAD_ERR_OK) {
            $err_msg = "Neznámá chyba";
            if ($fileError == UPLOAD_ERR_INI_SIZE || $fileError == UPLOAD_ERR_FORM_SIZE) $err_msg = "Soubor je na tento server příliš velký";
            $errors[] = "$name (Nenahráno: $err_msg)";
            continue;
        }

        $tempPath = $_FILES[$input_name]['tmp_name'][$key];
        $fileSize = filesize($tempPath);

        // 2. OCHRANA PROTI NULOVÝM SOUBORŮM
        if ($fileSize === 0) {
            $errors[] = "$name (Soubor je prázdný nebo poškozený)";
            continue;
        }

        // 3. VYČIŠTĚNÍ NÁZVU (Ochrana proti pádu cURL kvůli divným znakům)
        // Zbavíme se háčků, čárek a mezer, jinak se to na síti může ztratit
        $safe_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($name));

        $full_url = $folder_url . rawurlencode($safe_name);

        $fh = fopen($tempPath, 'r');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $full_url);
        curl_setopt($ch, CURLOPT_USERPWD, NC_USER . ":" . NC_PASS);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Přidán timeout, aby to "neumřelo" po cestě

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        // 4. BEZPEČNÁ VALIDACE VÝSLEDKU
        if ($curlErr) {
            $errors[] = "$safe_name (Chyba spojení na disk: $curlErr)";
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            $uploaded_db_strings[] = $safe_name . '~' . $type_tag;
            $existing_files[] = $safe_name;
        } else {
            $errors[] = "$safe_name (Disk vrátil chybu $httpCode)";
        }
    }
}

// Spuštění nahrávání do podsložky dodavatele
process_files('files_spec', 'spec', $folder_off_url);
process_files('files_lab', 'lab', $folder_off_url);
process_files('files_other', 'other', $folder_off_url);

// 5. ZÁPIS DO DATABÁZE
if (count($uploaded_db_strings) > 0) {
    $web_view_link = "https://nextcloud.lifefood.eu/index.php/apps/files/?dir=/$base_dir/" . rawurlencode($folder_req_name) . "/" . rawurlencode($folder_off_name);

    $new_files_str = implode('^', $uploaded_db_strings);
    $new_files_sql = mysqli_real_escape_string($conn, $new_files_str);

    // ZMĚNA: Odstraněno svévolné posouvání id_status. Skript teď pouze aktualizuje soubory.
    $sql = "UPDATE pozadavky_nabidky SET 
            link_dokumentace = '$web_view_link', 
            seznam_souboru = CASE 
                WHEN seznam_souboru IS NULL OR seznam_souboru = '' THEN '$new_files_sql'
                ELSE CONCAT(seznam_souboru, '^', '$new_files_sql') 
            END
            WHERE id = " . intval($id_nabidka);

    mysqli_query($conn, $sql);

    // ========================================================
    // ZÁPIS DO HISTORIE POŽADAVKU
    // ========================================================
    include_once("boardFunctions.php");
    $nahrane_nazvy = [];
    foreach ($uploaded_db_strings as $uds) {
        $parts = explode('~', $uds);
        $nahrane_nazvy[] = $parts[0];
    }
    $log_text = "Nahrány dokumenty: " . implode(", ", $nahrane_nazvy);
    zapis_do_historie($conn, $id_pozadavek, $id_nabidka, 'soubor', $log_text);
    // ========================================================

    @file_put_contents('last_change.txt', time());

    if (count($errors) > 0) echo "Částečně nahráno, chyby: " . implode(", ", $errors);
    else echo "OK";
} else {
    if (count($errors) > 0) echo "Chyba: " . implode(", ", $errors);
    else echo "Nebyl vybrán nový soubor.";
}
?>