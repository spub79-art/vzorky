<?php
include_once("db_connect.php");

$id_nabidka = $_POST['id_nabidka'] ?? 0;

if ($id_nabidka == 0) {
    http_response_code(400);
    die("CHYBA: Chybí ID nabídky.");
}

// 1. ZÍSKÁNÍ DAT
$res = mysqli_query($conn, "
    SELECT p.id, s.nazev, pn.seznam_souboru 
    FROM pozadavky_nabidky pn
    JOIN pozadavky p ON pn.id_pozadavek = p.id
    JOIN suroviny s ON p.id_surovina = s.id
    WHERE pn.id = " . intval($id_nabidka)
);
$data = mysqli_fetch_assoc($res);

if (!$data) {
    http_response_code(404);
    die("CHYBA: Nabídka neexistuje.");
}

$pripad_id = $data['id'];
$surovina_nazev = $data['nazev'];
$folder_name = $pripad_id . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $surovina_nazev);

// --- ZÍSKÁNÍ SEZNAMU UŽ NAHRANÝCH SOUBORŮ ---
$existing_files = [];
if (!empty($data['seznam_souboru'])) {
    $files_arr = explode('^', $data['seznam_souboru']);
    foreach ($files_arr as $f) {
        // Získáme čistý název bez typu (podporuje ~ i |)
        $parts = (strpos($f, '~') !== false) ? explode('~', $f) : explode('|', $f);
        $existing_files[] = $parts[0];
    }
}

// 2. KONFIGURACE NEXTCLOUD
$nc_user = "aplikace_poptavky";
$nc_pass = "vase-vygenerovane-app-heslo"; // <--- ZDE NEZAPOMEŇ DOPLNIT HESLO
$nc_root = "https://nextcloud.lifefood.eu/remote.php/dav/files/$nc_user/DOKUMENTACE_VZORKU/";
$folder_url = $nc_root . rawurlencode($folder_name) . "/";

// 3. VYTVOŘENÍ SLOŽKY (MKCOL)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $folder_url);
curl_setopt($ch, CURLOPT_USERPWD, $nc_user . ":" . $nc_pass);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code_mkcol = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- FUNKCE PRO NAHRÁNÍ ---
$errors = [];
$uploaded_db_strings = [];

function process_files($input_name, $type_tag, $folder_url, $nc_user, $nc_pass) {
    global $errors, $uploaded_db_strings, $existing_files;

    if (empty($_FILES[$input_name]['name'][0])) return;

    foreach ($_FILES[$input_name]['name'] as $key => $name) {
        if (empty($name)) continue;

        // KONTROLA DUPLICITY
        if (in_array($name, $existing_files)) {
            $errors[] = "$name (Tento soubor už je nahrán)";
            continue;
        }

        // Kontrola duplicity v rámci aktuálního výběru
        foreach($uploaded_db_strings as $uploaded) {
            if (strpos($uploaded, $name . '~') === 0) {
                $errors[] = "$name (Duplicita ve výběru)";
                continue 2;
            }
        }

        $tempPath = $_FILES[$input_name]['tmp_name'][$key];
        $full_url = $folder_url . rawurlencode($name);

        $fh = fopen($tempPath, 'r');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $full_url);
        curl_setopt($ch, CURLOPT_USERPWD, $nc_user . ":" . $nc_pass);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($tempPath));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($httpCode >= 200 && $httpCode < 300) {
            $uploaded_db_strings[] = $name . '~' . $type_tag;
            $existing_files[] = $name; // Přidáme do seznamu, aby nešel nahrát znovu
        } else {
            $errors[] = "$name (Chyba $httpCode)";
        }
    }
}

// 4. ZPRACOVÁNÍ
process_files('files_spec', 'spec', $folder_url, $nc_user, $nc_pass);
process_files('files_lab', 'lab', $folder_url, $nc_user, $nc_pass);
process_files('files_other', 'other', $folder_url, $nc_user, $nc_pass);

// 5. ZÁPIS DO DB
if (count($uploaded_db_strings) > 0) {
    $web_view_link = "https://nextcloud.lifefood.eu/index.php/apps/files/?dir=/DOKUMENTACE_VZORKU/" . rawurlencode($folder_name);

    $new_files_str = implode('^', $uploaded_db_strings);
    $new_files_sql = mysqli_real_escape_string($conn, $new_files_str);

    $sql = "UPDATE pozadavky_nabidky SET 
            link_dokumentace = '$web_view_link', 
            id_status = IF(id_status < 3, 3, id_status),
            seznam_souboru = CASE 
                WHEN seznam_souboru IS NULL OR seznam_souboru = '' THEN '$new_files_sql'
                ELSE CONCAT(seznam_souboru, '^', '$new_files_sql') 
            END
            WHERE id = " . intval($id_nabidka);

    mysqli_query($conn, $sql);
    mysqli_query($conn, "UPDATE pozadavky SET id_status = 3 WHERE id = $pripad_id AND id_status < 3");

    file_put_contents('last_change.txt', time());

    if (count($errors) > 0) echo "Nahráno, ale některé přeskočeny: " . implode(", ", $errors);
    else echo "OK";
} else {
    if (count($errors) > 0) echo "Nenahráno (duplicity nebo chyba): " . implode(", ", $errors);
    else echo "Nebyl vybrán žádný nový soubor.";
}
?>