<?php
/**
 * Sdílené workflow tlačítka u nabídky (nástěnka + detail požadavku).
 */
function offer_action_btn_class($layout) {
    return 'btn btn-xs' . ($layout === 'detail' ? '' : ' btn-block');
}

function offer_show_manage_btn($is_adm, $can_nakup, $is_vyvoj, $filter_phase, $layout) {
    if ($is_adm || $can_nakup) {
        return true;
    }
    if ($is_vyvoj && ($layout === 'detail' || (int)$filter_phase === 3)) {
        return true;
    }
    return false;
}

function renderOfferActionButtons(array $p, array $ctx) {
    $is_adm = !empty($ctx['is_adm']);
    $can_nakup = !empty($ctx['can_nakup']);
    $is_vyvoj = !empty($ctx['is_vyvoj']);
    $is_quality = !empty($ctx['is_quality']);
    $filter_phase = (int)($ctx['filter_phase'] ?? 0);
    $history_off = $ctx['history_off'] ?? [];
    $layout = ($ctx['layout'] ?? 'board') === 'detail' ? 'detail' : 'board';
    $detail_mode = ($layout === 'detail');

    $p_id = (int)($p['id'] ?? 0);
    $p_status_id = (int)($p['id_status'] ?? 0);
    $p_files_str = $p['seznam_souboru'] ?? '';
    $p_sarze = $p['sarze'] ?? '';
    $vlozena_cena = (float)($p['cena_nabidka'] ?? 0);
    $dodavatel_nazev = $p['dodavatel_nazev'] ?? 'Neznámý';

    $has_spec = false;
    $has_lab = false;
    foreach (explode('^', $p_files_str) as $f) {
        if (empty($f)) continue;
        $pts = explode('~', $f);
        if (($pts[1] ?? '') === 'spec') $has_spec = true;
        elseif (($pts[1] ?? '') === 'lab') $has_lab = true;
    }

    $has_user_activity = false;
    if (!empty($history_off)) {
        foreach ($history_off as $h) {
            if (in_array($h['typ_zaznamu'], ['komentar', 'komentar_urgentni', 'soubor'])) {
                $has_user_activity = true;
                break;
            }
        }
    }

    $is_rejected = nabidkaJeZamitnuta($p_status_id);
    $is_frozen = nabidkaJeOdlozena($p_status_id);
    $show_manage = offer_show_manage_btn($is_adm, $can_nakup, $is_vyvoj, $filter_phase, $layout);

    $needs_action = false;
    if ($is_adm) {
        $needs_action = true;
    } elseif ($can_nakup) {
        if (($detail_mode || $filter_phase === 2) && ((in_array($p_status_id, [3, STATUS_NABIDKA_BEZ_CENY]) && !$has_spec) || in_array($p_status_id, [9, 8, 11]))) {
            $needs_action = true;
        }
        if (!$has_lab && in_array($p_status_id, [12, 13, 10, 4, 8, 11])) {
            $needs_action = true;
        }
    } elseif ($is_quality) {
        if ($p_status_id == 12 && $has_spec) $needs_action = true;
    } elseif ($is_vyvoj) {
        if (in_array($p_status_id, [13, 2, 10, 4])) $needs_action = true;
    }

    $btn = offer_action_btn_class($layout);
    $poznamka_cena = $p['poznamka_cena'] ?? '';
    $sib_active = (int)($p['_sib_active'] ?? 0);
    $can_led = ($is_vyvoj || $is_adm || $can_nakup || $is_quality);

    ob_start();
    if ($layout === 'detail') {
        echo '<div class="rd-offer-wf-actions">';
    }

    if ($can_nakup && in_array($p_status_id, [2, 3, STATUS_NABIDKA_BEZ_CENY, 9, 12, 13])): ?>
        <button class="<?= $btn ?> btn-info btn-edit-offer" data-id="<?= $p_id ?>" data-dodavatel="<?= htmlspecialchars($dodavatel_nazev) ?>" data-cena="<?= $vlozena_cena ?>" data-mena="<?= htmlspecialchars($p['mena'] ?? 'CZK') ?>" data-moq-qty="<?= htmlspecialchars($p['moq_mnozstvi'] ?? '0') ?>" data-moq-mj="<?= htmlspecialchars($p['moq_mj'] ?? 'kg') ?>" data-poznamka="">
            <i class="glyphicon glyphicon-pencil"></i> UPRAVIT CENU
        </button>
        <?php if (!$has_user_activity): ?>
            <button class="<?= $btn ?> btn-danger btn-delete-offer-ajax" data-id="<?= $p_id ?>" title="Smazat chybně vloženou nabídku" style="margin-top: 2px;">
                <i class="glyphicon glyphicon-trash"></i> SMAZAT OMYL
            </button>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (in_array($p_status_id, [10, 4]) && ($is_vyvoj || $is_adm)): ?>
        <?php if (!$is_frozen && !$is_rejected): ?>
        <button class="<?= $btn ?> btn-default btn-postpone-offer" data-id="<?= $p_id ?>"
            title="Alternativa nejde do testu — schovat k ledu">
            <i class="glyphicon glyphicon-pause"></i> K ledu
        </button>
        <?php endif; ?>
        <?php if ($has_lab): ?>
            <button class="<?= $btn ?> btn-success btn-wf-direct" data-id="<?= $p_id ?>" data-status="6">TEST OK</button>
        <?php else: ?>
            <button class="<?= $btn ?> btn-default" title="Nelze schválit do výroby bez COA" disabled style="color:#999;">DODAT COA</button>
        <?php endif; ?>
        <button class="<?= $btn ?> btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7">TEST KO</button>
    <?php endif; ?>

    <?php if ($p_status_id == 2 && ($is_vyvoj || $is_adm)): ?>
        <button class="<?= $btn ?> btn-success btn-prompt-qty-note" data-id="<?= $p_id ?>" data-status="3">CENA OK</button>
        <?php if (empty($p['wf_delegace_komu']) || !function_exists('wf_delegace_is_active') || !wf_delegace_is_active($p)): ?>
        <button class="<?= $btn ?> btn-warning btn-wf-delegace" data-id="<?= $p_id ?>" data-komu="nakup" title="Cena/parametr — předat bez KO">
            <i class="glyphicon glyphicon-share-alt"></i> Předat — jiný důvod
        </button>
        <?php endif; ?>
        <button class="<?= $btn ?> btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7">KO</button>
    <?php endif; ?>

    <?php if (function_exists('wf_delegace_is_active') && wf_delegace_is_active($p)):
        $dk = $p['wf_delegace_komu'] ?? '';
        $can_clear_deleg = $is_adm
            || ($dk === 'nakup' && $can_nakup)
            || ($dk === 'kvalita' && $is_quality)
            || ($dk === 'vyvoj' && $is_vyvoj);
        if ($can_clear_deleg): ?>
        <button class="<?= $btn ?> btn-default btn-wf-delegace-clear" data-id="<?= $p_id ?>">
            <i class="glyphicon glyphicon-ok"></i> Vyřešeno — vrátit
        </button>
    <?php endif; endif; ?>

    <?php if ($p_status_id == 3 && $can_nakup && $has_spec): ?>
        <button class="<?= $btn ?> btn-primary btn-wf-direct" data-id="<?= $p_id ?>" data-status="12">PŘEDAT KVALITĚ</button>
    <?php endif; ?>

    <?php if ($p_status_id == STATUS_NABIDKA_BEZ_CENY && $has_spec): ?>
        <div class="text-muted" style="font-size:9px; margin:2px 0; line-height:1.3;">
            TDS nahráno — Nákup doplní cenu, vývoj schválí <strong>CENA OK</strong>, pak lze předat kvalitě.
        </div>
    <?php endif; ?>

    <?php if ($p_status_id == 12 && ($is_quality || $is_adm) && $has_spec): ?>
        <button class="<?= $btn ?> btn-success btn-quality-approve" data-id="<?= $p_id ?>" data-status="13" data-sarze="<?= htmlspecialchars($p_sarze) ?>">KVALITA OK</button>
        <div style="display:flex; gap:2px; flex-wrap:wrap;"><button class="btn btn-xs btn-warning btn-prompt-reason" data-id="<?= $p_id ?>" data-status="9" style="flex:1;">DOPLNIT</button><button class="btn btn-xs btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7" style="flex:1;">KO</button></div>
        <?php if (empty($p['wf_delegace_komu']) || !function_exists('wf_delegace_is_active') || !wf_delegace_is_active($p)): ?>
        <button class="<?= $btn ?> btn-warning btn-wf-delegace" data-id="<?= $p_id ?>" data-komu="nakup" style="margin-top:2px;">
            <i class="glyphicon glyphicon-share-alt"></i> Předat — jiný důvod
        </button>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($p_status_id == 13 && ($is_vyvoj || $is_adm) && $has_spec):
        $has_poptavka = trim($p['pozadovane_mnozstvi'] ?? '') !== '';
        if ($vlozena_cena <= 0): ?>
        <button class="<?= $btn ?> btn-success btn-wf-nutri-deferred" data-id="<?= $p_id ?>">NUTRIČNÍ OK</button>
        <div class="text-muted" style="font-size:9px; margin:2px 0;">Bez ceny — vzorek až po doplnění ceny Nákupu</div>
        <?php elseif (!$has_poptavka): ?>
        <div class="text-danger" style="font-size:9px; margin:2px 0;">Chybí schválení ceny vývojem (CENA OK). Nákup doplní cenu → vývoj schválí.</div>
        <?php else: ?>
        <button class="<?= $btn ?> btn-success btn-wf-check" data-id="<?= $p_id ?>" data-status="8">NUTRIČNÍ OK</button>
        <?php endif; ?>
        <div style="display:flex; gap:2px; flex-wrap:wrap;"><button class="btn btn-xs btn-warning btn-prompt-reason" data-id="<?= $p_id ?>" data-status="9" style="flex:1;">DOPLNIT</button><button class="btn btn-xs btn-danger btn-prompt-reason" data-id="<?= $p_id ?>" data-status="7" style="flex:1;">KO</button></div>
        <?php if (empty($p['wf_delegace_komu']) || !function_exists('wf_delegace_is_active') || !wf_delegace_is_active($p)): ?>
        <button class="<?= $btn ?> btn-warning btn-wf-delegace" data-id="<?= $p_id ?>" data-komu="nakup" style="margin-top:2px;">
            <i class="glyphicon glyphicon-share-alt"></i> Předat — jiný důvod
        </button>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($p_status_id == 8 && $can_nakup): ?>
        <button class="<?= $btn ?> btn-warning btn-wf-direct" data-id="<?= $p_id ?>" data-status="11">OBJEDNÁNO</button>
    <?php endif; ?>

    <?php if ($p_status_id == 11 && $can_nakup): ?>
        <button class="<?= $btn ?> btn-primary btn-wf-direct" data-id="<?= $p_id ?>" data-status="10">DORAZILO</button>
    <?php endif; ?>

    <?php if (($is_vyvoj || $is_adm) && $p_status_id === 6 && $sib_active > 0): ?>
        <button class="<?= $btn ?> btn-success btn-winning-offer" data-id="<?= $p_id ?>" data-count="<?= $sib_active ?>"
            title="Ostatní nabídky (<?= $sib_active ?>) pošle k ledu">
            <i class="glyphicon glyphicon-star"></i> Vítězná — ostatní k ledu (<?= $sib_active ?>)
        </button>
    <?php endif; ?>

    <?php if ($can_led && $is_frozen): ?>
        <button class="<?= $btn ?> btn-success btn-revive-offer" data-id="<?= $p_id ?>" style="margin-top:2px;">
            <i class="glyphicon glyphicon-fire"></i> Odledovat
        </button>
    <?php elseif ($can_led && !$is_rejected && !$is_frozen && $p_status_id !== 6 && !in_array($p_status_id, [10, 4])): ?>
        <button class="<?= $btn ?> btn-link btn-postpone-offer" data-id="<?= $p_id ?>"
            title="Schovat tuto alternativu" style="margin-top:2px; font-size:10px; padding:2px 6px;">
            <i class="glyphicon glyphicon-pause"></i> K ledu
        </button>
    <?php endif; ?>

    <?php if ($show_manage):
        $btn_class = 'btn-default';
        $btn_text = '<i class="glyphicon glyphicon-cog"></i> SPRÁVA';
        if ($needs_action && $can_nakup) {
            if (!$has_lab && in_array($p_status_id, [10, 4])) {
                $btn_class = 'btn-danger';
                $btn_text = '<i class="glyphicon glyphicon-upload"></i> COA';
            } else {
                $btn_class = 'btn-warning';
            }
        }
        ?>
        <button class="<?= $btn ?> <?= $btn_class ?> btn-wf" data-id="<?= $p_id ?>" data-status="<?= ($p_status_id == 9 ? nabidkaStatusPoDoplneniDokumentace($poznamka_cena) : 'no_change') ?>" data-upload="1" data-sarze="<?= htmlspecialchars($p_sarze) ?>" data-note="" data-files="<?= htmlspecialchars($p_files_str) ?>"><?= $btn_text ?></button>
    <?php endif;

    if ($layout === 'detail') {
        echo '</div>';
    }

    return ob_get_clean();
}

