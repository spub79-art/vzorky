<?php
ob_start();
session_start();
include("db_connect.php");
include_once("boardFunctions.php");

$id = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$poznamka_vstup = trim($_POST['poznamka'] ?? '');
$qty = isset($_POST['qty']) ? mysqli_real_escape_string($conn, $_POST['qty']) : '';
$sarze = isset($_POST['sarze']) ? mysqli_real_escape_string($conn, $_POST['sarze']) : '';

// NOVÉ: Kódy pro IS
$skupzbo = isset($_POST['skupzbo']) ? mysqli_real_escape_string($conn, $_POST['skupzbo']) : '';
$regcis = isset($_POST['regcis']) ? mysqli_real_escape_string($conn, $_POST['regcis']) : '';

// Identifikace uživatele a času
$kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo');
$datum_dnes = date("j.n. H:i");

// --- FORMÁTOVÁNÍ POZNÁMKY A AUDITNÍ STOPY ---
$poznamka_final = "";
if (!empty($poznamka_vstup)) {
    if (strpos($poznamka_vstup, 'Systémová akce:') === false) {
        // Ručně psaný text (např. důvod KO, zamítnutí)
        $nova_poznamka = "\n[{$kdo} - {$datum_dnes}]: {$poznamka_vstup}";
    } else {
        // Kliknutí na předpřipravená tlačítka
        $nova_poznamka = "\n⚙️ Systém: [{$kdo} - {$datum_dnes}]: {$poznamka_vstup}";
    }
    $poznamka_final = mysqli_real_escape_string($conn, $nova_poznamka);
}

