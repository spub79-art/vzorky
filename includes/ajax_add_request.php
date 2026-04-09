<?php
include_once("db_connect.php");
session_start();

if (empty($_SESSION['username'])) die("Nepřihlášen");

$sur_raw = $_POST['id_surovina'];
$poznamka = mysqli_real_escape_string($conn, $_POST['poznamka']);
$priorita = intval($_POST['priorita']);
$bio = !empty($_POST['bio']) ? 1 : 0;
$vegan = !empty($_POST['vegan']) ? 1 : 0;
$bezlepek = !empty($_POST['bezlepek']) ? 1 : 0;
$kosher = !empty($_POST['kosher']) ? 1 : 0;
$halal    = intval($_POST['halal']);

// LOGIKA PRO NOVOU SUROVINU
if (!is_numeric($sur_raw)) {
    $sur_name = mysqli_real_escape_string($conn, $sur_raw);
    mysqli_query($conn, "INSERT INTO suroviny (nazev) VALUES ('$sur_name')");
    $id_surovina = mysqli_insert_id($conn);
} else {
    $id_surovina = intval($sur_raw);
}

if ($id_surovina == 0) die("Chyba suroviny");

if ($id_surovina == 0) die("Chyba suroviny");

// Přečteme, jestli uživatel odklikl varování
$force_create = intval($_POST['force_create'] ?? 0);

if ($force_create == 0) {
    // --- START: KONTROLA DUPLICIT ---
    $check_sql = "SELECT id FROM pozadavky WHERE id_surovina = $id_surovina AND id_status NOT IN (5, 6, 7) LIMIT 1";
    $check_res = mysqli_query($conn, $check_sql);

    if ($check_res && mysqli_num_rows($check_res) > 0) {
        $existujici = mysqli_fetch_assoc($check_res);
        // Pošleme JS speciální klíč s IDčkem existujícího požadavku
        die("WARNING|" . $existujici['id']);
    }
    // --- KONEC: KONTROLY DUPLICIT ---
}

$sql = "INSERT INTO pozadavky (id_surovina, id_status, bio, vegan, bezlepek, kosher, halal, priorita, poznamka, datumPozadavek) 
        VALUES ($id_surovina, 1, $bio, $vegan, $bezlepek, $kosher, $halal, $priorita, '$poznamka', NOW())";

if (mysqli_query($conn, $sql)) {
    // ZACHYCENÍ NOVÉHO ID PRO ODKAZ
    $new_req_id = mysqli_insert_id($conn);

    // --- START: CHYTRÉ TELEGRAM NOTIFIKACE ---
    include_once("telegram.php");

    $sur_nazev = "Neznámá surovina";
    $q_sur = mysqli_query($conn, "SELECT nazev FROM suroviny WHERE id = " . (int)$id_surovina);
    if ($q_sur && $r_sur = mysqli_fetch_assoc($q_sur)) {
        $sur_nazev = $r_sur['nazev'];
    }

    $prio_text = ($priorita == 1) ? "🚨 URGENTNÍ" : "Normální";
    $kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo');

    // BEZPEČNÉ HTML FORMÁTOVÁNÍ (htmlspecialchars zabrání pádům doručení)
    $msg = "🆕 <b>NÁKUP: Byl zadán nový požadavek na surovinu!</b>\n\n";
    $msg .= "<b>Surovina:</b> " . htmlspecialchars($sur_nazev) . "\n";
    $msg .= "<b>Priorita:</b> " . $prio_text . "\n";
    $msg .= "<b>Zadal/a:</b> " . htmlspecialchars($kdo) . "\n";
    if (!empty($_POST['poznamka'])) {
        $msg .= "<b>Zadání:</b> " . htmlspecialchars(trim($_POST['poznamka'])) . "\n";
    }

    // AUTOMATICKÁ DETEKCE DOMÉNY A PŘIDÁNÍ KLIKACÍHO TEXTU (HTML ODKAZ)
    $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $new_req_id;

    $msg .= "\n👉 <a href='" . $link . "'>Otevřít detail požadavku v systému</a>";

    // Odeslání do skupiny 'nakup'
    sendTelegram($msg, 'nakup');
    // --- KONEC: CHYTRÉ TELEGRAM NOTIFIKACE ---

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba: " . mysqli_error($conn);
}
?>