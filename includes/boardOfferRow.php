<?php
function renderOfferRow($p, $is_adm, $is_orders, $is_vyvoj, $is_quality, $filter_phase, $req_color_hex = '#eee', $history_off = []) {
    $p_id = $p[6];
    $p_status_id = (int)$p[7];
    $p_link = $p[12] ?? '';
    $p_files_str = $p[13] ?? '';
    $p_sarze = $p[14] ?? '';

    $p_moq_qty = $p[16] ?? '0';
    $p_moq_mj = $p[17] ?? 'kg';

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
    } elseif ($is_orders) {
        if ($filter_phase == 2 && (($p_status_id == 3 && !$has_spec) || in_array($p_status_id, [9, 8, 11]))) $needs_action = true;
        if (!$has_lab && in_array($p_status_id, [10, 4, 8, 11])) $needs_action = true;
    } elseif ($is_quality) {
        if ($p_status_id == 12 && $has_spec) $needs_action = true;
    } elseif ($is_vyvoj) {
        if (in_array($p_status_id, [13, 2, 10, 4])) $needs_action = true;
    }

    $is_rejected = in_array($p_status_id, [5, 7]);
    $is_missing_coa_urgent = ($is_orders && !$has_lab && in_array($p_status_id, [10, 4]));

    $bg_color_offer = $is_rejected ? '#fdf2f2' : '#ffffff';

    $border_color_grey = '#dce0e5';
    if ($is_rejected || $is_missing_coa_urgent) $border_color_grey = '#ebccd1';
    elseif ($needs_action) $border_color_grey = '#f0ad4e';

    $border_color_left = $req_color_hex;
    if ($is_rejected || $is_missing_coa_urgent) $border_color_left = '#d9534f';
    elseif ($needs_action) $border_color_left = '#f0ad4e';

    $box_shadow = ($needs_action && !$is_rejected) ? "box-shadow: 0 2px 8px rgba(240,173,78,0.25);" : "box-shadow: 0 1px 3px rgba(0,0,0,0.04);";

    $show_manage = false;
    if ($is_adm) $show_manage = true;
    elseif ($is_orders) { $show_manage = true; }
    elseif ($is_vyvoj && $filter_phase == 3) $show_manage = true;

    $wf_attrs = "class='offer-file-badge is-dashed btn-wf' data-id='$p_id' data-status='".($p_status_id == 9 ? 3 : 'no_change')."' data-upload='1' data-sarze='".htmlspecialchars($p_sarze)."' data-files='".htmlspecialchars($p_files_str)."'";

    $tds_badge = $has_spec
        ? "<a href='#' class='offer-file-badge btn-open-files-modal' data-id='$p_id' style='color:#337ab7; border-color:#337ab7; background-color:#eef5fa;' title='Zobrazit soubory'><i class='glyphicon glyphicon-file'></i> TDS</a>"
        : ($show_manage ? "<span $wf_attrs style='color:#999; border-color:#ccc;' title='Nahrát TDS'><i class='glyphicon glyphicon-open'></i> TDS</span>" : "<span class='offer-file-badge is-dashed' style='color:#ccc; border-color:#eee;'><i class='glyphicon glyphicon-file'></i> TDS</span>");

    $coa_badge = $has_lab
        ? "<a href='#' class='offer-file-badge btn-open-files-modal' data-id='$p_id' style='color:#d9534f; border-color:#d9534f; background-color:#fdf0f0;' title='Zobrazit soubory'><i class='glyphicon glyphicon-file'></i> COA</a>"
        : ($show_manage ? "<span $wf_attrs style='color:#999; border-color:#ccc;' title='Nahrát COA'><i class='glyphicon glyphicon-open'></i> COA</span>" : "<span class='offer-file-badge is-dashed' style='color:#ccc; border-color:#eee;'><i class='glyphicon glyphicon-file'></i> COA</span>");

    $other_badge = (count($other_files) > 0)
        ? "<a href='#' class='offer-file-badge btn-open-files-modal' data-id='$p_id' style='color:#777; border-color:#999;' title='Ostatní soubory'><i class='glyphicon glyphicon-paperclip'></i></a>" : "";

    ob_start();
    if (($is_orders || $is_adm) && in_array($p_status_id, [2, 3])): ?>
        <button class="btn btn-xs btn-block btn-info btn-edit-offer" data-id="<?= $p_id ?>" data-dodavatel="<?= htmlspecialchars($p[0]) ?>" data-cena="<?= $p[1] ?>" data-moq-qty="<?= $p_moq_qty ?>" data-moq-mj="<?= $p_moq_mj ?>" data-poznamka="">
            <i class="glyphicon glyphicon-pencil"></i> UPRAVIT CENU
        </button>
    <?php endif; ?>

    <?php if (in_array($p_status_id, [10, 4]) && ($is_vyvoj || $is_adm)): ?>
        <?php if ($has_lab): ?>
            <button class="btn btn-xs btn-block btn-success btn-wf-direct" data-id="<?= $p_id ?>" data-status="6">TEST OK</button>
        <?php else: ?>
            <button class="btn btn-xs btn-block btn-default" title="Nelze schválit do výroby bez COA" disabled style="color:#999;">DODAT COA</button>
        <?php endif; ?>
        <button class="btn btn-xs btn-block btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7">TEST KO</button>
    <?php endif; ?>

    <?php if ($p_status_id == 2 && ($is_vyvoj || $is_adm)): ?>
        <button class="btn btn-xs btn-block btn-success btn-prompt-qty-note" data-id="<?= $p_id ?>" data-status="3">CENA OK</button>
        <button class="btn btn-xs btn-block btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7">KO</button>
    <?php endif; ?>

    <?php if ($p_status_id == 3 && ($is_orders || $is_adm) && $has_spec): ?>
        <button class="btn btn-xs btn-block btn-primary btn-wf-direct" data-id="<?= $p_id ?>" data-status="12">PŘEDAT KVALITĚ</button>
    <?php endif; ?>

    <?php if ($p_status_id == 12 && ($is_quality || $is_adm) && $has_spec): ?>
        <button class="btn btn-xs btn-block btn-success btn-quality-approve" data-id="<?= $p_id ?>" data-status="13" data-sarze="<?= htmlspecialchars($p_sarze) ?>">KVALITA OK</button>
        <div style="display:flex; gap:2px;"><button class="btn btn-xs btn-warning btn-prompt-reason" data-id="<?= $p_id ?>" data-status="9" style="flex:1;">DOPLNIT</button><button class="btn btn-xs btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7" style="flex:1;">KO</button></div>
    <?php endif; ?>

    <?php if ($p_status_id == 13 && ($is_vyvoj || $is_adm) && $has_spec): ?>
        <button class="btn btn-xs btn-block btn-success btn-wf-check" data-id="<?= $p_id ?>" data-status="8">NUTRIČNÍ OK</button>
        <div style="display:flex; gap:2px;"><button class="btn btn-xs btn-warning btn-prompt-reason" data-id="<?= $p_id ?>" data-status="9" style="flex:1;">DOPLNIT</button><button class="btn btn-xs btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7" style="flex:1;">KO</button></div>
    <?php endif; ?>

    <?php if ($p_status_id == 8 && ($is_orders || $is_adm)): ?>
        <button class="btn btn-xs btn-block btn-warning btn-wf-direct" data-id="<?= $p_id ?>" data-status="11">OBJEDNÁNO</button>
    <?php endif; ?>

    <?php if ($p_status_id == 11 && ($is_orders || $is_adm)): ?>
        <button class="btn btn-xs btn-block btn-primary btn-wf-direct" data-id="<?= $p_id ?>" data-status="10">DORAZILO</button>
    <?php endif; ?>

    <?php if ($show_manage):
        $btn_class = 'btn-default'; $btn_text = '<i class="glyphicon glyphicon-cog"></i> SPRÁVA';
        if ($needs_action && $is_orders) {
            if (!$has_lab && in_array($p_status_id, [10, 4])) { $btn_class = 'btn-danger'; $btn_text = '<i class="glyphicon glyphicon-upload"></i> COA'; }
            else { $btn_class = 'btn-warning'; }
        }
        ?>
        <button class="btn btn-xs btn-block <?= $btn_class ?> btn-wf" data-id="<?= $p_id ?>" data-status="<?= ($p_status_id == 9 ? 3 : 'no_change') ?>" data-upload="1" data-sarze="<?= htmlspecialchars($p_sarze) ?>" data-note="" data-files="<?= htmlspecialchars($p_files_str) ?>"><?= $btn_text ?></button>
    <?php endif;
    $action_buttons = ob_get_clean();
    ?>

    <div class="offer-row <?= $is_rejected ? 'offer-rejected' : '' ?> <?= ($needs_action && !$is_rejected) ? 'needs-my-action' : '' ?>" style="background-color: <?= $bg_color_offer ?>; border: 1px solid <?= $border_color_grey ?>; border-left: 4px solid <?= $border_color_left ?>; <?= $box_shadow ?>">
        <div class="offer-content">

            <div class="offer-title-row">
                <div class="offer-title-group">
        <span class="offer-title" title="<?= htmlspecialchars($p[0]) ?>">
            <span class="text-muted" style="font-size: 1em; margin-right: 4px;">#<?= $p_id ?></span>
            <?= htmlspecialchars($p[0]) ?>
        </span>
                    <div class="offer-files-row">
                        <?= $tds_badge ?>
                        <?= $coa_badge ?>
                        <?= $other_badge ?>
                    </div>
                </div>
                <div class="offer-price-box" style="text-align: right;">
                    <?php
                    $vlozena_cena = (float)$p[1];
                    $mena = $p[2];

                    $dopravne = 0;

                    // PŘIDÁNO: Přepočet i pro USD
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

                    if ($is_vyvoj && !$is_adm): ?>
                        <span class="offer-price" style="color: #2c3e50;" title="Finální cena vč. dopravy (přepočteno z <?= $mena ?>)">
                            <?= number_format($finalni_all_in, 2, ',', ' ') ?>&nbsp;CZK
                        </span>
                    <?php else: ?>
                        <span class="offer-price">
                            <?= number_format($vlozena_cena, (floor($vlozena_cena) == $vlozena_cena ? 0 : 2), ',', ' ') ?>&nbsp;<?= $mena ?>
                        </span>
                        <?php if ($is_adm): ?>
                            <div style="font-size: 10px; color: #95a5a6; margin-top: -2px;">
                                (All-in: <?= number_format($finalni_all_in, 2, ',', ' ') ?> CZK)
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ((float)$p_moq_qty > 0): ?>
                        <div class="offer-moq" title="MOQ"><i class="glyphicon glyphicon-scale"></i> MOQ: <?= $p_moq_qty ?>&nbsp;<?= $p_moq_mj ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="offer-status-row">
                <?php
                switch($p_status_id) {
                    case 8: echo '<b class="text-success"><i class="glyphicon glyphicon-ok-circle"></i> TDS SCHVÁLENO VŠEMI</b>'; break;
                    case 11: echo '<span style="color:#8e44ad;"><i class="glyphicon glyphicon-plane"></i> Vzorek objednán</span>'; break;
                    case 7: echo '<b class="text-danger">ZAMÍTNUTO (KO)</b>'; break;
                    case 9: echo '<b class="text-warning">NÁKUP: DOPLNIT DOKUMENTACI</b>'; break;
                    case 3: echo '<span class="text-info">Nákup: Čeká se na nahrání TDS a předání kvalitě</span>'; break;
                    case 12: echo '<span class="text-primary"><i class="glyphicon glyphicon-search"></i> Kvalita: Kontrola TDS</span>'; break;
                    case 13: echo '<span class="text-primary"><i class="glyphicon glyphicon-apple"></i> Vývoj: Kontrola nutričních hodnot</span>'; break;
                    case 10: case 4: echo '<b style="color:#2980b9;"><i class="glyphicon glyphicon-flask"></i> TECHNOLOGICKÝ TEST</b>'; break;
                    case 2: echo '<span class="text-warning">Čeká na schválení ceny</span>'; break;
                    default: echo '<span class="text-muted">'.$p[3].'</span>';
                }
                ?>
            </div>

            <?php if (!$has_lab && in_array($p_status_id, [8, 11, 10, 4])): ?>
                <div class="offer-alert">
                    <i class="glyphicon glyphicon-alert"></i> CHYBÍ COA (Nutné k testu!)
                </div>
            <?php endif; ?>

            <?php // ======================================================== ?>
            <?php // VYKRESLENÍ SJEDNOCENÉ ČASOVÉ OSY (Kompaktní design) ?>
            <?php // ======================================================== ?>
            <?php if (!empty($history_off)): ?>
                <div class="offer-sys-msg" style="margin-top: 6px; padding: 4px 6px; background: #fafafa; border: 1px solid #e3e3e3; border-radius: 3px; max-height: 120px; overflow-y: auto;">
                    <?php
                    $zobrazeno_off_hist = array_slice($history_off, 0, 5);

                    foreach($zobrazeno_off_hist as $h):
                        $is_system = !in_array($h['typ_zaznamu'], ['komentar', 'komentar_urgentni']);
                        $is_urgent_msg = ($h['typ_zaznamu'] === 'komentar_urgentni');
                        $is_mine = (isset($_SESSION['uid']) && $h['id_user'] == $_SESSION['uid']);
                        $can_delete = (!$is_system && ($is_mine || $is_adm));

                        $icon = 'glyphicon-cog text-muted';
                        if ($h['typ_zaznamu'] == 'status') {
                            if (in_array($h['nova_hodnota'], ['5', '7'])) $icon = 'glyphicon-ban-circle text-danger';
                            elseif (in_array($h['nova_hodnota'], ['6', '8'])) $icon = 'glyphicon-ok-sign text-success';
                            else $icon = 'glyphicon-share-alt text-primary';
                        }
                        if ($h['typ_zaznamu'] == 'soubor') $icon = 'glyphicon-file text-info';

                        if (!$is_system) {
                            $icon = $is_urgent_msg ? 'glyphicon-exclamation-sign text-danger' : 'glyphicon-pencil text-primary';
                        }

                        $text_style = '';
                        if ($is_urgent_msg) {
                            $text_style = 'color: #c9302c; font-weight: bold; background: #fff0f0; padding: 1px 4px; border-radius: 3px; border: 1px solid #f5c6c6;';
                        }
                        ?>
                        <div style="font-size: 11px; line-height: 1.3; margin-bottom: 4px; <?= $is_system ? 'color: #666;' : 'color: #333;' ?>">
                            <i class="glyphicon <?= $icon ?>" style="font-size: 9px; margin-right: 2px;"></i>
                            [<?= htmlspecialchars($h['jmeno_user']) ?> - <?= date('j.n. H:i', strtotime($h['vytvoreno'])) ?>]:

                            <span <?= $is_urgent_msg ? 'style="'.$text_style.'"' : '' ?>><?= nl2br(htmlspecialchars($h['text_hodnota'])) ?></span>

                            <?php // PŘIDÁNO: Editační tlačítko (tužka) vedle křížku ?>
                            <?php if ($can_delete): ?>
                                <span style="float: right; margin-top: 1px;">
                                    <i class="glyphicon glyphicon-pencil text-primary btn-edit-history" data-id="<?= $h['id'] ?>" data-text="<?= htmlspecialchars($h['text_hodnota'], ENT_QUOTES) ?>" title="Upravit poznámku" style="cursor: pointer; font-size: 10px; margin-right: 6px;"></i>
                                    <i class="glyphicon glyphicon-remove text-danger btn-delete-history" data-id="<?= $h['id'] ?>" title="Smazat poznámku" style="cursor: pointer; font-size: 10px;"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($history_off) > 5): ?>
                        <div style="font-size: 10px; color: #999; text-align: center; margin-top: 4px; border-top: 1px dashed #ddd; padding-top: 2px;">
                            ... a dalších <?= count($history_off) - 5 ?> starších záznamů (viz detail)
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="chat-flex-container">
                <input type="text" class="form-control inline-comment-text" data-id="<?= $p_id ?>" data-type="nabidka" placeholder="Napsat k nabídce...">
                <button class="btn btn-warning btn-urgent btn-inline-comment" data-id="<?= $p_id ?>" data-type="nabidka" data-urgent="1" title="Odeslat jako URGENTNÍ">
                    <i class="glyphicon glyphicon-flash"></i>
                </button>
                <button class="btn btn-default btn-send btn-inline-comment" data-id="<?= $p_id ?>" data-type="nabidka" data-urgent="0" title="Odeslat">
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