<?php
// Pomocné funkce pro vizualizaci nástěnky

if (!defined('STATUS_NABIDKA_BEZ_CENY')) {
    define('STATUS_NABIDKA_BEZ_CENY', 14);
}
if (!defined('STATUS_NABIDKA_ODLOZENO')) {
    define('STATUS_NABIDKA_ODLOZENO', 15);
}

/** Statusy nabídky ve 2. fázi (dokumentace / TDS). */
function nabidkaFaze2Statusy() {
    return [3, 8, 9, 11, 12, 13, STATUS_NABIDKA_BEZ_CENY];
}

/** Zamítnutá nabídka (KO). Status 8 u nabídky = NUTRI OK — neplést s id_status 8 u požadavku (odloženo). */
function nabidkaJeZamitnuta($status_id) {
    return in_array((int)$status_id, [5, 7], true);
}

/** Nabídka odložena k ledu — není KO, lze oživit. */
function nabidkaJeOdlozena($status_id) {
    return (int)$status_id === STATUS_NABIDKA_ODLOZENO;
}

/** Skryté na nástěnce (KO nebo led) — zobrazí se po zapnutí filtru KO. */
function nabidkaJeSkryta($status_id) {
    return nabidkaJeZamitnuta($status_id) || nabidkaJeOdlozena($status_id);
}

/** SQL fragment pro aktivní nabídky (ne KO, ne led). */
function nabidka_sql_not_skryte() {
    return 'NOT IN (5, 7, ' . STATUS_NABIDKA_ODLOZENO . ')';
}

function nabidka_ma_led_columns($conn) {
    if (!function_exists('pf_has_column')) {
        @include_once(__DIR__ . '/portfolio_helpers.php');
    }
    return function_exists('pf_has_column') && pf_has_column($conn, 'pozadavky_nabidky', 'status_pred_ledem');
}

/**
 * Odešle jednu nabídku k ledu. Vrací false pokud nelze.
 */
function nabidka_odesli_k_ledu($conn, $offer_id, $poznamka, $req_id = 0) {
    $offer_id = (int)$offer_id;
    if ($offer_id <= 0) {
        return false;
    }
    $res = mysqli_query($conn, "SELECT * FROM pozadavky_nabidky WHERE id = $offer_id LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        return false;
    }
    $cur_st = (int)$row['id_status'];
    if ($cur_st === 6 || nabidkaJeSkryta($cur_st)) {
        return false;
    }
    $req_id = $req_id > 0 ? $req_id : (int)$row['id_pozadavek'];
    $has_led_col = nabidka_ma_led_columns($conn);
    $hist = mysqli_real_escape_string($conn, "🧊 Odloženo k ledu: $poznamka");
    $led = STATUS_NABIDKA_ODLOZENO;
    $delegace_clear = ($has_led_col && function_exists('wf_delegace_has_columns') && wf_delegace_has_columns($conn))
        ? 'wf_delegace_komu = NULL, wf_delegace_od = NULL, wf_delegace_duvod = NULL, wf_delegace_vytvoreno = NULL,'
        : '';

    if ($has_led_col) {
        $sql = "UPDATE pozadavky_nabidky SET
            status_pred_ledem = IF(status_pred_ledem IS NULL, id_status, status_pred_ledem),
            id_status = $led,
            $delegace_clear
            poznamka_cena = CONCAT(IFNULL(poznamka_cena,''), '\n⚙️ Systém: $hist'),
            updated_at = NOW()
            WHERE id = $offer_id";
    } else {
        $sql = "UPDATE pozadavky_nabidky SET id_status = $led,
            poznamka_cena = CONCAT(IFNULL(poznamka_cena,''), '\n⚙️ Systém: $hist'),
            updated_at = NOW()
            WHERE id = $offer_id";
    }
    if (!mysqli_query($conn, $sql)) {
        return false;
    }
    zapis_do_historie($conn, $req_id, $offer_id, 'status', $poznamka, (string)$cur_st, (string)$led);
    return true;
}