function renderRequestDetailToolbar(array $req, array $perms) {
    $is_adm = !empty($perms['is_adm']);
    $can_nakup = !empty($perms['can_nakup']);
    $can_claim_nakup = !empty($perms['can_claim_nakup']);
    $is_vyvoj = !empty($perms['is_vyvoj']);
    $is_orders = !empty($perms['is_orders']);
    $current_uid = (int)($perms['current_uid'] ?? 0);

    $id = (int)$req['id'];
    $is_total_cancel = in_array((int)$req['id_status'], [5, 7, 8]);
    $id_nakupci = (int)($req['id_nakupci'] ?? 0);
    $nakupci_jmeno = trim($req['nakupci_jmeno'] ?? '');
    $is_my_nakup_req = ($id_nakupci > 0 && $id_nakupci === $current_uid);
    $snooze_active = !empty($req['souhrn_snooze_do']) && strtotime($req['souhrn_snooze_do']) > time();
    $surovina = htmlspecialchars($req['surovina_nazev'] ?? '', ENT_QUOTES);

    ob_start();
    ?>
    <div class="rd-request-actions">
        <button type="button" class="btn btn-xs btn-default btn-toggle-priority" data-id="<?= $id ?>" data-prio="<?= (int)$req['priorita'] ?>"
            title="Přepnout urgentní prioritu">
            <i class="glyphicon <?= ((int)$req['priorita'] === 1) ? 'glyphicon-exclamation-sign text-danger' : 'glyphicon-unchecked text-muted' ?>"></i>
            <?= ((int)$req['priorita'] === 1) ? 'Urgentní' : 'Priorita' ?>
        </button>

        <?php if (!$is_total_cancel): ?>
        <button type="button" class="btn btn-xs btn-warning btn-urge-task" data-id="<?= $id ?>" data-sur="<?= $surovina ?>"
            title="Urgovat řešitele">
            <i class="glyphicon glyphicon-flash"></i> Urgovat
        </button>
        <?php endif; ?>

        <?php if ($can_nakup && !$is_total_cancel): ?>
        <button type="button" class="btn btn-xs btn-default btn-digest-snooze" data-id="<?= $id ?>"
            data-snooze-until="<?= htmlspecialchars($req['souhrn_snooze_do'] ?? '', ENT_QUOTES) ?>"
            data-snooze-note="<?= htmlspecialchars($req['souhrn_snooze_poznamka'] ?? '', ENT_QUOTES) ?>"
            title="<?= $snooze_active ? 'Souhrn: dlouhé dodání' : 'Souhrn: snížit prioritu' ?>">
            <i class="glyphicon glyphicon-time <?= $snooze_active ? 'text-primary' : 'text-muted' ?>"></i> Souhrn snooze
        </button>
        <?php endif; ?>

        <?php if (!$is_orders && !$is_total_cancel): ?>
        <button type="button" class="btn btn-xs btn-info btn-ping-purchasing" data-id="<?= $id ?>" data-sur="<?= $surovina ?>"
            title="Vyžádat další nabídku od Nákupu">
            <i class="glyphicon glyphicon-bell"></i> Ping nákup
        </button>
        <?php endif; ?>

        <?php if ($can_claim_nakup && !$is_total_cancel && !$is_my_nakup_req): ?>
        <button type="button" class="btn btn-xs btn-primary btn-claim-request" data-id="<?= $id ?>"
            data-resitel-id="<?= $id_nakupci ?>" data-resitel-jmeno="<?= htmlspecialchars($nakupci_jmeno, ENT_QUOTES) ?>"
            title="Převzít požadavek">
            <i class="glyphicon glyphicon-hand-up"></i> Převzít
        </button>
        <?php endif; ?>

        <?php if (!$is_total_cancel && ($is_my_nakup_req || ($is_adm && $id_nakupci > 0))): ?>
        <button type="button" class="btn btn-xs btn-default btn-release-request" data-id="<?= $id ?>" title="Vzdávám to">
            <i class="glyphicon glyphicon-log-out"></i> Vzdát
        </button>
        <?php endif; ?>

        <?php if (($is_vyvoj || $is_adm) && in_array((int)$req['id_status'], [5, 7, 8])): ?>
        <button type="button" class="btn btn-xs btn-success btn-revive-req" data-id="<?= $id ?>" title="Oživit požadavek">
            <i class="glyphicon glyphicon-play"></i> Oživit
        </button>
        <?php endif; ?>

        <?php if (($is_vyvoj || $is_adm) && !$is_total_cancel): ?>
        <button type="button" class="btn btn-xs btn-default btn-postpone-req" data-id="<?= $id ?>" title="Odložit k ledu">
            <i class="glyphicon glyphicon-pause"></i> K ledu
        </button>
        <?php endif; ?>

        <?php if (($is_vyvoj || $is_adm) && (int)$req['id_status'] === 1): ?>
        <button type="button" class="btn btn-xs btn-default btn-edit-req" data-id="<?= $id ?>" data-sur="<?= $surovina ?>"
            data-bio="<?= (int)$req['bio'] ?>" data-vegan="<?= (int)$req['vegan'] ?>" data-bezlepek="<?= (int)$req['bezlepek'] ?>"
            data-kosher="<?= (int)$req['kosher'] ?>" data-halal="<?= (int)$req['halal'] ?>" data-prio="<?= (int)$req['priorita'] ?>"
            data-mnozstvi="<?= htmlspecialchars($req['Mnozstvi'] ?? $req['mnozstvi'] ?? '', ENT_QUOTES) ?>"
            data-mj="<?= htmlspecialchars($req['mj'] ?? 'kg', ENT_QUOTES) ?>"
            data-note="<?= htmlspecialchars($req['poznamka'] ?? '', ENT_QUOTES) ?>"
            data-zakaznici-ids="<?= htmlspecialchars($req['zakaznici_ids'] ?? '', ENT_QUOTES) ?>"
            data-produkty-ids="<?= htmlspecialchars($req['produkty_ids'] ?? '', ENT_QUOTES) ?>"
            title="Editovat požadavek">
            <i class="glyphicon glyphicon-pencil"></i> Upravit
        </button>
        <button type="button" class="btn btn-xs btn-danger btn-delete-req" data-id="<?= $id ?>" title="Zrušit požadavek">
            <i class="glyphicon glyphicon-trash"></i> Zrušit
        </button>
        <?php endif; ?>

        <?php if ($can_nakup && !$is_total_cancel): ?>
        <button type="button" class="btn btn-xs btn-warning btn-add-offer" data-id="<?= $id ?>" title="Přidat nabídku">
            <i class="glyphicon glyphicon-plus-sign"></i> Přidat nabídku
        </button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
