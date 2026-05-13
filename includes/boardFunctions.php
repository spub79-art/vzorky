<?php
// Pomocné funkce pro vizualizaci nástěnky

// Přidali jsme parametr $is_spread (ve výchozím stavu true, abychom nic nerozbili, než to propojíme)
function getUniqueColor($id, $is_spread = true) {
    if (!$is_spread) {
        return '#e2e6ea'; // Neutrální jemná šedá pro nabídky, co jsou poslušně v jedné fázi
    }

    $hash = md5('salt_lf_' . $id);
    return sprintf("#%02x%02x%02x",
        (int)((hexdec(substr($hash, 0, 2)) + 255) / 2),
        (int)((hexdec(substr($hash, 2, 2)) + 255) / 2),
        (int)((hexdec(substr($hash, 4, 2)) + 255) / 2)
    );
}


// Pomocné funkce pro vykreslování prvků na nástěnce

function renderBadges($row) {
    if ($row['priorita'] == 1) echo '<span class="label label-danger req-badge"><i class="glyphicon glyphicon-flash"></i> URGENT</span>';
    if ($row['bio'] == 1) echo '<span class="label label-success req-badge">BIO</span>';
    // ZMĚNA: BL -> BEZ LEPKU
    if ($row['bezlepek'] == 1) echo '<span class="label label-warning req-badge">BEZ LEPKU</span>';
    if ($row['vegan'] == 1) echo '<span class="label label-success req-badge">VEGAN</span>';
    if ($row['kosher'] == 1) echo '<span class="label rd-badge-kosher req-badge">KOSHER</span>';
    if ($row['halal'] == 1) echo '<span class="label rd-badge-halal req-badge">HALAL</span>';
}

function getPhaseName($phase) {
    switch ($phase) {
        case 1: return "1. VÝBĚR";
        case 2: return "2. DOKUMENTY";
        case 3: return "3. TESTOVÁNÍ";
        default: return "NEZNÁMÁ FÁZE";
    }
}

// =========================================================================
// UNIVERZÁLNÍ ZÁPIS DO HISTORIE POŽADAVKU (UNIFIED TIMELINE)
// =========================================================================
function zapis_do_historie($conn, $id_pozadavek, $id_nabidka, $typ_zaznamu, $text_hodnota = '', $stara_hodnota = '', $nova_hodnota = '') {
    $id_user = $_SESSION['uid'] ?? 0;
    $jmeno = is_array($_SESSION['username']) ? $_SESSION['username'][0] : ($_SESSION['username'] ?? 'Systém');

    // Ochrana proti SQL injection
    $text_db = mysqli_real_escape_string($conn, $text_hodnota);
    $stara_db = mysqli_real_escape_string($conn, $stara_hodnota);
    $nova_db = mysqli_real_escape_string($conn, $nova_hodnota);

    $sql = "INSERT INTO historie_pozadavku 
            (id_pozadavek, id_nabidka, typ_zaznamu, id_user, jmeno_user, text_hodnota, stara_hodnota, nova_hodnota) 
            VALUES 
            ($id_pozadavek, $id_nabidka, '$typ_zaznamu', $id_user, '$jmeno', '$text_db', '$stara_db', '$nova_db')";

    mysqli_query($conn, $sql);
}
// =========================================================================
// POMOCNÉ FUNKCE PRO CELÝ SYSTÉM
// =========================================================================

// Vypočítá kontrastní barvu textu (bílá/tmavá) k libovolnému pozadí
function getContrastColor($hexcolor) {
    $hexcolor = trim($hexcolor, '#');
    if (strlen($hexcolor) == 3) {
        $r = hexdec(substr($hexcolor,0,1).substr($hexcolor,0,1));
        $g = hexdec(substr($hexcolor,1,1).substr($hexcolor,1,1));
        $b = hexdec(substr($hexcolor,2,1).substr($hexcolor,2,1));
    } else {
        $r = hexdec(substr($hexcolor,0,2));
        $g = hexdec(substr($hexcolor,2,2));
        $b = hexdec(substr($hexcolor,4,2));
    }
    $yiq = (($r*299)+($g*587)+($b*114))/1000;
    return ($yiq >= 140) ? '#2c3e50' : '#ffffff';
}

// Vygeneruje hotový HTML štítek statusu přímo z číselníku v databázi
function getStatusHtml($conn, $status_id) {
    static $status_cache = null;

    // Načteme číselník jen jednou při prvním zavolání, pak už to jede z paměti (cache)
    if ($status_cache === null) {
        $status_cache = [];
        $res = mysqli_query($conn, "SELECT id, nazev, barva_hex FROM ciselnik_statusu");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $status_cache[(int)$row['id']] = $row;
            }
        }
    }

    $id = (int)$status_id;
    if (isset($status_cache[$id])) {
        $bg = htmlspecialchars($status_cache[$id]['barva_hex']);
        $name = htmlspecialchars($status_cache[$id]['nazev']);
        $color = getContrastColor($bg);
        return '<span class="badge" style="background-color: ' . $bg . '; color: ' . $color . ';">' . $name . '</span>';
    }

    return '<span class="badge" style="background-color: #999;">Neznámý stav</span>';
}
?>