/** Odleduje nabídku — vrátí obnovený status nebo 0. */
function nabidka_odleduj($conn, $offer_id, $poznamka = '') {
    $offer_id = (int)$offer_id;
    $res = mysqli_query($conn, "SELECT * FROM pozadavky_nabidky WHERE id = $offer_id LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row || !nabidkaJeOdlozena($row['id_status'])) {
        return 0;
    }
    $req_id = (int)$row['id_pozadavek'];
    $restore = (int)($row['status_pred_ledem'] ?? 0);
    if ($restore <= 0 || in_array($restore, [5, 7, 6, STATUS_NABIDKA_ODLOZENO], true)) {
        $restore = 2;
    }
    $note = $poznamka !== '' ? $poznamka : 'Odledováno';
    $hist = mysqli_real_escape_string($conn, "🧊 Nabídka odledována (návrat ke stavu #$restore): $note");
    $has_led_col = nabidka_ma_led_columns($conn);

    if ($has_led_col) {
        mysqli_query($conn, "UPDATE pozadavky_nabidky SET id_status = $restore, status_pred_ledem = NULL,
            poznamka_cena = CONCAT(IFNULL(poznamka_cena,''), '\n⚙️ Systém: $hist'), updated_at = NOW() WHERE id = $offer_id");
    } else {
        mysqli_query($conn, "UPDATE pozadavky_nabidky SET id_status = $restore,
            poznamka_cena = CONCAT(IFNULL(poznamka_cena,''), '\n⚙️ Systém: $hist'), updated_at = NOW() WHERE id = $offer_id");
    }
    zapis_do_historie($conn, $req_id, $offer_id, 'status', $note, (string)STATUS_NABIDKA_ODLOZENO, (string)$restore);
    return $restore;
}

/** Počet aktivních / odledovaných sourozeneckých nabídek (bez sebe). */
function nabidka_sibling_counts(array $offers, $current_id) {
    $active = 0;
    $frozen = 0;
    foreach ($offers as $o) {
        if (empty($o['id']) || (int)$o['id'] === (int)$current_id) {
            continue;
        }
        $s = (int)($o['id_status'] ?? 0);
        if (nabidkaJeOdlozena($s)) {
            $frozen++;
        } elseif (!nabidkaJeSkryta($s)) {
            $active++;
        }
    }
    return ['active' => $active, 'frozen' => $frozen];
}

function wf_delegace_dept_label($dept) {
    $map = ['nakup' => 'Nákup', 'vyvoj' => 'Vývoj', 'kvalita' => 'Kvalita'];
    return $map[$dept] ?? $dept;
}

function wf_delegace_has_columns($conn) {
    if (!function_exists('pf_has_column')) {
        @include_once(__DIR__ . '/portfolio_helpers.php');
    }
    return function_exists('pf_has_column') && pf_has_column($conn, 'pozadavky_nabidky', 'wf_delegace_komu');
}

/** @return string SQL fragment pro SELECT nabídky */
function wf_delegace_sql_select($conn) {
    if (wf_delegace_has_columns($conn)) {
        return 'pn.wf_delegace_komu, pn.wf_delegace_od, pn.wf_delegace_duvod, pn.wf_delegace_vytvoreno,';
    }
    return 'NULL AS wf_delegace_komu, NULL AS wf_delegace_od, NULL AS wf_delegace_duvod, NULL AS wf_delegace_vytvoreno,';
}

function wf_delegace_is_active(array $offer) {
    $komu = trim($offer['wf_delegace_komu'] ?? '');
    return $komu !== '' && in_array($komu, ['nakup', 'kvalita', 'vyvoj'], true);
}

/**
 * Přepíše workflow actor podle aktivní delegace (Souhrn + nástěnka).
 *
 * @return array workflow pole
 */
function wf_delegace_apply_workflow(array $wf, array $offer) {
    if (!wf_delegace_is_active($offer)) {
        return $wf;
    }
    $komu = $offer['wf_delegace_komu'];
    $od = $offer['wf_delegace_od'] ?? '';
    $duvod = trim($offer['wf_delegace_duvod'] ?? '');
    $action = $duvod !== '' ? $duvod : 'Vyřešit předchozí krok';

    $wf['actor'] = $komu;
    $wf['actor_label'] = wf_delegace_dept_label($komu);
    $wf['dept_actions'] = ['nakup' => null, 'vyvoj' => null, 'kvalita' => null];
    $wf['dept_actions'][$komu] = $action;
    $wf['dept_next'] = ['nakup' => null, 'vyvoj' => null, 'kvalita' => null];
    if ($od !== '' && $od !== $komu) {
        $wf['dept_next'][$od] = 'Po vyřešení pokračovat ve workflow';
    }
    $wf['kontext_short'] = '↪ ' . wf_delegace_dept_label($od) . ' → ' . wf_delegace_dept_label($komu);
    return $wf;
}

/** HTML banner na kartě nabídky */
function wf_delegace_banner_html(array $offer) {
    if (!wf_delegace_is_active($offer)) {
        return '';
    }
    $komu = wf_delegace_dept_label($offer['wf_delegace_komu']);
    $od = wf_delegace_dept_label($offer['wf_delegace_od'] ?? '');
    $duvod = htmlspecialchars(trim($offer['wf_delegace_duvod'] ?? ''));
    $html = '<div class="offer-delegace-banner" title="Workflow odbočka — stav nabídky beze změny">';
    $html .= '<i class="glyphicon glyphicon-share-alt"></i> <strong>Předáno ' . htmlspecialchars($komu) . '</strong>';
    if ($od !== '') {
        $html .= ' <span class="text-muted">(od ' . htmlspecialchars($od) . ')</span>';
    }
    if ($duvod !== '') {
        $html .= '<br><span class="offer-delegace-reason">' . $duvod . '</span>';
    }
    $html .= '</div>';
    return $html;
}

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

/** Vývoj schválil cenu tlačítkem CENA OK (množství vzorku). */
function nabidkaMaCenuSchvalenouVyvojem($pozadovane_mnozstvi) {
    return formatPozadovaneMnozstvi($pozadovane_mnozstvi) !== null;
}

/** Kam se vrátit po doplnění dokumentace (status 9). */
function nabidkaStatusPoDoplneniDokumentace($poznamka_cena) {
    if (preg_match('/\[WF_RETURN:(\d+)\]/', (string)$poznamka_cena, $m)) {
        $st = (int)$m[1];
        if (in_array($st, [3, 12, 13], true)) {
            return $st;
        }
    }
    return 3;
}

/**
 * Přílohy nabídky — TDS (spec) a COA (lab).
 * @return array{0:bool,1:bool}
 */
function nabidka_parse_file_flags($seznam_souboru) {
    $has_spec = false;
    $has_lab = false;
    foreach (explode('^', (string)$seznam_souboru) as $f) {
        if ($f === '') continue;
        $pts = explode('~', $f);
        if (($pts[1] ?? '') === 'spec') $has_spec = true;
        elseif (($pts[1] ?? '') === 'lab') $has_lab = true;
    }
    return [$has_spec, $has_lab];
}

/** Pořadí nabídky ve workflow (pro odhad dokončených kroků). */
function nabidka_workflow_status_rank($st) {
    static $map = [
        14 => 10,
        2 => 20,
        9 => 25,
        3 => 30,
        12 => 40,
        13 => 50,
        8 => 60,
        11 => 70,
        10 => 80,
        4 => 80,
        6 => 100,
    ];
    return $map[(int)$st] ?? 0;
}

/** Definice kroků stepperu v detailu požadavku. */
function nabidka_workflow_step_defs() {
    return [
        ['id' => 'tds',     'label' => 'TDS',     'title' => 'Specifikace (TDS)',           'icon' => 'glyphicon-file'],
        ['id' => 'cena',    'label' => 'Cena',    'title' => 'Cena vložena',                'icon' => 'glyphicon-usd'],
        ['id' => 'cena_ok', 'label' => 'CENA OK', 'title' => 'Schváleno vývojem',           'icon' => 'glyphicon-ok-circle'],
        ['id' => 'kvalita', 'label' => 'Kvalita', 'title' => 'TDS schváleno kvalitou',      'icon' => 'glyphicon-certificate'],
        ['id' => 'nutri',   'label' => 'Nutri',   'title' => 'Nutriční schválení',          'icon' => 'glyphicon-leaf'],
        ['id' => 'vzorek',  'label' => 'Vzorek',  'title' => 'Vzorek objednán a dorazil',   'icon' => 'glyphicon-gift'],
        ['id' => 'coa',     'label' => 'COA',     'title' => 'Analýza vzorku (COA)',        'icon' => 'glyphicon-flask'],
        ['id' => 'test',    'label' => 'Test',    'title' => 'Technologický test (TEST OK)', 'icon' => 'glyphicon-flag'],
    ];
}

function nabidka_workflow_step_done($step_id, $st, $rank, $has_spec, $has_lab, $cena, $cena_ok_qty) {
    switch ($step_id) {
        case 'tds':     return $has_spec;
        case 'cena':    return $cena > 0;
        case 'cena_ok': return $cena_ok_qty !== null || $rank >= 40;
        case 'kvalita': return $rank >= 50;
        case 'nutri':   return $rank >= 60;
        case 'vzorek':  return $rank >= 80;
        case 'coa':     return $has_lab;
        case 'test':    return (int)$st === 6;
        default:        return false;
    }
}

function nabidka_hex_to_rgba($hex, $alpha = 0.08) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return 'rgba(108,117,125,' . (float)$alpha . ')';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . (float)$alpha . ')';
}

/** Barvy karty / řádku stepperu — stejná logika jako rd-offer-card v detailu. */
function nabidka_offer_card_colors(array $off) {
    $st = (int)($off['id_status'] ?? 0);
    $is_ko = nabidkaJeZamitnuta($st);
    $is_frozen = nabidkaJeOdlozena($st);
    $accent = $off['barva_hex'] ?? '#ccc';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
        $accent = '#ccc';
    }

    $bg = nabidka_hex_to_rgba($accent, 0.09);
    $opacity = '1';
    if ($is_ko) {
        $bg = '#fafafa';
        $opacity = '0.75';
    } elseif ($is_frozen) {
        $bg = '#f4f6f8';
        $accent = '#95a5a6';
        $opacity = '0.75';
    }

    return [
        'accent' => $accent,
        'bg' => $bg,
        'opacity' => $opacity,
    ];
}

