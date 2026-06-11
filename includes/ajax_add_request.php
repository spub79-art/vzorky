<?php
include_once("db_connect.php");
session_start();

if (empty($_SESSION['username'])) die("Nepřihlášen");

$sur_raw = $_POST['id_surovina'];
$poznamka = mysqli_real_escape_string($conn, $_POST['poznamka'] ?? '');
$priorita = intval($_POST['priorita']);
$bio = !empty($_POST['bio']) ? 1 : 0;
$vegan = !empty($_POST['vegan']) ? 1 : 0;
$bezlepek = !empty($_POST['bezlepek']) ? 1 : 0;
$kosher = !empty($_POST['kosher']) ? 1 : 0;
$halal    = intval($_POST['halal']);

$mnozstvi_raw = trim($_POST['mnozstvi'] ?? '');
$mnozstvi = ($mnozstvi_raw !== '') ? floatval(str_replace(',', '.', $mnozstvi_raw)) : 0;
$mj = mysqli_real_escape_string($conn, trim($_POST['mj'] ?? 'kg'));

// LOGIKA PRO NOVOU SUROVINU
if (!is_numeric($sur_raw)) {
    $sur_name = mysqli_real_escape_string($conn, $sur_raw);
    mysqli_query($conn, "INSERT INTO suroviny (nazev) VALUES ('$sur_name')");
    $id_surovina = mysqli_insert_id($conn);
} else {
    $id_surovina = intval($sur_raw);
}

if ($id_surovina == 0) die("Chyba suroviny");

$force_create = intval($_POST['force_create'] ?? 0);

if ($force_create == 0) {
    // --- START: CHYTRÁ KONTROLA DUPLICIT ---
    // Zkontrolujeme přesnou shodu: Surovina + všechny certifikáty
    $check_sql = "SELECT id FROM pozadavky 
                  WHERE id_surovina = $id_surovina 
                  AND bio = $bio AND vegan = $vegan AND bezlepek = $bezlepek AND kosher = $kosher AND halal = $halal
                  AND id_status NOT IN (5, 6, 7) LIMIT 1";
    $check_res = mysqli_query($conn, $check_sql);

    if ($check_res && mysqli_num_rows($check_res) > 0) {
        $existujici = mysqli_fetch_assoc($check_res);
        // Pošleme speciální klíč EXACT_DUP
        die("EXACT_DUP|" . $existujici['id']);
    }
    // --- KONEC: KONTROLY DUPLICIT ---
}

// Zjištění identity a role
$zadavatel_jmeno = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Neznámý');
$zadavatel_jmeno_db = mysqli_real_escape_string($conn, $zadavatel_jmeno);

$zadavatel_role = 'cumil';
if (!empty($_SESSION['vyvoj'])) $zadavatel_role = 'vyvoj';
if (!empty($_SESSION['orders'])) $zadavatel_role = 'orders';
if (!empty($_SESSION['kvalita'])) $zadavatel_role = 'kvalita';
if (!empty($_SESSION['adm'])) $zadavatel_role = 'adm';

$sql = "INSERT INTO pozadavky (id_surovina, id_status, bio, vegan, bezlepek, kosher, halal, priorita, Mnozstvi, mj, poznamka, datumPozadavek, zadavatel_role, zadavatel_jmeno) 
        VALUES ($id_surovina, 1, $bio, $vegan, $bezlepek, $kosher, $halal, $priorita, $mnozstvi, '$mj', '$poznamka', NOW(), '$zadavatel_role', '$zadavatel_jmeno_db')";

if (mysqli_query($conn, $sql)) {
    $new_req_id = mysqli_insert_id($conn);

    // ========================================================
    // NOVÉ: Uložení zákazníků (vazební tabulka) + vytvoření nových
    // ========================================================
    $zakaznici = isset($_POST['zakaznici']) && is_array($_POST['zakaznici']) ? $_POST['zakaznici'] : [];
    foreach ($zakaznici as $zak_raw) {
        if (trim($zak_raw) === '') continue;

        if (!is_numeric($zak_raw)) {
            // Vytvoříme nového zákazníka v číselníku
            $z_name = mysqli_real_escape_string($conn, $zak_raw);
            mysqli_query($conn, "INSERT INTO zakaznici (nazev) VALUES ('$z_name')");
            $id_zakaznik = mysqli_insert_id($conn);
        } else {
            $id_zakaznik = intval($zak_raw);
        }

        if ($id_zakaznik > 0) {
            mysqli_query($conn, "INSERT INTO pozadavky_zakaznici (id_pozadavek, id_zakaznik) VALUES ($new_req_id, $id_zakaznik)");
        }
    }

    // První zápis do sjednocené historie požadavku
    $uid = $_SESSION['uid'] ?? 0;
    $log_text = "Požadavek založen (Priorita: " . ($priorita == 1 ? "Urgentní" : "Normální") . ").";
    $log_sql = "INSERT INTO historie_pozadavku (id_pozadavek, id_nabidka, typ_zaznamu, id_user, jmeno_user, text_hodnota) 
                VALUES ($new_req_id, 0, 'zalozeni', $uid, '$zadavatel_jmeno_db', '$log_text')";
    mysqli_query($conn, $log_sql);

    // Telegram notifikace (zachována, jen ošetřena proti prázdné poznámce)
    include_once("telegram.php");
    $sur_nazev = "Neznámá surovina";
    $q_sur = mysqli_query($conn, "SELECT nazev FROM suroviny WHERE id = " . (int)$id_surovina);
    if ($q_sur && $r_sur = mysqli_fetch_assoc($q_sur)) {
        $sur_nazev = $r_sur['nazev'];
    }

    $prio_text = ($priorita == 1) ? "🚨 URGENTNÍ" : "Normální";
    $msg = "🆕 <b>NÁKUP: Byl zadán nový požadavek na surovinu!</b>\n\n";
    $msg .= "<b>Surovina:</b> " . htmlspecialchars($sur_nazev) . "\n";
    $msg .= "<b>Priorita:</b> " . $prio_text . "\n";
    $msg .= "<b>Zadal/a:</b> " . htmlspecialchars($zadavatel_jmeno) . "\n";
    if (!empty($poznamka)) {
        $msg .= "<b>Zadání:</b> " . htmlspecialchars(trim($poznamka)) . "\n";
    }

    $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $new_req_id;
    $msg .= "\n👉 <a href='" . $link . "'>Otevřít detail požadavku v systému</a>";

    sendTelegram($msg, 'nakup');

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba databáze: " . mysqli_error($conn);
}
?>