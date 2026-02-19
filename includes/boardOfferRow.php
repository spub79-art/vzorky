<?php
function renderOfferRow($p, $is_adm, $is_orders, $is_vyvoj, $is_quality, $filter_phase, $req_color_hex = '#eee') {
    // Rozbalení dat
    $p_id = $p[6];
    $p_status_id = (int)$p[7];
    $p_link = $p[12] ?? '';
    $p_files_str = $p[13] ?? '';
    $p_sarze = $p[14] ?? '';
    $p_note_purch = trim($p[9] ?? '');
    $p_note_reason = trim($p[10] ?? '');

    // Detekce dokumentů
    $has_spec = (strpos($p_files_str, '~spec') !== false);
    $has_lab = (strpos($p_files_str, '~lab') !== false);
    $has_all_required = ($has_spec && $has_lab);

    // --- LOGIKA ZVÝRAZNĚNÍ (Highlight - oranžová barva) ---
    $needs_action = false;
    if ($is_adm) {
        $needs_action = true;
    } elseif ($is_orders) {
        if ($filter_phase == 2) {
            if (($p_status_id == 3 && !$has_all_required) || in_array($p_status_id, [9, 8, 11])) {
                $needs_action = true;
            }
        }
    } elseif ($is_quality) {
        if ($p_status_id == 3 && $has_all_required) $needs_action = true;
    } elseif ($is_vyvoj) {
        if (in_array($p_status_id, [2, 10, 4])) $needs_action = true;
    }

    $is_rejected = in_array($p_status_id, [5, 7]);

    $border_color = $is_rejected ? '#d9534f' : ($needs_action ? '#f0ad4e' : '#ddd');
    $row_style = "background: " . ($is_rejected ? '#fdf2f2' : '#fff') . "; ";
    $row_style .= "border: " . ($needs_action ? '2px' : '1px') . " solid $border_color; ";
    if ($needs_action && !$is_rejected) {
        $row_style .= "box-shadow: 0 2px 8px rgba(240,173,78,0.15);";
    }
    ?>
    <div class="offer-row <?= $is_rejected ? 'offer-rejected' : '' ?>"
         style="display:flex; border-radius:4px; margin-bottom:6px; overflow:hidden; <?= $row_style ?> border-left: 6px solid <?= $border_color ?>;">

        <div style="flex:1; padding:8px; min-width: 0;">
            <div style="display:flex; justify-content:space-between; align-items: baseline;">
                <span style="font-size:11px; font-weight:bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px;" title="<?= htmlspecialchars($p[0]) ?>">
                    <?= htmlspecialchars($p[0]) ?>
                </span>
                <span style="font-size:11px; font-weight:bold; color:#444;"><?= number_format((float)$p[1], 0, ',', ' ') ?>&nbsp;<?= $p[2] ?></span>
            </div>

            <div style="font-size:10px; margin-top:2px;">
                <?php
                switch($p_status_id) {
                    case 8: echo '<b class="text-success"><i class="glyphicon glyphicon-ok-circle"></i> DOKUMENTY SCHVÁLENY</b>'; break;
                    case 11: echo '<span style="color:#8e44ad;"><i class="glyphicon glyphicon-plane"></i> Vzorek na cestě</span>'; break;
                    case 7: echo '<b class="text-danger">ZAMÍTNUTO (KO)</b>'; break;
                    case 9: echo '<b class="text-warning">KVALITA: DOPLNIT DOKUMENTACI</b>'; break;
                    case 3: echo '<span class="text-info">'.($has_all_required ? 'Kvalita: Kontrola' : 'Nákup: Nahrát dokumenty').'</span>'; break;
                    case 10: case 4: echo '<b style="color:#2980b9;"><i class="glyphicon glyphicon-flask"></i> TECHNOLOGICKÝ TEST</b>'; break;
                    case 2: echo '<span class="text-warning">Čeká na schválení ceny</span>'; break;
                    default: echo '<span class="text-muted">'.$p[3].'</span>';
                }
                ?>
            </div>

            <?php
            $display_note = !empty($p_note_reason) ? $p_note_reason : $p_note_purch;
            if (!empty($display_note) && in_array($p_status_id, [9, 7, 3, 2, 10, 4])):
                ?>
                <div style="font-size:10px; color:#856404; background: #fff3cd; border: 1px solid #ffeeba; padding: 4px 6px; border-radius: 3px; margin-top: 4px; line-height: 1.2;">
                    <i class="glyphicon glyphicon-info-sign"></i> <strong>Poznámka:</strong> <?= htmlspecialchars($display_note) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($p_sarze)): ?><div style="font-size:9px; color:#666; margin-top:2px;">Šarže: <strong><?= htmlspecialchars($p_sarze) ?></strong></div><?php endif; ?>

            <?php if(!empty($p_files_str)): ?>
                <div style="display:flex; gap:6px; margin-top:5px;">
                    <?php
                    foreach(explode('^', $p_files_str) as $f):
                        if(empty($f)) continue;
                        $pts = explode('~', $f);
                        $cl = ($pts[1] == 'spec' ? '#337ab7' : ($pts[1] == 'lab' ? '#d9534f' : '#777'));
                        ?>
                        <a href="<?= $p_link ?>&scrollto=<?= rawurlencode($pts[0]) ?>" target="_blank" style="color:<?= $cl ?>; font-size:11px;"><i class="glyphicon glyphicon-file"></i></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="width:115px; padding:4px; background: rgba(0,0,0,0.02); border-left: 1px solid #eee; display:flex; flex-direction:column; gap:3px; justify-content: center;">

            <?php if (in_array($p_status_id, [10, 4]) && ($is_vyvoj || $is_adm)): ?>
                <button class="btn btn-xs btn-block btn-success btn-wf-direct" data-id="<?= $p_id ?>" data-status="6" style="font-size:9px; font-weight:bold;">TEST OK</button>
                <button class="btn btn-xs btn-block btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7" style="font-size:8px;">TEST KO</button>
            <?php endif; ?>

            <?php if ($p_status_id == 2 && ($is_vyvoj || $is_adm)): ?>
                <button class="btn btn-xs btn-block btn-success btn-prompt-qty-note"
                        data-id="<?= $p_id ?>"
                        data-status="3"
                        data-note="<?= htmlspecialchars($p_note_purch) ?>"
                        style="font-size:9px; font-weight:bold;">CENA OK</button>
                <button class="btn btn-xs btn-block btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7" style="font-size:8px;">KO</button>
            <?php endif; ?>

            <?php if ($p_status_id == 3 && ($is_quality || $is_adm) && $has_all_required): ?>
                <button class="btn btn-xs btn-block btn-success btn-wf-check" data-id="<?= $p_id ?>" data-status="8" style="font-size:9px; font-weight:bold;">DOK. OK</button>
                <div style="display:flex; gap:2px;">
                    <button class="btn btn-xs btn-warning btn-prompt-reason" data-id="<?= $p_id ?>" data-status="9" style="flex:1; font-size:8px;">DOPLNIT</button>
                    <button class="btn btn-xs btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7" style="flex:1; font-size:8px;">KO</button>
                </div>
            <?php endif; ?>

            <?php if ($p_status_id == 8 && ($is_orders || $is_adm)): ?>
                <button class="btn btn-xs btn-block btn-warning btn-wf-direct" data-id="<?= $p_id ?>" data-status="11" style="font-size:10px; font-weight:bold;">OBJEDNÁNO</button>
                <button class="btn btn-xs btn-block btn-default btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7" style="font-size:8px; color:#777;">NELZE OBJEDNAT</button>
            <?php endif; ?>

            <?php if ($p_status_id == 11 && ($is_orders || $is_adm)): ?>
                <button class="btn btn-xs btn-block btn-primary btn-wf-direct" data-id="<?= $p_id ?>" data-status="10" style="font-size:9px; font-weight:bold;">DORAZILO</button>
            <?php endif; ?>

            <?php
            $show_manage = false;
            if ($is_adm) $show_manage = true;
            elseif ($is_orders && $filter_phase == 2) $show_manage = true;
            elseif ($is_vyvoj && $filter_phase == 3) $show_manage = true;

            if ($show_manage):
                ?>
                <button class="btn btn-xs btn-block <?= ($needs_action) ? 'btn-warning' : 'btn-default' ?> btn-wf"
                        style="font-size:9px; padding: 2px 0;" data-id="<?= $p_id ?>" data-status="<?= ($p_status_id == 9 ? 3 : 'no_change') ?>" data-upload="1"
                        data-sarze="<?= htmlspecialchars($p_sarze) ?>" data-note="<?= htmlspecialchars($p_note_purch) ?>" data-files="<?= htmlspecialchars($p_files_str) ?>">
                    <i class="glyphicon glyphicon-cog"></i> SPRÁVA
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}