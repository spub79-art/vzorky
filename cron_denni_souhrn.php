<?php
// cron_denni_souhrn.php
// Tento skript se pouští automaticky (např. v 8:00 ráno) přes CRON na serveru

include_once("includes/db_connect.php");
@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

// NASTAVENÍ E-MAILU
$to_email = "nakup@tvojefirma.cz"; // ZDE UPRAV E-MAIL ODDĚLENÍ
$subject = "🔔 Ranní přehled: Požadavky k řešení (Nákup)";
$BASE_URL = "https://system.tvojefirma.cz/"; // ZDE UPRAV URL SYSTÉMU

// Stejný dotaz, jaký pohání nástěnku
$sql = "SELECT p.*, s.nazev AS surovina_nazev, 
        GROUP_CONCAT(CONCAT(IFNULL(pn.id_status, '0')) SEPARATOR '|') as nabidky_statusy
        FROM pozadavky p 
        LEFT JOIN suroviny s ON p.id_surovina = s.id 
        LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
        GROUP BY p.id HAVING p.id_status != 6 ORDER BY p.datumPozadavek ASC";

$result = mysqli_query($conn, $sql);

$urgentni = [];
$lezaky = [];
$vyzadano = [];

$ted = time();

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Zjištění, zda je to zrušené
        if (in_array((int)$row['id_status'], [5, 7])) continue;

        // Logika Fáze 1 (Zjišťujeme, zda to visí na Nákupu)
        $p1_active = true;
        if (!empty($row['nabidky_statusy'])) {
            $has_active_offer = false;
            foreach (explode('|', $row['nabidky_statusy']) as $s_id) {
                if ($s_id != '0' && !in_array((int)$s_id, [5, 7])) {
                    $has_active_offer = true;
                    $p1_active = false; // Má platnou nabídku, už to není primárně Fáze 1
                    break;
                }
            }
            if (!$has_active_offer) $p1_active = true; // Všechny nabídky jsou KO
        }

        // Pokud to nevisí v 1. fázi, přeskočíme
        if (!$p1_active) continue;

        // Máme požadavek ve Fázi 1! Rozřadíme ho:
        $stari_hodin = ($ted - strtotime($row['datumPozadavek'])) / 3600;

        // 1. Je urgentní? (Má prioritu 1)
        if ($row['priorita'] == 1) {
            $urgentni[] = $row;
        }
        // 2. Je to ležák? (Starší než 3 dny)
        elseif ($stari_hodin > 72) {
            $lezaky[] = $row;
        }
    }
}

