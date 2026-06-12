<?php
// cron_denni_souhrn.php — denní e-mailový souhrn pro Nákup (Fáze 1)
// Spouštět 1× denně přes cron, např.: 0 8 * * 1-5 php /cesta/cron_denni_souhrn.php

include_once("includes/db_connect.php");
include_once("includes/mail.php");
@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

$BASE_URL = defined('APP_BASE_URL') ? APP_BASE_URL : 'https://docs.lifefood.eu/vzorky';

// Požadavky ve Fázi 1 (čeká na nabídku od Nákupu) + zákazníci + stav nabídek
$users_tbl = defined('DB_TBL_USERS') ? DB_TBL_USERS : 'users';
$sql = "SELECT p.*, s.nazev AS surovina_nazev, u_nak.jmeno AS nakupci_jmeno,
        (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ')
         FROM pozadavky_zakaznici pz
         JOIN zakaznici z ON pz.id_zakaznik = z.id
         WHERE pz.id_pozadavek = p.id) AS zakaznici_seznam,
        GROUP_CONCAT(IFNULL(pn.id_status, '0') SEPARATOR '|') AS nabidky_statusy,
        COUNT(CASE WHEN pn.id IS NOT NULL AND pn.id_status NOT IN (5,7) THEN 1 END) AS pocet_aktivnich,
        COUNT(pn.id) AS pocet_nabidek
        FROM pozadavky p
        LEFT JOIN suroviny s ON p.id_surovina = s.id
        LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
        LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
        WHERE p.id_status NOT IN (5, 6, 7, 8)
        GROUP BY p.id
        ORDER BY p.datumPozadavek ASC";

$result = mysqli_query($conn, $sql);

$urgentni = [];
$standardni = [];

// ID požadavků, u kterých někdo za posledních 24 h vyžádal další nabídku
$ping_ids = [];
$vcera = date('Y-m-d H:i:s', strtotime('-24 hours'));
$q_ping = mysqli_query($conn, "SELECT DISTINCT id_pozadavek FROM historie_pozadavku
                               WHERE typ_zaznamu = 'ping_nakup' AND vytvoreno >= '$vcera'");
if ($q_ping) {
    while ($r = mysqli_fetch_assoc($q_ping)) {
        $ping_ids[(int)$r['id_pozadavek']] = true;
    }
}

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Fáze 1 = žádná aktivní nabídka (nebo všechny KO)
        $faze1 = true;
        if (!empty($row['nabidky_statusy'])) {
            foreach (explode('|', $row['nabidky_statusy']) as $s_id) {
                if ($s_id !== '0' && !in_array((int)$s_id, [5, 7])) {
                    $faze1 = false;
                    break;
                }
            }
        }
        if (!$faze1) continue;

        $row['vyzadano'] = !empty($ping_ids[(int)$row['id']]);
        // pocet_nabidek = kolik nabídek celkem existuje (pro info „vše KO“)
        $row['pocet_nabidek'] = (int)$row['pocet_nabidek'];

        if ($row['priorita'] == 1) {
            $urgentni[] = $row;
        } else {
            $standardni[] = $row;
        }
    }
}

$celkem = count($urgentni) + count($standardni);

$is_preview = isset($_GET['preview']) && $_GET['preview'] == '1';
if ($is_preview) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $is_dev = (strpos($_SERVER['REQUEST_URI'] ?? '', 'dev-vzorky') !== false);
    if (!$is_dev && empty($_SESSION['adm'])) {
        die('Náhled je dostupný jen pro administrátory (nebo na dev prostředí).');
    }
}

if ($celkem === 0 && !$is_preview) {
    die("Vše je čisté, nic neodesílám.");
}

// Předmět: stručný a výstižný
$subject = "Vzorkovna: ";
if (count($urgentni) > 0) {
    $subject .= count($urgentni) . " urgentní";
    if (count($standardni) > 0) $subject .= ", ";
}
if (count($standardni) > 0) {
    $subject .= count($standardni) . " standardní";
}
$subject .= " — čeká Nákup";

$datum_cs = date('j.n.Y');
$html = wrapDigestEmail(
    "Ranní přehled pro Nákup",
    "$datum_cs · Požadavky ve Fázi 1 (čeká se na vaši nabídku)",
    [
        ['title' => 'Urgentní', 'color' => '#d9534f', 'rows' => $urgentni],
        ['title' => 'Standardní', 'color' => '#337ab7', 'rows' => $standardni],
    ],
    $BASE_URL
);

if ($is_preview) {
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

$recipients = getEmailsForChannel('nakup');
if (empty($recipients)) {
    die("Chyba: Žádní příjemci. Vyplňte e-maily u uživatelů s rolí Nákup, nebo MAIL_NAKUP v config.php.");
}

if (sendEmailTo($recipients, $subject, $html)) {
    echo "Odesláno ($celkem požadavků) na: " . implode(', ', $recipients);
} else {
    echo "Chyba při odesílání e-mailu.";
}
