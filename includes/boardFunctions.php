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

function renderBadges($row) { ?>
    <div style="display: flex; gap: 3px; flex-wrap: wrap; margin-top: 2px;">
        <?php if(!empty($row['bio'])): ?><span class="badge" style="background-color:#28a745; font-size:8px; padding: 2px 4px;">BIO</span><?php endif; ?>
        <?php if(!empty($row['vegan'])): ?><span class="badge" style="background-color:#17a2b8; font-size:8px; padding: 2px 4px;">VGN</span><?php endif; ?>
        <?php if(!empty($row['bezlepek'])): ?><span class="badge" style="background-color:#ffc107; color:#000; font-size:8px; padding: 2px 4px;">BEZ LEPKU</span><?php endif; ?>

        <?php if(!empty($row['kosher'])): ?><span class="badge" style="background-color:#6f42c1; font-size:8px; padding: 2px 4px;">KOSHER</span><?php endif; ?>

        <?php if(!empty($row['halal'])): ?><span class="badge" style="background-color:#009688; font-size:8px; padding: 2px 4px;">HALAL</span><?php endif; ?>

        <?php if(!empty($row['priorita']) && $row['priorita'] == 1): ?><span class="badge" style="background-color:#d9534f; font-size:8px; padding: 2px 4px;">URGENT</span><?php endif; ?>
    </div>
<?php }
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