// Zjištění "Vyžádaných dohledání" za posledních 24 hodin (z historie)
$vcera = date('Y-m-d H:i:s', strtotime('-24 hours'));
$q_ping = mysqli_query($conn, "SELECT h.id_pozadavek, s.nazev as surovina_nazev 
                               FROM historie_pozadavku h 
                               JOIN pozadavky p ON h.id_pozadavek = p.id
                               JOIN suroviny s ON p.id_surovina = s.id
                               WHERE h.typ_zaznamu = 'ping_nakup' 
                               AND h.vytvoreno >= '$vcera'
                               AND p.id_status NOT IN (5,6,7)
                               GROUP BY h.id_pozadavek");
if ($q_ping) {
    while ($r = mysqli_fetch_assoc($q_ping)) {
        $vyzadano[] = $r;
    }
}

// Pokud není nic k řešení, nebudeme spamovat e-mail
if (empty($urgentni) && empty($lezaky) && empty($vyzadano)) {
    die("Vše je čisté, nic neodesílám.");
}

// VYTVOŘENÍ HTML TĚLA E-MAILU
$html = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>";
$html .= "<div style='max-width: 600px; margin: 0 auto; border: 1px solid #e3e3e3; border-radius: 8px; overflow: hidden;'>";

// Hlavička
$html .= "<div style='background-color: #2c3e50; color: #fff; padding: 15px 20px;'>";
$html .= "<h2 style='margin: 0;'>Ranní svodka: Nákup</h2>";
$html .= "<p style='margin: 5px 0 0 0; font-size: 13px; color: #cbd5e1;'>Automatický přehled úkolů ve Fázi 1</p>";
$html .= "</div>";

$html .= "<div style='padding: 20px;'>";

// Sekce: URGENTNÍ
if (!empty($urgentni)) {
    $html .= "<h3 style='color: #d9534f; border-bottom: 2px solid #d9534f; padding-bottom: 5px;'>🚨 URGENTNÍ POŽADAVKY</h3>";
    $html .= "<ul style='padding-left: 20px;'>";
    foreach ($urgentni as $u) {
        $link = $BASE_URL . "index.php?req_id=" . $u['id'];
        $html .= "<li style='margin-bottom: 5px;'><strong>" . htmlspecialchars($u['surovina_nazev']) . "</strong> ";
        $html .= "<a href='$link' style='color: #337ab7; font-size: 13px; text-decoration: none;'>[Otevřít detail]</a></li>";
    }
    $html .= "</ul>";
}

// Sekce: VYŽÁDÁNO ZNOVU
if (!empty($vyzadano)) {
    $html .= "<h3 style='color: #5bc0de; border-bottom: 2px solid #5bc0de; padding-bottom: 5px;'>🔔 UŽIVATELÉ VYŽÁDALI DALŠÍ NABÍDKU</h3>";
    $html .= "<ul style='padding-left: 20px;'>";
    foreach ($vyzadano as $v) {
        $link = $BASE_URL . "index.php?req_id=" . $v['id_pozadavek'];
        $html .= "<li style='margin-bottom: 5px;'><strong>" . htmlspecialchars($v['surovina_nazev']) . "</strong> ";
        $html .= "<a href='$link' style='color: #337ab7; font-size: 13px; text-decoration: none;'>[Otevřít detail]</a></li>";
    }
    $html .= "</ul>";
}

// Sekce: LEŽÁKY
if (!empty($lezaky)) {
    $html .= "<h3 style='color: #f0ad4e; border-bottom: 2px solid #f0ad4e; padding-bottom: 5px;'>⏳ ČEKÁ SE NA NABÍDKU (Více než 3 dny)</h3>";
    $html .= "<ul style='padding-left: 20px;'>";
    foreach ($lezaky as $l) {
        $link = $BASE_URL . "index.php?req_id=" . $l['id'];
        $stari = floor((time() - strtotime($l['datumPozadavek'])) / 86400);
        $html .= "<li style='margin-bottom: 5px;'><strong>" . htmlspecialchars($l['surovina_nazev']) . "</strong> <span style='color: #777; font-size: 12px;'>(čekačka $stari dní)</span> ";
        $html .= "<a href='$link' style='color: #337ab7; font-size: 13px; text-decoration: none;'>[Otevřít detail]</a></li>";
    }
    $html .= "</ul>";
}

$html .= "<br><div style='text-align: center; margin-top: 20px;'>";
$html .= "<a href='$BASE_URL' style='background-color: #337ab7; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;'>Přejít do systému</a>";
$html .= "</div>";

$html .= "</div>"; // Konec padding obsahu
$html .= "<div style='background-color: #f8f9fa; text-align: center; padding: 10px; font-size: 11px; color: #999; border-top: 1px solid #e3e3e3;'>Tento e-mail byl vygenerován automaticky. Neodpovídejte na něj.</div>";
$html .= "</div></body></html>";

// HLAVIČKY PRO HTML E-MAIL S UTF-8
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: Vzorkovna <noreply@tvojefirma.cz>" . "\r\n"; // Můžeš změnit

// ODESLÁNÍ
if (mail($to_email, $subject, $html, $headers)) {
    echo "E-mail úspěšně odeslán.";
} else {
    echo "Chyba při odesílání e-mailu.";
}
?>