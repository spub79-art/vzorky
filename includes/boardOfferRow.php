<?php
include_once(__DIR__ . '/boardOfferActions.php');

function renderOfferRow($p, $is_adm, $can_nakup, $is_vyvoj, $is_quality, $filter_phase, $req_color_hex = '#eee', $history_off = []) {
    // 100% CISTE PROPOJENI S DATABAZI - zadne ciselne indexy
    $p_id = (int)($p['id'] ?? 0);
    $p_status_id = (int)($p['id_status'] ?? 0);
    $p_link = $p['link_dokumentace'] ?? '';
    $p_files_str = $p['seznam_souboru'] ?? '';
    $p_sarze = $p['sarze'] ?? '';
    $p_moq_qty = $p['moq_mnozstvi'] ?? '0';
    $p_moq_mj = $p['moq_mj'] ?? 'kg';
    $p_poptavka_qty = formatPozadovaneMnozstvi($p['pozadovane_mnozstvi'] ?? '');
    $vlozena_cena = (float)($p['cena_nabidka'] ?? 0);
    $mena = $p['mena'] ?? 'CZK';
    $dodavatel_nazev = $p['dodavatel_nazev'] ?? 'Neznámý';
    $poznamka_nakup = $p['poznamka_nakup'] ?? '';
    $id_resitel = (int)($p['id_resitel'] ?? 0);

    // LOGIKA PRO PRAZDNOU NABIDKU ("Hledá se dodavatel...")
    $current_uid = $_SESSION['uid'] ?? 0;
    if (strpos($poznamka_nakup, '[Hledá se dodavatel]') !== false) {
        $is_my_dummy = ($id_resitel == $current_uid || $is_adm || $can_nakup);
        ?>
        <div class="offer-row <?= $is_my_dummy ? 'needs-my-action' : '' ?>" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 10px; margin-bottom: 10px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 11px; color: #2196f3; font-weight: bold;"><i class="glyphicon glyphicon-hourglass"></i> Hledá se dodavatel...</span>
            <?php if ($is_my_dummy): ?>
                <div>
                    <button class="btn btn-xs btn-default btn-return-request" data-offer-id="<?= $p_id ?>">Vzdávám to</button>
                    <button class="btn btn-xs btn-warning btn-edit-offer" data-id="<?= $p_id ?>" data-dodavatel="" data-cena="" data-mena="CZK" data-resitel="<?= $id_resitel ?>"><i class="glyphicon glyphicon-pencil"></i> Vložit</button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return; // Dál už normální nabídku nekreslíme
    }

    $spec_files = []; $lab_files = []; $other_files = [];
    foreach(explode('^', $p_files_str) as $f) {
        if(empty($f)) continue;
        $pts = explode('~', $f);
        if ($pts[1] == 'spec') $spec_files[] = $pts;
        elseif ($pts[1] == 'lab') $lab_files[] = $pts;
        else $other_files[] = $pts;
    }

    $has_spec = count($spec_files) > 0;
    $has_lab = count($lab_files) > 0;

    $needs_action = false;
    if ($is_adm) {
        $needs_action = true;
    } elseif ($can_nakup) {
        if ($filter_phase == 2 && ((in_array($p_status_id, [3, STATUS_NABIDKA_BEZ_CENY]) && !$has_spec) || in_array($p_status_id, [9, 8, 11]))) $needs_action = true;
        if (!$has_lab && in_array($p_status_id, [12, 13, 10, 4, 8, 11])) $needs_action = true;
    } elseif ($is_quality) {
        if ($p_status_id == 12 && $has_spec) $needs_action = true;
    } elseif ($is_vyvoj) {
        if (in_array($p_status_id, [13, 2, 10, 4])) $needs_action = true;
    }

    if (function_exists('wf_delegace_is_active') && wf_delegace_is_active($p)) {
        $dk = $p['wf_delegace_komu'] ?? '';
        $do = $p['wf_delegace_od'] ?? '';
        if (($is_vyvoj && $do === 'vyvoj') || ($is_quality && $do === 'kvalita') || ($can_nakup && $do === 'nakup')) {
            $needs_action = false;
        }
        if (($can_nakup && $dk === 'nakup') || ($is_quality && $dk === 'kvalita') || ($is_vyvoj && $dk === 'vyvoj')) {
            $needs_action = true;
        }
    }

    $is_rejected = nabidkaJeZamitnuta($p_status_id);
    $is_frozen = nabidkaJeOdlozena($p_status_id);
    $is_missing_coa_urgent = ($can_nakup && !$has_lab && in_array($p_status_id, [10, 4]));

    $bg_color_offer = $is_rejected ? '#fdf2f2' : ($is_frozen ? '#f4f6f8' : '#ffffff');

    $border_color_grey = '#dce0e5';
    if ($is_rejected || $is_missing_coa_urgent) $border_color_grey = '#ebccd1';
    elseif ($is_frozen) $border_color_grey = '#cfd8dc';
    elseif ($needs_action) $border_color_grey = '#f0ad4e';

    $border_color_left = $req_color_hex;
    if ($is_rejected || $is_missing_coa_urgent) $border_color_left = '#d9534f';
    elseif ($is_frozen) $border_color_left = '#95a5a6';
    elseif ($needs_action) $border_color_left = '#f0ad4e';

    $box_shadow = ($needs_action && !$is_rejected && !$is_frozen) ? "box-shadow: 0 2px 8px rgba(240,173,78,0.25);" : "box-shadow: 0 1px 3px rgba(0,0,0,0.04);";

    $show_manage = false;
    if ($is_adm) $show_manage = true;
    elseif ($can_nakup) { $show_manage = true; }
    elseif ($is_vyvoj && $filter_phase == 3) $show_manage = true;

    $poznamka_cena = $p['poznamka_cena'] ?? '';
    $wf_return_st = ($p_status_id == 9) ? nabidkaStatusPoDoplneniDokumentace($poznamka_cena) : 'no_change';
    $wf_attrs = "class='offer-file-badge is-dashed btn-wf' data-id='$p_id' data-status='".($p_status_id == 9 ? $wf_return_st : 'no_change')."' data-upload='1' data-sarze='".htmlspecialchars($p_sarze)."' data-files='".htmlspecialchars($p_files_str)."'";

    $tds_badge = $has_spec
        ? "<a href='#' class='offer-file-badge btn-open-files-modal' data-id='$p_id' style='color:#337ab7; border-color:#337ab7; background-color:#eef5fa;' title='Zobrazit soubory'><i class='glyphicon glyphicon-file'></i> TDS</a>"
        : ($show_manage ? "<span $wf_attrs style='color:#999; border-color:#ccc;' title='Nahrát TDS'><i class='glyphicon glyphicon-open'></i> TDS</span>" : "<span class='offer-file-badge is-dashed' style='color:#ccc; border-color:#eee;'><i class='glyphicon glyphicon-file'></i> TDS</span>");

    $coa_badge = $has_lab
        ? "<a href='#' class='offer-file-badge btn-open-files-modal' data-id='$p_id' style='color:#d9534f; border-color:#d9534f; background-color:#fdf0f0;' title='Zobrazit soubory'><i class='glyphicon glyphicon-file'></i> COA</a>"
        : ($show_manage ? "<span $wf_attrs style='color:#999; border-color:#ccc;' title='Nahrát COA'><i class='glyphicon glyphicon-open'></i> COA</span>" : "<span class='offer-file-badge is-dashed' style='color:#ccc; border-color:#eee;'><i class='glyphicon glyphicon-file'></i> COA</span>");

    $other_badge = (count($other_files) > 0)
        ? "<a href='#' class='offer-file-badge btn-open-files-modal' data-id='$p_id' style='color:#777; border-color:#999;' title='Ostatní soubory'><i class='glyphicon glyphicon-paperclip'></i></a>" : "";

    $action_buttons = renderOfferActionButtons($p, [
        'is_adm' => $is_adm,
        'can_nakup' => $can_nakup,
        'is_vyvoj' => $is_vyvoj,
        'is_quality' => $is_quality,
        'filter_phase' => $filter_phase,
        'history_off' => $history_off,
        'layout' => 'board',
    ]);

    ?>
    <div class="offer-row <?= $is_rejected ? 'offer-rejected' : '' ?> <?= $is_frozen ? 'offer-frozen' : '' ?> <?= ($needs_action && !$is_rejected && !$is_frozen) ? 'needs-my-action' : '' ?>" style="background-color: <?= $bg_color_offer ?>; border: 1px solid <?= $border_color_grey ?>; border-left: 4px solid <?= $border_color_left ?>; <?= $box_shadow ?>">
        <div class="offer-content">

            <div class="offer-title-row">
                <div class="offer-title-group">
                    <span class="offer-title" title="<?= htmlspecialchars($dodavatel_nazev) ?>">
                        <span class="text-muted" style="font-size: 1em; margin-right: 4px;">#<?= $p_id ?></span>
                        <?= htmlspecialchars($dodavatel_nazev) ?>
                    </span>
                    <div class="offer-files-row">
                        <?= $tds_badge ?>
                        <?= $coa_badge ?>
                        <?= $other_badge ?>
                    </div>
                </div>
                <div class="offer-price-box" style="text-align: right;">
                    <?php
                    $dopravne = 0;
                    if (!empty($poznamka_nakup) && preg_match('/\[Dopravné:\s*([0-9.,]+)\s*Kč\/MJ\]/ui', $poznamka_nakup, $m)) {
                        $dopravne = (float)str_replace(',', '.', $m[1]);
                    }

                    $kurz_eur = defined('CNB_EUR_RATE') ? CNB_EUR_RATE : 25.10;
                    $kurz_usd = defined('CNB_USD_RATE') ? CNB_USD_RATE : 23.50;

                    if ($mena === 'EUR') {
                        $cena_v_czk = $vlozena_cena * $kurz_eur;
                    } elseif ($mena === 'USD') {
                        $cena_v_czk = $vlozena_cena * $kurz_usd;
                    } else {
                        $cena_v_czk = $vlozena_cena;
                    }

                    $finalni_all_in = $cena_v_czk + $dopravne;

                    if ($p_status_id == STATUS_NABIDKA_BEZ_CENY): ?>
                        <span class="offer-price" style="color: #8e44ad; font-style: italic;" title="Cena bude doplněna později">bez ceny</span>
                    <?php else:
                        $price_title = 'Cena do receptury vč. dopravy (CZK/MJ)';
                        if ($mena !== 'CZK' && $vlozena_cena > 0) {
                            $price_title .= ' · nabídka '
                                . number_format($vlozena_cena, (floor($vlozena_cena) == $vlozena_cena ? 0 : 2), ',', ' ')
                                . ' ' . $mena;
                            if ($dopravne > 0) {
                                $price_title .= ' + dopravné';
                            }
                        }
                        ?>
                        <span class="offer-price" style="color: #2c3e50;" title="<?= htmlspecialchars($price_title) ?>">
                            <?= number_format($finalni_all_in, 2, ',', ' ') ?>&nbsp;CZK
                        </span>
                    <?php endif; ?>

                    <?php if ((float)$p_moq_qty > 0): ?>
                        <div class="offer-moq" title="Minimální objednací množství dodavatele"><i class="glyphicon glyphicon-scale"></i> MOQ: <?= htmlspecialchars($p_moq_qty) ?>&nbsp;<?= htmlspecialchars($p_moq_mj) ?></div>
                    <?php endif; ?>
                    <?php if ($p_status_id == 2): ?>
                        <div class="offer-poptavka offer-poptavka-pending" title="Množství zadá vývoj při schválení ceny"><i class="glyphicon glyphicon-time"></i> Poptávka: po CENA OK</div>
                    <?php elseif ($p_status_id == STATUS_NABIDKA_BEZ_CENY): ?>
                        <div class="offer-poptavka offer-poptavka-pending" title="Množství až po doplnění a schválení ceny"><i class="glyphicon glyphicon-time"></i> Poptávka: po doplnění ceny</div>
                    <?php elseif ($p_poptavka_qty !== null): ?>
                        <div class="offer-poptavka" title="Množství požadované vývojem"><i class="glyphicon glyphicon-shopping-cart"></i> Poptávka: <?= htmlspecialchars($p_poptavka_qty) ?></div>
                    <?php elseif (nabidkaPotrebujePoptavku($p_status_id)): ?>
                        <div class="offer-poptavka offer-poptavka-missing" title="Chybí množství — mělo být zadáno při CENA OK"><i class="glyphicon glyphicon-warning-sign"></i> Poptávka: <strong>nezadáno</strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (function_exists('wf_delegace_banner_html')) echo wf_delegace_banner_html($p); ?>

            <div class="offer-status-row">
                <?php
                switch($p_status_id) {
                    case 8: echo '<b class="text-success"><i class="glyphicon glyphicon-ok-circle"></i> TDS SCHVÁLENO VŠEMI</b>'; break;
                    case 11: echo '<span style="color:#8e44ad;"><i class="glyphicon glyphicon-plane"></i> Vzorek objednán</span>'; break;
                    case 7: echo '<b class="text-danger">ZAMÍTNUTO (KO)</b>'; break;
                    case STATUS_NABIDKA_ODLOZENO: echo '<b class="text-muted"><i class="glyphicon glyphicon-pause"></i> ODLOŽENO K LEDU</b>'; break;
                    case 9: echo '<b class="text-warning">NÁKUP: DOPLNIT DOKUMENTACI</b>'; break;
                    case 3: echo '<span class="text-info">Nákup: Čeká se na nahrání TDS a předání kvalitě</span>'; break;
                    case STATUS_NABIDKA_BEZ_CENY: echo '<span style="color:#8e44ad;"><i class="glyphicon glyphicon-file"></i> Dokumentace bez ceny — nahrát TDS</span>'; break;
                    case 12: echo '<span class="text-primary"><i class="glyphicon glyphicon-search"></i> Kvalita: Kontrola TDS</span>'; break;
                    case 13: echo '<span class="text-primary"><i class="glyphicon glyphicon-apple"></i> Vývoj: Kontrola nutričních hodnot</span>'; break;
                    case 10: case 4: echo '<b style="color:#2980b9;"><i class="glyphicon glyphicon-flask"></i> TECHNOLOGICKÝ TEST</b>'; break;
                    case 2:
                        if (function_exists('wf_delegace_is_active') && wf_delegace_is_active($p)) {
                            echo '<span class="text-info"><i class="glyphicon glyphicon-share-alt"></i> Odbočka — řeší ' . htmlspecialchars(wf_delegace_dept_label($p['wf_delegace_komu'])) . '</span>';
                        } else {
                            echo '<span class="text-warning">Čeká na schválení ceny</span>';
                        }
                        break;
                    default: echo '<span class="text-muted">ID Statusu: '.$p_status_id.'</span>';
                }
                ?>
            </div>

            <?php if (!$has_lab && in_array($p_status_id, [8, 11, 10, 4])): ?>
                <div class="offer-alert">
                    <i class="glyphicon glyphicon-alert"></i> CHYBÍ COA (Nutné k testu!)
                </div>
            <?php endif; ?>

            <?php if (!empty($history_off)): ?>
                <div class="offer-comments-wrapper">
                    <?php
                    // ZOBRAZENÍ VŠECH ZÁZNAMŮ (Akordeon Zoom to v CSS schová)
                    $current_uid = $_SESSION['uid'] ?? 0;
                    foreach($history_off as $h) {
                        renderHistoryRow($h, $is_adm, $current_uid);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="chat-flex-container" style="display: flex; gap: 4px; align-items: center; margin-top: 8px;">
                <button class="btn btn-link btn-inline-comment" data-id="<?= $p_id ?>" data-type="nabidka" data-urgent="1" title="Odeslat jako URGENTNÍ" style="padding: 0 8px; color: #d9534f; font-size: 16px; text-decoration: none;">
                    <i class="glyphicon glyphicon-flash"></i>
                </button>
                <input type="text" class="form-control inline-comment-text" data-id="<?= $p_id ?>" data-type="nabidka" placeholder="Napsat k nabídce..." style="height: 30px; font-size: 11px; flex: 1;">
                <button class="btn btn-default btn-send btn-inline-comment" data-id="<?= $p_id ?>" data-type="nabidka" data-urgent="0" title="Odeslat" style="padding: 2px 10px; height: 30px;">
                    <i class="glyphicon glyphicon-send text-primary"></i>
                </button>
            </div>
        </div>

        <?php if (trim($action_buttons) !== ''): ?>
            <div class="offer-actions-col">
                <?= $action_buttons ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>