/**
 * Stav kroků jedné nabídky pro stepper v detailu.
 * @return array{mode:string,steps:array,subtitle:string}
 */
function nabidka_workflow_for_offer(array $off) {
    $st = (int)($off['id_status'] ?? 0);
    [$has_spec, $has_lab] = nabidka_parse_file_flags($off['seznam_souboru'] ?? '');
    $cena = (float)($off['cena_nabidka'] ?? 0);
    $cena_ok_qty = formatPozadovaneMnozstvi($off['pozadovane_mnozstvi'] ?? '');
    $rank = nabidka_workflow_status_rank($st);
    $delegace = function_exists('wf_delegace_is_active') && wf_delegace_is_active($off);

    if (nabidkaJeZamitnuta($st)) {
        $mode = 'ko';
    } elseif (nabidkaJeOdlozena($st)) {
        $mode = 'led';
    } else {
        $mode = 'active';
    }

    $steps_out = [];
    $found_current = false;
    foreach (nabidka_workflow_step_defs() as $def) {
        $done = nabidka_workflow_step_done($def['id'], $st, $rank, $has_spec, $has_lab, $cena, $cena_ok_qty);
        $state = 'pending';

        if ($mode === 'ko') {
            $state = $done ? 'done' : ($found_current ? 'pending' : 'ko');
            if ($state === 'ko') $found_current = true;
        } elseif ($mode === 'led') {
            $state = $done ? 'done' : ($found_current ? 'pending' : 'led');
            if ($state === 'led') $found_current = true;
        } elseif ($done) {
            $state = 'done';
        } elseif (!$found_current) {
            $state = $delegace ? 'delegated' : 'current';
            $found_current = true;
        }

        if ($def['id'] === 'coa' && !$has_lab && $rank >= 70 && $state === 'pending' && $mode === 'active') {
            $state = 'warn';
        }

        $steps_out[] = array_merge($def, ['state' => $state]);
    }

    $dod = $off['dodavatel_nazev'] ?? 'Dodavatel';
    $subtitle = '';
    if ($mode === 'ko') {
        $subtitle = 'zamítnuto';
    } elseif ($mode === 'led') {
        $subtitle = 'k ledu';
    } elseif ($st == 2) {
        $subtitle = $delegace
            ? '↪ ' . wf_delegace_dept_label($off['wf_delegace_komu'] ?? '')
            : 'čeká CENA OK';
    } elseif ($st == STATUS_NABIDKA_BEZ_CENY) {
        $subtitle = 'bez ceny';
    } elseif ($cena_ok_qty !== null) {
        $subtitle = $cena_ok_qty;
    } elseif (nabidkaPotrebujePoptavku($st)) {
        $subtitle = '⚠ nezadáno';
    } else {
        $subtitle = $off['status_nazev'] ?? '';
    }

    return [
        'mode' => $mode,
        'steps' => $steps_out,
        'subtitle' => $subtitle,
        'dodavatel' => $dod,
        'offer_id' => (int)($off['id'] ?? 0),
        'colors' => nabidka_offer_card_colors($off),
    ];
}

