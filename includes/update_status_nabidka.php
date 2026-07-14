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

    $gate_q = mysqli_query($conn, "SELECT id_status, cena_nabidka, pozadovane_mnozstvi, poznamka_cena FROM pozadavky_nabidky WHERE id = $id");
    $gate_row = mysqli_fetch_assoc($gate_q) ?: [];
    $cur_st = (int)($gate_row['id_status'] ?? 0);

    // Tvrdá brána: CENA OK (2→3) jen s cenou a množstvím
    if ((int)$status === 3) {
        if ((float)($gate_row['cena_nabidka'] ?? 0) <= 0) {
            ob_end_clean();
            die('Nelze schválit cenu: chybí cena. Nákup musí cenu doplnit.');
        }
        if ($qty === '' && !nabidkaMaCenuSchvalenouVyvojem($gate_row['pozadovane_mnozstvi'] ?? '')) {
            ob_end_clean();
            die('CENA OK vyžaduje zadání množství vzorku.');
        }
        if (!in_array($cur_st, [2, 9], true)) {
            ob_end_clean();
            die('Schválení ceny jen ze stavu „čeká na schválení ceny“.');
        }
        if ($cur_st === 9 && !nabidkaMaCenuSchvalenouVyvojem($gate_row['pozadovane_mnozstvi'] ?? '')) {
            ob_end_clean();
            die('Nejdřív musí vývoj schválit cenu (CENA OK).');
        }
    }

    // Předat kvalitě jen po CENA OK (stav 3)
    if ((int)$status === 12) {
        if ($cur_st === STATUS_NABIDKA_BEZ_CENY) {
            ob_end_clean();
            die('Nejdřív doplnit cenu a nechat schválit vývojem (CENA OK).');
        }
        if (!nabidkaMaCenuSchvalenouVyvojem($gate_row['pozadovane_mnozstvi'] ?? '')) {
            ob_end_clean();
            die('Nejdřív musí vývoj schválit cenu (CENA OK) — chybí požadované množství.');
        }
        if (!in_array($cur_st, [3, 9], true)) {
            ob_end_clean();
            die('Předání kvalitě jen po schválení ceny vývojem.');
        }
    }

    // Kvalita OK jen pokud vývoj cenu už schválil
    if ((int)$status === 13) {
        if (!in_array($cur_st, [12, 9], true)) {
            ob_end_clean();
            die('Schválení kvality jen ze stavu kontroly TDS.');
        }
        if (!nabidkaMaCenuSchvalenouVyvojem($gate_row['pozadovane_mnozstvi'] ?? '')) {
            ob_end_clean();
            die('Nejdřív musí vývoj schválit cenu (CENA OK).');
        }
    }

    // Senzorika OK (8) jen z kontroly nutri ve stavu 13, po CENA OK
    if ((int)$status === 8) {
        if ($cur_st !== 13) {
            ob_end_clean();
            die('Schválení nutričních hodnot jen ze stavu kontroly TDS vývojem.');
        }
        if ((float)($gate_row['cena_nabidka'] ?? 0) <= 0) {
            ob_end_clean();
            die('Nelze pokračovat: chybí cena. Nákup musí doplnit cenu a vývoj ji schválit (CENA OK).');
        }
        if (!nabidkaMaCenuSchvalenouVyvojem($gate_row['pozadovane_mnozstvi'] ?? '')) {
            ob_end_clean();
            die('Nelze pokračovat: chybí množství ze schválení ceny. Vývoj musí nejdřív dát CENA OK.');
        }
    }

    // Návrat z DOPLNIT: zapamatovat, odkud jsme přišli (12 nebo 13)
    if ((int)$status === 9 && in_array($cur_st, [12, 13], true)) {
        $poznamka_final .= mysqli_real_escape_string($conn, "\n[WF_RETURN:$cur_st]");
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
    if (!mysqli_query($conn, $sql)) {
        ob_end_clean();
        die('Chyba DB při ukládání nabídky: ' . mysqli_error($conn));
    }

    // Zápis množství do historie (viditelné v timeline)
    if ($qty !== '' && $id_pozadavek) {
        zapis_do_historie($conn, $id_pozadavek, $id, 'poptavka', "Požadované množství vzorku: $qty");
    }

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
    if (in_array((int)$status, nabidkaFaze2Statusy())) $new_main_status = 3;
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