if ($id > 0) {
    // Vazby na požadavek a surovinu
    $res_info = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_nabidky WHERE id = $id");
    $row_info = mysqli_fetch_assoc($res_info);
    $id_pozadavek = $row_info['id_pozadavek'] ?? 0;

    $id_surovina = 0;
    if ($id_pozadavek) {
        $res_sur = mysqli_query($conn, "SELECT id_surovina FROM pozadavky WHERE id = $id_pozadavek");
        $row_sur = mysqli_fetch_assoc($res_sur);
        $id_surovina = $row_sur['id_surovina'] ?? 0;
    }

    // 1. AKTUALIZACE NABÍDKY
    if ($status === 'no_change') {
        $sql = "UPDATE pozadavky_nabidky SET 
                poznamka_cena = CONCAT(IFNULL(poznamka_cena,''), '$poznamka_final'), 
                sarze = IF('$sarze' != '', '$sarze', sarze),
                updated_at = NOW() 
                WHERE id = $id";
    } else {
        $new_st = intval($status);
        $sql = "UPDATE pozadavky_nabidky SET 
                id_status = $new_st, 
                poznamka_cena = CONCAT(IFNULL(poznamka_cena,''), '$poznamka_final'), 
                pozadovane_mnozstvi = IF('$qty' != '', '$qty', pozadovane_mnozstvi),
                sarze = IF('$sarze' != '', '$sarze', sarze),
                updated_at = NOW() 
                WHERE id = $id";
    }
    mysqli_query($conn, $sql);

    // Pokud se jedná o testy, rovnou zakládáme záznam do technologických testů
    if (intval($status) == 10 || intval($status) == 4) {
        $check = mysqli_query($conn, "SELECT id FROM technologicke_testy WHERE id_nabidka = $id");
        if (mysqli_num_rows($check) == 0) mysqli_query($conn, "INSERT INTO technologicke_testy (id_nabidka) VALUES ($id)");
    }

    // Zápis kódů IS při finálním schválení
    if (intval($status) == 6 && $id_surovina > 0) {
        mysqli_query($conn, "UPDATE suroviny SET skupzbo = '$skupzbo', regcis = '$regcis' WHERE id = $id_surovina");
    }

    // Synchronizace hlavního statusu požadavku
    $new_main_status = null;
    if (in_array((int)$status, [3, 8, 9, 11, 12, 13])) $new_main_status = 3;
    elseif (in_array((int)$status, [10, 4])) $new_main_status = 10;
    elseif ((int)$status == 6) $new_main_status = 6;

    if ($new_main_status !== null && $id_pozadavek) {
        mysqli_query($conn, "UPDATE pozadavky SET id_status = $new_main_status WHERE id = $id_pozadavek");
    }

    // --- START: CHYTRÉ TELEGRAM NOTIFIKACE ---
    include_once("telegram.php");

    $info_q = mysqli_query($conn, "SELECT s.nazev as sur_nazev, d.nazev as dod_nazev 
                               FROM pozadavky_nabidky pn 
                               JOIN pozadavky p ON pn.id_pozadavek = p.id 
                               JOIN suroviny s ON p.id_surovina = s.id 
                               LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id 
                               WHERE pn.id = " . $id);

    if ($info_q && $info_r = mysqli_fetch_assoc($info_q)) {
        $sur = htmlspecialchars($info_r['sur_nazev']);
        $dod = htmlspecialchars($info_r['dod_nazev'] ? $info_r['dod_nazev'] : 'Neznámý dodavatel');
        $stav = (int)$status;
        $pozn = htmlspecialchars($poznamka_vstup);
        $qty_html = htmlspecialchars($qty);

        // GENERACE ODKAZU
        $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
        $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
        $link = "\n\n👉 <a href='" . $base_url . "/index.php?Pozadavek=1&req_id=" . $id_pozadavek . "'>Otevřít detail v systému</a>";

        if ($stav == 3) {
            $msg = "✅ <b>VÝVOJ: Cena byla schválena!</b>\n\n";
            $msg .= "<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod\n<b>Schválil/a:</b> $kdo\n";
            if (!empty($pozn)) $msg .= "<b>Poznámka:</b> $pozn\n";
            $msg .= "\n<i>Nyní je potřeba vyžádat a nahrát TDS (specifikaci).</i>";
            sendTelegram($msg . $link, 'nakup');

        } elseif ($stav == 12) {
            $msg = "🔍 <b>KVALITA: Čeká se na kontrolu dokumentů</b>\n<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod\n<b>Posunul/a:</b> $kdo\n<b>Poznámka:</b> $pozn";
            sendTelegram($msg . $link, 'kvalita');

            $msg2 = "ℹ️ <b>VÝVOJ (FYI): Nákup nahrál novou specifikaci (TDS)</b>\n<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod\n<i>Zatím to schvaluje Kvalita, ale už můžete mrknout, jestli dodali nutriční hodnoty.</i>";
            sendTelegram($msg2 . $link, 'vyvoj');

        } elseif ($stav == 13) {
            $msg = "📊 <b>VÝVOJ: Kvalita schválila bezpečnost TDS!</b>\n<i>Teď je řada na vás. Prosím zkontrolujte, zda TDS obsahuje nutriční hodnoty.</i>\n<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod\n<b>Schválil/a:</b> $kdo";
            sendTelegram($msg . $link, 'vyvoj');

        } elseif ($stav == 8) {
            $msg = "📦 <b>NÁKUP: Vývoj i Kvalita schválili TDS! Prosba o objednání fyzického vzorku</b>\n<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod\n<b>Požadované množství:</b> $qty_html\n<b>Poznámka:</b> $pozn";
            sendTelegram($msg . $link, 'nakup');

        } elseif ($stav == 10 || $stav == 4) {
            $msg = "🧪 <b>VÝVOJ/LAB: Vzorek dorazil, čeká se na testy!</b>\n<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod\n<b>Posunul/a:</b> $kdo\n<b>Poznámka:</b> $pozn";
            sendTelegram($msg . $link, 'vyvoj');

        } elseif ($stav == 6) {
            $msg = "✅ <b>NÁKUP: Surovina byla schválena laboratoří!</b>\n<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod\n<b>Test provedl/a:</b> $kdo\n<i>Můžete začít objednávat naostro.</i>";
            sendTelegram($msg . $link, 'nakup');

        } elseif ($stav == 5 || $stav == 7) {
            $msg = "❌ <b>ZAMÍTNUTO (KO)</b>\n<b>Surovina:</b> $sur\n<b>Dodavatel:</b> $dod\n<b>Zamítl/a:</b> $kdo\n<b>Důvod:</b> $pozn";
            sendTelegram($msg . $link, 'nakup');
            // Můžeme poslat info i vývoji, ať ví, že to padlo
            sendTelegram($msg . $link, 'vyvoj');
        }
    }
    // --- KONEC: CHYTRÉ TELEGRAM NOTIFIKACE ---
// ========================================================
    // NOVÉ: ZÁPIS DO UNIFIED TIMELINE (HISTORIE)
    // ========================================================
    // a) Pokud uživatel napsal manuální poznámku (důvod zamítnutí, nebo jen text), uložíme jako komentář
    if (!empty($poznamka_vstup) && strpos($poznamka_vstup, 'Systémová akce:') === false) {
        zapis_do_historie($conn, $id_pozadavek, $id, 'komentar', $poznamka_vstup);
    }

    // b) Pokud se jedná o posun statusu
    if ($status !== 'no_change') {
        $nazev_akce = "Změna stavu";
        // Pokusíme se vyčíst hezčí název z té automatické systémové poznámky
        if (strpos($poznamka_vstup, 'Systémová akce:') !== false) {
            $nazev_akce = str_replace('Systémová akce: ', '', $poznamka_vstup);
        }

        // Zde ideálně chceme i název stavu, ale prozatím logujeme IDs nebo název akce
        zapis_do_historie($conn, $id_pozadavek, $id, 'status', $nazev_akce, '', $status);
    }
    // ========================================================
    @file_put_contents('last_change.txt', time());
    echo "OK";
}
ob_end_flush();
?>