/** HTML matice kroků (fialový panel v detailu požadavku). */
function render_detail_workflow_matrix(array $offers) {
    $cols = [];
    foreach ($offers as $off) {
        if (empty($off['id'])) continue;
        $cols[] = nabidka_workflow_for_offer($off);
    }

    if (empty($cols)) {
        return '<span class="text-muted">Zatím žádná nabídka.</span>';
    }

    $html = '<div class="rd-wf-matrix">';
    foreach ($cols as $col) {
        $mode = $col['mode'];
        $row_class = 'rd-wf-row rd-wf-row-accent';
        if ($mode === 'ko') $row_class .= ' rd-wf-row-ko';
        elseif ($mode === 'led') $row_class .= ' rd-wf-row-led';

        $c = $col['colors'];
        $row_style = 'border-left-color:' . htmlspecialchars($c['accent']) . ';'
            . 'background:' . htmlspecialchars($c['bg']) . ';'
            . 'opacity:' . htmlspecialchars($c['opacity']) . ';';

        $html .= '<div class="' . $row_class . '" style="' . $row_style . '" title="#' . $col['offer_id'] . '">';
        $html .= '<div class="rd-wf-row-head">';
        $html .= '<strong class="rd-wf-row-name">' . htmlspecialchars($col['dodavatel']) . '</strong>';
        $sub_class = 'rd-wf-row-sub';
        if ($col['subtitle'] === '⚠ nezadáno') $sub_class .= ' text-danger';
        elseif ($mode === 'ko') $sub_class .= ' text-danger';
        elseif ($mode === 'led') $sub_class .= ' text-muted';
        $html .= '<span class="' . $sub_class . '">' . htmlspecialchars($col['subtitle']) . '</span>';
        $html .= '</div>';

        $html .= '<div class="rd-wf-track">';
        $step_count = count($col['steps']);
        foreach ($col['steps'] as $i => $step) {
            $tip = $step['title'];
            if ($step['state'] === 'delegated') {
                $tip .= ' · odbočka';
            } elseif ($step['state'] === 'warn') {
                $tip .= ' · chybí COA';
            } elseif ($step['state'] === 'ko') {
                $tip .= ' · KO';
            } elseif ($step['state'] === 'led') {
                $tip .= ' · odloženo';
            }
            $html .= '<div class="rd-wf-step rd-wf-step-' . htmlspecialchars($step['state']) . '" title="' . htmlspecialchars($tip) . '">';
            $html .= '<span class="rd-wf-step-icon"><i class="glyphicon ' . htmlspecialchars($step['icon']) . '"></i></span>';
            $html .= '<span class="rd-wf-step-label">' . htmlspecialchars($step['label']) . '</span>';
            $html .= '</div>';
            if ($i < $step_count - 1) {
                $conn_class = 'rd-wf-connector';
                if ($step['state'] === 'done') {
                    $conn_class .= ' rd-wf-connector-done';
                }
                $html .= '<span class="' . $conn_class . '"></span>';
            }
        }
        $html .= '</div></div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Shrnutí poptávek vývoje z pole nabídek (včetně chybějících hodnot).
 */
function summarizePoptavkyVyvoje($offers) {
    $lines = [];
    foreach ($offers as $off) {
        if (empty($off['id'])) continue;
        $st = (int)($off['id_status'] ?? 0);
        if (in_array($st, [5, 7, STATUS_NABIDKA_ODLOZENO], true)) continue;

        $dod = htmlspecialchars($off['dodavatel_nazev'] ?? 'Dodavatel');
        $qty = formatPozadovaneMnozstvi($off['pozadovane_mnozstvi'] ?? '');

        if ($st == 2) {
            if (function_exists('wf_delegace_is_active') && wf_delegace_is_active($off)) {
                $komu = wf_delegace_dept_label($off['wf_delegace_komu'] ?? '');
                $lines[] = "<strong>$dod</strong>: <span class='text-warning'>↪ řeší $komu</span>";
            } else {
                $lines[] = "<strong>$dod</strong>: <span class='text-muted'>čeká na CENA OK</span>";
            }
        } elseif ($st == STATUS_NABIDKA_BEZ_CENY) {
            $lines[] = "<strong>$dod</strong>: <span class='text-muted'>bez ceny</span>";
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
    if ($h['typ_zaznamu'] == 'prirazeni') $icon = 'glyphicon-briefcase text-info';
    if ($h['typ_zaznamu'] == 'urgence') $icon = 'glyphicon-flash text-warning';
    if ($h['typ_zaznamu'] == 'poptavka') $icon = 'glyphicon-scale text-info';
    if ($h['typ_zaznamu'] == 'zalozeni') $icon = 'glyphicon-plus text-success';
    if ($h['typ_zaznamu'] == 'soubor') $icon = 'glyphicon-file text-info';
    if ($h['typ_zaznamu'] == 'workflow_delegace') $icon = 'glyphicon-share-alt text-warning';
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