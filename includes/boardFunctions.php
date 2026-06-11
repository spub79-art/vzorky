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

// =========================================================================
// VYKRESLENÍ ŠTÍTKŮ (BIO, Vegan, atd.)
// =========================================================================
/**
 * Množství zadané vývojem (při schválení ceny / objednávce vzorku).
 */
function formatPozadovaneMnozstvi($qty) {
    $qty = trim((string)($qty ?? ''));
    return $qty !== '' ? $qty : null;
}

/**
 * Množství zadané při zakládání požadavku (sloupce Mnozstvi + mj).
 */
function formatPozadavekMnozstvi($row) {
    $m = $row['Mnozstvi'] ?? $row['mnozstvi'] ?? null;
    if ($m === null || $m === '' || (float)$m == 0) return null;
    $mj = trim((string)($row['mj'] ?? 'kg'));
    $num = (float)$m;
    $formatted = (floor($num) == $num)
        ? (string)(int)$num
        : rtrim(rtrim(number_format($num, 3, ',', ' '), '0'), ',');
    return $formatted . ($mj !== '' ? ' ' . $mj : '');
}

/** Nabídka už prošla schválením ceny — měla by mít vyplněnou poptávku vývoje. */
function nabidkaPotrebujePoptavku($status_id) {
    return in_array((int)$status_id, [3, 4, 6, 8, 9, 10, 11, 12, 13]);
}

/**
 * Shrnutí poptávek vývoje z pole nabídek (včetně chybějících hodnot).
 */
function summarizePoptavkyVyvoje($offers) {
    $lines = [];
    foreach ($offers as $off) {
        if (empty($off['id'])) continue;
        $st = (int)($off['id_status'] ?? 0);
        if (in_array($st, [5, 7])) continue;

        $dod = htmlspecialchars($off['dodavatel_nazev'] ?? 'Dodavatel');
        $qty = formatPozadovaneMnozstvi($off['pozadovane_mnozstvi'] ?? '');

        if ($st == 2) {
            $lines[] = "<strong>$dod</strong>: <span class='text-muted'>čeká na CENA OK</span>";
        } elseif ($qty !== null) {
            $lines[] = "<strong>$dod</strong>: " . htmlspecialchars($qty);
        } elseif (nabidkaPotrebujePoptavku($st)) {
            $lines[] = "<strong>$dod</strong>: <span class='text-danger'>⚠ nezadáno</span>";
        }
    }
    return $lines;
}

function renderBadges($row) {
    if (isset($row['priorita']) && $row['priorita'] == 1) {
        echo '<span class="badge-modern badge-urgent"><i class="glyphicon glyphicon-flash"></i> URGENTNÍ</span>';
    }
    if (isset($row['bio']) && $row['bio'] == 1) {
        echo '<span class="badge-modern badge-bio"><i class="glyphicon glyphicon-leaf"></i> BIO</span>';
    }
    if (isset($row['vegan']) && $row['vegan'] == 1) {
        echo '<span class="badge-modern badge-vegan"><i class="glyphicon glyphicon-apple"></i> Vegan</span>';
    }
    if (isset($row['bezlepek']) && $row['bezlepek'] == 1) {
        echo '<span class="badge-modern badge-bezlepek"><i class="glyphicon glyphicon-grain"></i> Bezlepek</span>';
    }
    if (isset($row['kosher']) && $row['kosher'] == 1) {
        echo '<span class="badge-modern badge-kosher">Kosher</span>';
    }
    if (isset($row['halal']) && $row['halal'] == 1) {
        echo '<span class="badge-modern badge-halal">Halal</span>';
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
// =========================================================================
// VYKRESLENÍ JEDNOHO ŘÁDKU HISTORIE (DRY PRINCIP)
// =========================================================================
function renderHistoryRow($h, $is_adm, $current_uid) {
    $is_system = !in_array($h['typ_zaznamu'], ['komentar', 'komentar_urgentni']);
    $is_urgent_msg = ($h['typ_zaznamu'] === 'komentar_urgentni');
    $is_mine = ($current_uid > 0 && $h['id_user'] == $current_uid);
    $can_delete = ($is_mine || $is_adm);

    // Ikony podle typu záznamu
    $icon = 'glyphicon-cog text-muted';
    if ($h['typ_zaznamu'] == 'urgence') $icon = 'glyphicon-flash text-warning';
    if ($h['typ_zaznamu'] == 'poptavka') $icon = 'glyphicon-scale text-info';
    if ($h['typ_zaznamu'] == 'zalozeni') $icon = 'glyphicon-plus text-success';
    if ($h['typ_zaznamu'] == 'soubor') $icon = 'glyphicon-file text-info';
    if ($h['typ_zaznamu'] == 'status') {
        if (in_array($h['nova_hodnota'], ['5', '7'])) $icon = 'glyphicon-ban-circle text-danger';
        elseif (in_array($h['nova_hodnota'], ['6', '8'])) $icon = 'glyphicon-ok-sign text-success';
        else $icon = 'glyphicon-share-alt text-primary';
    }
    if (!$is_system) {
        $icon = $is_urgent_msg ? 'glyphicon-exclamation-sign text-danger' : 'glyphicon-pencil text-primary';
    }

    // Přiřazení čistých CSS tříd místo inline stylů
    $row_class = $is_system ? 'sys-text' : 'usr-text';
    if (isset($h['skryto']) && $h['skryto'] == 1) {
        $row_class .= ' is-hidden';
        $is_hidden = true;
    } else {
        $is_hidden = false;
    }

    $text_class = $is_urgent_msg ? 'history-urgent-text' : '';

    ?>
    <div class="history-row <?= $row_class ?>">
        <i class="glyphicon <?= $icon ?> history-row-icon"></i>
        [<?= htmlspecialchars($h['jmeno_user']) ?> - <?= date('j.n. H:i', strtotime($h['vytvoreno'])) ?>]
        <?= $is_hidden ? '<b class="text-danger" style="text-decoration: none;">(SKRYTO)</b>' : '' ?>:

        <span class="<?= $text_class ?>" id="comment_text_<?= $h['id'] ?>"><?= nl2br(htmlspecialchars($h['text_hodnota'])) ?></span>

        <?php if ($can_delete && !$is_hidden): ?>
            <span class="history-actions">
                <?php if (!$is_system): ?>
                    <i class="glyphicon glyphicon-pencil text-primary history-action-btn btn-edit-history" data-id="<?= $h['id'] ?>" data-text="<?= htmlspecialchars($h['text_hodnota'], ENT_QUOTES) ?>" title="Upravit poznámku"></i>
                <?php endif; ?>
                <i class="glyphicon glyphicon-remove text-danger history-action-btn btn-delete-history" data-id="<?= $h['id'] ?>" title="Skrýt záznam"></i>
            </span>
        <?php endif; ?>
    </div>
    <?php
}
?>