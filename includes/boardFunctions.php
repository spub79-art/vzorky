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
        <?php if(!empty($row['bezlepek'])): ?><span class="badge" style="background-color:#ffc107; color:#000; font-size:8px; padding: 2px 4px;">BL</span><?php endif; ?>

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
?>