<?php
include_once("db_connect.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['username'])) die("Nepřihlášen");

$id_pozadavek = (int)$_POST['id_pozadavek'];
$zakaznici = isset($_POST['zakaznici']) && is_array($_POST['zakaznici']) ? $_POST['zakaznici'] : [];

if (empty($zakaznici)) {
    die("Žádní zákazníci k přidání.");
}

// 1. Získáme seznam ID zákazníků, kteří už u tohoto požadavku jsou
$existujici_ids = [];
$q_old = mysqli_query($conn, "SELECT id_zakaznik FROM pozadavky_zakaznici WHERE id_pozadavek = $id_pozadavek");
while ($r_old = mysqli_fetch_assoc($q_old)) {
    $existujici_ids[] = (int)$r_old['id_zakaznik'];
}

$skutecne_pridana_jmena = [];

foreach ($zakaznici as $zak_raw) {
    if (trim($zak_raw) === '') continue;

    $id_zakaznik = 0;
    $nazev_zakaznika = '';

    if (!is_numeric($zak_raw)) {
        // Úplně nový zákazník - v číselníku ještě není, takže u požadavku taky být nemůže
        $z_name = mysqli_real_escape_string($conn, $zak_raw);
        mysqli_query($conn, "INSERT INTO zakaznici (nazev) VALUES ('$z_name')");
        $id_zakaznik = mysqli_insert_id($conn);
        $nazev_zakaznika = $zak_raw;
    } else {
        // Existující zákazník z číselníku
        $id_zakaznik = intval($zak_raw);

        // KONTROLA: Pokud už tento zákazník u požadavku je, přeskočíme ho
        if (in_array($id_zakaznik, $existujici_ids)) {
            continue;
        }

        $q_name = mysqli_query($conn, "SELECT nazev FROM zakaznici WHERE id = $id_zakaznik");
        if ($r_name = mysqli_fetch_assoc($q_name)) {
            $nazev_zakaznika = $r_name['nazev'];
        }
    }

    if ($id_zakaznik > 0) {
        mysqli_query($conn, "INSERT IGNORE INTO pozadavky_zakaznici (id_pozadavek, id_zakaznik) VALUES ($id_pozadavek, $id_zakaznik)");
        $skutecne_pridana_jmena[] = $nazev_zakaznika;
    }
}

// 2. Zápis do historie provedeme JEN tehdy, pokud přibyl alespoň jeden NOVÝ zákazník
if (!empty($skutecne_pridana_jmena)) {
    $zadavatel_jmeno = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Neznámý');
    $zadavatel_jmeno_db = mysqli_real_escape_string($conn, $zadavatel_jmeno);
    $uid = $_SESSION['uid'] ?? 0;

    $text_historie = "K požadavku byli nově přidáni zákazníci: " . implode(", ", $skutecne_pridana_jmena);
    $text_historie_db = mysqli_real_escape_string($conn, $text_historie);

    $log_sql = "INSERT INTO historie_pozadavku (id_pozadavek, id_nabidka, typ_zaznamu, id_user, jmeno_user, text_hodnota) 
                VALUES ($id_pozadavek, 0, 'komentar', $uid, '$zadavatel_jmeno_db', '$text_historie_db')";
    mysqli_query($conn, $log_sql);
}

@file_put_contents('last_change.txt', time());
echo "OK";
?>