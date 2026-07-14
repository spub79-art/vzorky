<?php
include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("permissions.php");
if (session_status() === PHP_SESSION_NONE) session_start();

requireNakupAccess();

$id_pozadavek = (int)$_POST['id_pozadavek'];
$dodavatel_raw = trim($_POST['dodavatel_raw']);
$cena_clean = floatval(str_replace(',', '.', $_POST['cena'] ?? '0'));
$bez_ceny = !empty($_POST['bez_ceny']);
$mena = $_POST['mena'] ?? 'CZK'; // CHYTÁME MĚNU

if ($bez_ceny) {
    $cena_clean = 0;
    $id_status_nova = STATUS_NABIDKA_BEZ_CENY;
} elseif ($cena_clean <= 0) {
    die('Zadejte cenu, nebo zaškrtněte „Zatím bez ceny“.');
} else {
    $id_status_nova = 2;
}

$moq_raw = trim($_POST['moq_qty'] ?? '');
$moq_qty = ($moq_raw !== '') ? floatval(str_replace(',', '.', $moq_raw)) : null;
$moq_mj = trim($_POST['moq_mj'] ?? 'kg');

$poznamka_vstup = trim($_POST['poznamka_nakup'] ?? '');
$user_id = !empty($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
$user_name = $_SESSION['username'] ?? 'Neznámý';
$datum = date("j.n. H:i");

$finalni_poznamka = !empty($poznamka_vstup) ? "[Nákup - {$user_name} - {$datum}]: {$poznamka_vstup}" : "";

// Dodavatel
$id_dodavatel = 0;
$stmt = $conn->prepare("SELECT id FROM dodavatele WHERE nazev = ?");
$stmt->bind_param("s", $dodavatel_raw);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $id_dodavatel = $row['id'];
} else {
    $stmt_ins = $conn->prepare("INSERT INTO dodavatele (nazev) VALUES (?)");
    $stmt_ins->bind_param("s", $dodavatel_raw);
    $stmt_ins->execute();
    $id_dodavatel = $conn->insert_id;
}

// SQL OPRAVENO: dynamická měna místo 'CZK' a 8 parametrů místo 7
$sql = "INSERT INTO pozadavky_nabidky 
        (id_pozadavek, id_dodavatel, cena_nabidka, moq_mnozstvi, moq_mj, mena, id_status, id_user_posledni_zmena, poznamka_nakup) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_off = $conn->prepare($sql);
$stmt_off->bind_param("iiddssiis", $id_pozadavek, $id_dodavatel, $cena_clean, $moq_qty, $moq_mj, $mena, $id_status_nova, $user_id, $finalni_poznamka);

if ($stmt_off->execute()) {

    // Automatické přiřazení nákupčího při vložení nabídky
    if ($user_id) {
        $q_assign = mysqli_query($conn, "SELECT id_nakupci FROM pozadavky WHERE id = $id_pozadavek LIMIT 1");
        if ($q_assign && ($row_assign = mysqli_fetch_assoc($q_assign))) {
            $old_nakupci = (int)($row_assign['id_nakupci'] ?? 0);
            if ($old_nakupci !== $user_id) {
                mysqli_query($conn, "UPDATE pozadavky SET id_nakupci = $user_id WHERE id = $id_pozadavek");
                $assign_text = $old_nakupci > 0
                    ? 'Přiřazení nákupčího změněno automaticky při vložení nabídky.'
                    : 'Požadavek automaticky převzat při vložení nabídky.';
                zapis_do_historie($conn, $id_pozadavek, 0, 'prirazeni', $assign_text);
            }
        }
    }

    // --- START: CHYTRÉ TELEGRAM NOTIFIKACE (Nová nabídka) ---
    include_once("telegram.php");
    $new_offer_id = $conn->insert_id;

    $sur_nazev = "Neznámá surovina";
    $q_sur = mysqli_query($conn, "SELECT s.nazev FROM pozadavky p JOIN suroviny s ON p.id_surovina = s.id WHERE p.id = $id_pozadavek");
    if ($q_sur && $r_sur = mysqli_fetch_assoc($q_sur)) {
        $sur_nazev = $r_sur['nazev'];
    }

    $kdo = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Někdo z nákupu');
    $is_dev = (strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
    $base_url = $is_dev ? "https://docs.lifefood.eu/dev-vzorky" : "https://docs.lifefood.eu/vzorky";
    $link = $base_url . "/index.php?Pozadavek=1&req_id=" . $id_pozadavek;

    if ($bez_ceny) {
        $msg = "📄 <b>DOKUMENTACE BEZ CENY — nová nabídka k posouzení</b>\n\n";
        $msg .= "📌 <b>ID:</b> Požadavek #$id_pozadavek | Nabídka #$new_offer_id\n";
        $msg .= "<b>Surovina:</b> " . htmlspecialchars($sur_nazev) . "\n";
        $msg .= "<b>Dodavatel:</b> " . htmlspecialchars($dodavatel_raw) . "\n";
        $msg .= "<b>Přidal/a:</b> " . htmlspecialchars($kdo) . "\n";
        $msg .= "\n<i>Cena zatím není — lze nahrát TDS. Před předáním kvalitě musí Nákup doplnit cenu a vývoj schválit CENA OK.</i>";
        $msg .= "\n\n👉 <a href='" . $link . "'>Zobrazit nabídku v systému</a>";
        sendTelegram($msg, 'vyvoj');
        sendTelegram($msg, 'kvalita');
    } else {
        $cena_formatovana = number_format($cena_clean, (floor($cena_clean) == $cena_clean ? 0 : 2), ',', ' ') . " " . $mena;
        $msg = "💰 <b>VÝVOJ: Byla přidána nová nabídka!</b>\n\n";
        $msg .= "📌 <b>ID:</b> Požadavek #$id_pozadavek | Nabídka #$new_offer_id\n";
        $msg .= "<b>Surovina:</b> " . htmlspecialchars($sur_nazev) . "\n";
        $msg .= "<b>Dodavatel:</b> " . htmlspecialchars($dodavatel_raw) . "\n";
        $msg .= "<b>Cena:</b> " . $cena_formatovana . "\n";
        if ($moq_qty > 0) {
            $msg .= "<b>MOQ:</b> $moq_qty " . htmlspecialchars($moq_mj) . "\n";
        }
        $msg .= "<b>Přidal/a:</b> " . htmlspecialchars($kdo) . "\n";
        $msg .= "\n<i>Čeká se na schválení ceny (Tlačítko CENA OK).</i>";
        $msg .= "\n\n👉 <a href='" . $link . "'>Zobrazit nabídku v systému</a>";
        sendTelegram($msg, 'vyvoj');
    }
    // --- KONEC: CHYTRÉ TELEGRAM NOTIFIKACE ---

    file_put_contents('last_change.txt', time());
    echo "OK";
} else {
    echo "Chyba: " . $stmt_off->error;
}
?>