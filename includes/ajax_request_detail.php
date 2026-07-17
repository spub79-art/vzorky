<?php
session_start();
include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("boardOfferActions.php");
@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

if (!isset($_POST['id'])) exit;
$id_pozadavek = (int)$_POST['id'];

include_once("permissions.php");
$perms = loadSessionPermissions();
$is_adm = $perms['is_adm'];
$can_nakup = $perms['can_nakup'];
$can_claim_nakup = $perms['can_claim_nakup'];
$can_portfolio = $perms['can_portfolio'];
$is_vyvoj = $perms['is_vyvoj'];
$is_kvalita = $perms['is_kvalita'];
$is_orders = $perms['is_orders'];
$current_uid = (int)$perms['current_uid'];
$users_tbl = defined('DB_TBL_USERS') ? DB_TBL_USERS : 'users';

// 1. Načtení detailu požadavku
$q_req = mysqli_query($conn, "SELECT p.*, s.nazev AS surovina_nazev, cs.nazev as status_nazev, cs.barva_hex, u_nak.jmeno AS nakupci_jmeno,
        (SELECT GROUP_CONCAT(pz.id_zakaznik SEPARATOR ',') FROM pozadavky_zakaznici pz WHERE pz.id_pozadavek = p.id) AS zakaznici_ids
                              FROM pozadavky p 
                              LEFT JOIN suroviny s ON p.id_surovina = s.id 
                              LEFT JOIN ciselnik_statusu cs ON p.id_status = cs.id
                              LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
                              WHERE p.id = $id_pozadavek");
if (!$q_req || mysqli_num_rows($q_req) == 0) {
    echo "<div class='alert alert-danger' style='margin: 20px;'>Požadavek nenalezen.</div>";
    exit;
}
$req = mysqli_fetch_assoc($q_req);

// 2. Načtení CELÉ historie (požadavek i všechny jeho nabídky) jedním dotazem
$history_req = [];
$history_off = [];
$q_hist = mysqli_query($conn, "SELECT * FROM historie_pozadavku WHERE id_pozadavek = $id_pozadavek ORDER BY vytvoreno DESC");
if ($q_hist) {
    while ($h = mysqli_fetch_assoc($q_hist)) {
        if ($h['id_nabidka'] == 0) {
            $history_req[] = $h;
        } else {
            $history_off[$h['id_nabidka']][] = $h;
        }
    }
}

// 3. Načtení všech nabídek
$offers = [];
$q_off = mysqli_query($conn, "SELECT pn.*, d.nazev as dodavatel_nazev, cs.nazev as status_nazev, cs.barva_hex 
                              FROM pozadavky_nabidky pn 
                              LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id 
                              LEFT JOIN ciselnik_statusu cs ON pn.id_status = cs.id 
                              WHERE pn.id_pozadavek = $id_pozadavek 
                              ORDER BY pn.cena_nabidka ASC");
if ($q_off) {
    while ($o = mysqli_fetch_assoc($q_off)) {
        $offers[] = $o;
    }
}

@include_once(__DIR__ . '/portfolio_helpers.php');
$pf_ctx = function_exists('pf_request_detail_products_context')
    ? pf_request_detail_products_context($conn, $id_pozadavek, (int)$req['id_surovina'])
    : ['products' => [], 'zakaznici' => [], 'has_links' => false];

$pf_from_product = false;
if (!empty($pf_ctx['products'])) {
    foreach ($pf_ctx['products'] as $pp) {
        if (empty($pp['ukonceny']) && !empty($pp['priorita'])) {
            $pf_from_product = true;
            break;
        }
    }
}
?>

<input type="hidden" id="currentReqDetailId" value="<?= $id_pozadavek ?>">

<div class="row" style="margin: 0;">

    <div class="col-md-4 rd-left-col">
        <h2 class="rd-header-title" style="flex-wrap: wrap; gap: 10px;">
            <span><?= htmlspecialchars($req['surovina_nazev']) ?></span>
            <?php
            // Najdeme, kdo požadavek založil, přímo z historie
            $zadavatel = "Neznámý uživatel";
            if (!empty($history_req)) {
                // Projdeme historii odzadu (nejstarší záznam)
                $reverse_hist = array_reverse($history_req);
                foreach ($reverse_hist as $hr) {
                    if ($hr['typ_zaznamu'] == 'zalozeni') {
                        $zadavatel = $hr['jmeno_user'];
                        break;
                    }
                }
            }
            ?>
            <small class="rd-header-id" style="display: block; margin-top: 5px; width: 100%;">
                #<?= $req['id'] ?> &bull; Zadal/a: <strong><?= htmlspecialchars($zadavatel) ?></strong> &bull; <?= date('j.n.Y', strtotime($req['datumPozadavek'])) ?>
                <?php
                $id_nakupci = (int)($req['id_nakupci'] ?? 0);
                $nakupci_jmeno = trim($req['nakupci_jmeno'] ?? '');
                $is_my_nakup_req = ($id_nakupci > 0 && $id_nakupci === $current_uid);
                ?>
                &bull; Nákup:
                <?php if ($id_nakupci > 0 && $nakupci_jmeno !== ''): ?>
                    <strong style="color:#337ab7;"><?= htmlspecialchars($nakupci_jmeno) ?></strong>
                <?php else: ?>
                    <span class="text-muted" style="font-style:italic;">volné</span>
                <?php endif; ?>
                <?php if ($can_claim_nakup && !$is_my_nakup_req): ?>
                        <i class="glyphicon glyphicon-hand-up text-primary btn-claim-request" style="cursor:pointer; margin-left:4px;"
                           data-id="<?= $req['id'] ?>" data-resitel-id="<?= $id_nakupci ?>"
                           data-resitel-jmeno="<?= htmlspecialchars($nakupci_jmeno, ENT_QUOTES) ?>"
                           title="<?= $id_nakupci > 0 ? 'Převzít (nyní: ' . htmlspecialchars($nakupci_jmeno, ENT_QUOTES) . ')' : 'Převzít' ?>"></i>
                    <?php endif; ?>
                    <?php if ($is_my_nakup_req || ($is_adm && $id_nakupci > 0)): ?>
                        <i class="glyphicon glyphicon-log-out text-muted btn-release-request" style="cursor:pointer; margin-left:2px;"
                           data-id="<?= $req['id'] ?>" title="Vzdávám to"></i>
                    <?php endif; ?>
            </small>
        </h2>

        <div class="rd-badges-wrapper">
            <?php if ($req['bio'] == 1) echo '<span class="label label-success rd-badge">BIO</span>'; ?>
            <?php if ($req['bezlepek'] == 1) echo '<span class="label label-warning rd-badge">BEZ LEPKU</span>'; ?>
            <?php if ($req['vegan'] == 1) echo '<span class="label label-success rd-badge">VEGAN</span>'; ?>
            <?php if ($req['kosher'] == 1) echo '<span class="label rd-badge rd-badge-kosher">KOSHER</span>'; ?>
            <?php if ($req['halal'] == 1) echo '<span class="label rd-badge rd-badge-halal">HALAL</span>'; ?>
            <?php if ($req['priorita'] == 1) echo '<span class="label label-danger rd-badge">URGENT</span>'; ?>
            <?php if ($pf_from_product && (int)$req['priorita'] === 1): ?>
                <span class="label label-warning rd-badge" title="Urgentní kvůli propojenému produktu"><i class="glyphicon glyphicon-flash"></i> z produktu</span>
            <?php endif; ?>
        </div>

        <?= renderRequestDetailToolbar($req, $perms) ?>

        <?php if (!empty($pf_ctx['products']) || !empty($pf_ctx['zakaznici'])): ?>
        <div class="panel panel-default rd-prod-context-panel">
            <div class="panel-heading" style="background:#f0f7fb; border-color:#bce8f1;">
                <b><i class="glyphicon glyphicon-briefcase"></i> Kontext vývoje</b>
                <?php if (count($pf_ctx['products']) > 1): ?>
                    <span class="text-muted" style="font-size:12px;font-weight:normal;"> — tento požadavek jde do <?= count($pf_ctx['products']) ?> produktů</span>
                <?php endif; ?>
            </div>
            <div class="panel-body" style="padding:10px 12px;">
                <?php if (!empty($pf_ctx['zakaznici']) && empty($pf_ctx['products'])): ?>
                    <div class="rd-prod-zak-row text-muted" style="margin-bottom:8px;font-size:13px;">
                        <i class="glyphicon glyphicon-user"></i>
                        <strong>Zákazníci požadavku:</strong>
                        <?= htmlspecialchars(implode(', ', array_column($pf_ctx['zakaznici'], 'nazev'))) ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($pf_ctx['products'] as $pp):
                    $rs = $pp['readiness_summary'] ?? [];
                    $ended = !empty($pp['ukonceny']);
                    $sur_total = (int)($rs['total'] ?? count($pp['suroviny']));
                    $sur_ok = (int)($rs['ok'] ?? 0);
                    $sur_block = (int)($rs['blocking'] ?? 0);
                    $zak_label = trim($pp['zakaznik_nazev'] ?? '');
                    if ($zak_label === '') $zak_label = '—';
                ?>
                <div class="rd-prod-card<?= $ended ? ' rd-prod-card-ended' : '' ?><?= !empty($rs['blocks_product']) ? ' rd-prod-card-blocks' : '' ?>">
                    <div class="rd-prod-card-head">
                        <div class="rd-prod-card-title">
                            <span class="rd-prod-zak"><?= htmlspecialchars($zak_label) ?></span>
                            <span class="rd-prod-arrow">→</span>
                            <strong class="rd-prod-name"><?= htmlspecialchars($pp['nazev']) ?></strong>
                            <?php if (!empty($pp['priorita']) && !$ended): ?>
                                <span class="label label-danger" style="font-size:10px;margin-left:4px;">URGENT</span>
                            <?php endif; ?>
                            <?php if ($ended): ?>
                                <span class="label label-default" style="font-size:10px;margin-left:4px;">ukončeno</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($can_portfolio): ?>
                        <a href="index.php?Portfolio=1&amp;pf_prod=<?= (int)$pp['id'] ?>" class="btn btn-xs btn-default rd-prod-portfolio-link" target="_blank" title="Otevřít v Portfoliu">
                            <i class="glyphicon glyphicon-new-window"></i> Portfolio
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($sur_total > 0): ?>
                    <div class="rd-prod-summary text-muted">
                        <?= $sur_total ?> surovin
                        <?php if ($sur_ok > 0): ?> · <?= $sur_ok ?> OK<?php endif; ?>
                        <?php if ($sur_block > 0): ?> · <span class="text-danger"><strong><?= $sur_block ?> brzdí</strong></span><?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($pp['suroviny'])): ?>
                    <ul class="rd-prod-suroviny list-unstyled">
                        <?php foreach ($pp['suroviny'] as $sur):
                            $rd = $sur['readiness'] ?? null;
                            $st = $rd['state'] ?? 'missing';
                            $st_label = $rd['label'] ?? '';
                            $is_cur = !empty($sur['is_current']);
                            $req_other = !empty($sur['req_id']) && (int)$sur['req_id'] !== $id_pozadavek;
                        ?>
                        <li class="rd-prod-sur rd-prod-sur-<?= htmlspecialchars($st) ?><?= $is_cur ? ' rd-prod-sur-current' : '' ?>">
                            <span class="rd-prod-sur-dot"></span>
                            <span class="rd-prod-sur-name">
                                <?php if ($is_cur): ?><i class="glyphicon glyphicon-hand-right text-primary" title="Tato surovina"></i> <?php endif; ?>
                                <?= htmlspecialchars($sur['nazev']) ?>
                                <?php if (!empty($sur['nazev_en'])): ?>
                                    <span class="text-muted" style="font-size:11px;">/ <?= htmlspecialchars($sur['nazev_en']) ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if ($st_label !== ''): ?>
                            <span class="rd-prod-sur-st"><?= htmlspecialchars($st_label) ?></span>
                            <?php endif; ?>
                            <?php if ($req_other && !empty($sur['req_id'])): ?>
                            <a href="index.php?Pozadavek=1&amp;req_id=<?= (int)$sur['req_id'] ?>" class="rd-prod-sur-req" title="Jiný požadavek">#<?= (int)$sur['req_id'] ?></a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted rd-prod-empty-sur" style="font-size:12px;margin:6px 0 0;">Zatím bez surovin v receptuře produktu.</p>
                    <?php endif; ?>

                    <?php if (!empty($pp['poznamka'])): ?>
                    <div class="rd-prod-poznamka">
                        <i class="glyphicon glyphicon-pushpin"></i>
                        <span><?= nl2br(htmlspecialchars($pp['poznamka'])) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($pp['komentare'])): ?>
                    <div class="rd-prod-chat">
                        <div class="rd-prod-chat-label"><i class="glyphicon glyphicon-comment"></i> Diskuze produktu</div>
                        <?php foreach ($pp['komentare'] as $km): ?>
                        <div class="rd-prod-chat-row">
                            <span class="rd-prod-chat-meta"><?= htmlspecialchars($km['jmeno']) ?> · <?= date('j.n. H:i', strtotime($km['vytvoreno'])) ?></span>
                            <div class="rd-prod-chat-text"><?= nl2br(htmlspecialchars($km['text'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading"><b>Zadání požadavku</b></div>
            <div class="panel-body rd-task-body" style="background-color: #fcfcfc;">
                <?php $zadane_mnoz = formatPozadavekMnozstvi($req); ?>
                <?php if ($zadane_mnoz): ?>
                    <div class="rd-zadani-mnozstvi"><i class="glyphicon glyphicon-scale"></i> <strong>Zadání množství:</strong> <?= htmlspecialchars($zadane_mnoz) ?></div>
                <?php endif; ?>
                <?= !empty(trim($req['poznamka'])) ? nl2br(htmlspecialchars(trim($req['poznamka']))) : '<i class="text-muted">Bez textového zadání</i>' ?>
            </div>
        </div>

        <?php if (!empty($offers)): ?>
        <div class="panel panel-default rd-wf-panel" style="border-color: #8e44ad;">
            <div class="panel-heading" style="background: #f9f5fc; color: #6f42c1;">
                <b><i class="glyphicon glyphicon-road"></i> Průběh nabídek</b>
                <span class="rd-wf-panel-hint">TDS → CENA OK → Kvalita → Nutri → Vzorek → COA → Test</span>
            </div>
            <div class="panel-body rd-wf-panel-body">
                <?= render_detail_workflow_matrix($offers) ?>
            </div>
        </div>
        <?php endif; ?>

        <h4 class="rd-disc-title"><i class="glyphicon glyphicon-time" style="color:#999; font-size:12px;"></i> Historie požadavku</h4>
        <div class="rd-disc-scroll" style="background: #fff; border: 1px solid #eee; padding: 10px; border-radius: 4px; max-height: 400px; overflow-y: auto;">
            <?php if (empty($history_req)): ?>
                <div class="text-muted" style="font-size: 13px;">Zatím žádná historie.</div>
            <?php else: ?>
                <?php foreach($history_req as $h):
                    $is_system = !in_array($h['typ_zaznamu'], ['komentar', 'komentar_urgentni']);
                    $is_urgent_msg = ($h['typ_zaznamu'] === 'komentar_urgentni');
                    // Umožníme mazat/upravovat i vlastní systémové zprávy
                    $can_delete = ($h['id_user'] == $current_uid || $is_adm);

                    $icon = 'glyphicon-cog text-muted';
                    if ($h['typ_zaznamu'] == 'urgence') $icon = 'glyphicon-flash text-warning';
                    if ($h['typ_zaznamu'] == 'zalozeni') $icon = 'glyphicon-plus text-success';
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
                    <div style="margin-bottom: 6px; font-size: 11.5px; line-height: 1.3; border-bottom: 1px dotted #e9e9e9; padding-bottom: 4px;">
                        <div style="color: #777; font-size: 10.5px; margin-bottom: 2px;">
                            <i class="glyphicon <?= $icon ?>" style="font-size: 9px; margin-right: 3px;"></i>
                            <?= htmlspecialchars($h['jmeno_user']) ?> &bull; <?= date('j.n. H:i', strtotime($h['vytvoreno'])) ?>
                            <?php if ($can_delete): ?>
                                <span style="float: right;">
                                    <i class="glyphicon glyphicon-pencil text-primary btn-edit-history" data-id="<?= $h['id'] ?>" data-text="<?= htmlspecialchars($h['text_hodnota'], ENT_QUOTES) ?>" title="Upravit poznámku" style="cursor: pointer; font-size: 10px; margin-right: 6px;"></i>
                                    <i class="glyphicon glyphicon-remove text-danger btn-delete-history" data-id="<?= $h['id'] ?>" title="Smazat poznámku" style="cursor: pointer; font-size: 10px;"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="padding-left: 14px; <?= $is_system ? 'color: #555;' : 'color: #222; font-weight: 500;' ?>">
                            <span <?= $is_urgent_msg ? 'style="'.$text_style.'"' : '' ?>><?= nl2br(htmlspecialchars($h['text_hodnota'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-8 rd-right-col">
        <h3 class="rd-offers-title">Vložené nabídky od dodavatelů</h3>

        <?php if (empty($offers)): ?>
            <div class="alert alert-warning" style="font-size: 15px;">
                <i class="glyphicon glyphicon-info-sign"></i> K tomuto požadavku zatím nebyla vložena žádná nabídka.
            </div>
        <?php else: ?>
            <div class="rd-offers-grid">
                <?php
                foreach ($offers as $off):
                    $is_ko = nabidkaJeZamitnuta($off['id_status']);
                    $is_frozen = nabidkaJeOdlozena($off['id_status']);
                    $off_st = (int)$off['id_status'];
                    $sib = nabidka_sibling_counts($offers, $off['id']);
                    $border_color = $off['barva_hex'] ?: '#ccc';
                    $card_colors = nabidka_offer_card_colors($off);
                    $bg_color = $card_colors['bg'];
                    $opacity = $card_colors['opacity'];

                    $files_tds = []; $files_coa = []; $files_other = [];
                    if (!empty($off['seznam_souboru'])) {
                        $pts = explode('^', $off['seznam_souboru']);
                        foreach ($pts as $pt) {
                            if (empty(trim($pt))) continue;
                            $finfo = explode('~', $pt);
                            $fname = $finfo[0];
                            $ftype = $finfo[1] ?? 'other';

                            if ($ftype == 'spec') $files_tds[] = $files_tds[] = $fname;
                            elseif ($ftype == 'lab') $files_coa[] = $fname;
                            else $files_other[] = $fname;
                        }
                    }
                    ?>
                    <div class="rd-offer-grid-item">
                        <div class="rd-offer-card" style="background-color: <?= $bg_color ?>; border-top-color: <?= $border_color ?>; opacity: <?= $opacity ?>;">

                            <div class="rd-offer-header" style="flex-wrap: wrap;">
                                <div style="margin-bottom: 10px;">
                                    <h4 class="rd-offer-vendor">
                                        <?= htmlspecialchars($off['dodavatel_nazev']) ?>
                                        <span class="rd-offer-id">(#<?= $off['id'] ?>)</span>
                                    </h4>
                                    <span class="label rd-offer-status" style="background-color: <?= $border_color ?>;">
                                        <?= htmlspecialchars($off['status_nazev']) ?>
                                    </span>
                                </div>
                                <div class="rd-offer-price-col" style="text-align: left; width: 100%;">
                                    <?php
                                    $cena_czk = (float)$off['cena_nabidka'];
                                    $mena_off = strtoupper($off['mena'] ?? 'CZK');
                                    if ($mena_off === 'EUR') {
                                        $kurz = defined('CNB_EUR_RATE') ? CNB_EUR_RATE : 25.0;
                                        $cena_czk = $off['cena_nabidka'] * $kurz;
                                    } elseif ($mena_off === 'USD') {
                                        $kurz = defined('CNB_USD_RATE') ? CNB_USD_RATE : 23.5;
                                        $cena_czk = $off['cena_nabidka'] * $kurz;
                                    }

                                    $dopravne = 0;
                                    if (!empty($off['poznamka_nakup']) && preg_match('/\[Dopravné:\s*([0-9.,]+)\s*Kč\/MJ\]/ui', $off['poznamka_nakup'], $m)) {
                                        $dopravne = (float)str_replace(',', '.', $m[1]);
                                    }

                                    $celkem_czk = $cena_czk + $dopravne;
                                    $price_tip = 'Cena do receptury vč. dopravy (CZK/MJ)';
                                    if ($mena_off !== 'CZK' && (float)$off['cena_nabidka'] > 0) {
                                        $price_tip .= ' · nabídka '
                                            . number_format((float)$off['cena_nabidka'], 2, ',', ' ')
                                            . ' ' . $mena_off;
                                    }
                                    ?>
                                    <div class="rd-offer-price-main" title="<?= htmlspecialchars($price_tip) ?>">
                                        <?= number_format($celkem_czk, 2, ',', ' ') ?> CZK
                                    </div>

                                    <div class="rd-offer-moq">
                                        MOQ: <?= $off['moq_mnozstvi'] ? $off['moq_mnozstvi'].' '.htmlspecialchars($off['moq_mj']) : '-' ?>
                                    </div>
                                    <?php
                                    $popt_qty = formatPozadovaneMnozstvi($off['pozadovane_mnozstvi'] ?? '');
                                    ?>
                                    <?php if ($off_st == 2): ?>
                                    <?php if (function_exists('wf_delegace_is_active') && wf_delegace_is_active($off)): ?>
                                    <div class="rd-offer-poptavka" style="color:#f0ad4e;">↪ Odbočka: řeší <?= htmlspecialchars(wf_delegace_dept_label($off['wf_delegace_komu'])) ?></div>
                                    <?php else: ?>
                                    <div class="rd-offer-poptavka rd-offer-poptavka-pending">Poptávka: <em>po schválení ceny (CENA OK)</em></div>
                                    <?php endif; ?>
                                    <?php elseif ($popt_qty !== null): ?>
                                    <div class="rd-offer-poptavka">
                                        <i class="glyphicon glyphicon-shopping-cart"></i> Poptávka vývoje: <?= htmlspecialchars($popt_qty) ?>
                                    </div>
                                    <?php elseif (nabidkaPotrebujePoptavku($off_st)): ?>
                                    <div class="rd-offer-poptavka rd-offer-poptavka-missing">
                                        <i class="glyphicon glyphicon-warning-sign"></i> Poptávka vývoje: <strong>nezadáno</strong>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty(trim($off['poznamka_nakup']))): ?>
                                <div class="rd-offer-note-buyer" style="background: #eef7fa; border-left: 3px solid #5bc0de; padding: 6px 10px; margin-bottom: 10px; font-size: 12px;">
                                    <strong>Poznámka nákupu:</strong><br>
                                    <?= nl2br(htmlspecialchars(trim($off['poznamka_nakup']))) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (function_exists('wf_delegace_banner_html')) echo wf_delegace_banner_html($off); ?>

                            <?php
                            $off['_sib_active'] = $sib['active'];
                            echo renderOfferActionButtons($off, [
                                'is_adm' => $is_adm,
                                'can_nakup' => $can_nakup,
                                'is_vyvoj' => $is_vyvoj,
                                'is_quality' => $is_kvalita,
                                'filter_phase' => 0,
                                'history_off' => $history_off[$off['id']] ?? [],
                                'layout' => 'detail',
                            ]);
                            ?>

                            <?php if (!empty($files_tds) || !empty($files_coa) || !empty($files_other)):
                                $link_doc = trim($off['link_dokumentace']);
                                $dir_path = "";

                                if (!empty($link_doc) && strpos($link_doc, 'dir=') !== false) {
                                    $parsed = parse_url($link_doc);
                                    parse_str($parsed['query'] ?? '', $query_params);
                                    $dir_path = $query_params['dir'] ?? '';
                                }

                                if (empty($dir_path)) {
                                    $base_dir = (defined('IS_DEV') && IS_DEV) ? "DEVEL_DOKUMENTACE_VZORKU" : "DOKUMENTACE_VZORKU";
                                    $folder_req_name = $id_pozadavek . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $req['surovina_nazev']);
                                    $folder_off_name = $off['id'] . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', ($off['dodavatel_nazev'] ?? 'Neznamy_dodavatel'));
                                    $dir_path = "/$base_dir/" . rawurlencode($folder_req_name) . "/" . rawurlencode($folder_off_name);
                                }

                                $safe_dl_base = "https://nextcloud.lifefood.eu/index.php/apps/files/?dir=" . $dir_path . "&scrollto=";
                                ?>
                                <div class="rd-offer-files-box">
                                    <div class="rd-offer-files-head">
                                        <i class="glyphicon glyphicon-paperclip"></i> Přiložené soubory
                                    </div>
                                    <div class="rd-offer-files-body" style="word-wrap: break-word;">
                                        <?php if (!empty($files_tds)): ?>
                                            <div class="rd-file-cat-tds"><strong>TDS (Specifikace):</strong><br>
                                                <?php foreach(array_unique($files_tds) as $f):
                                                    $dl_link = $safe_dl_base . rawurlencode($f);
                                                    ?>
                                                    <div class="rd-file-link">
                                                        <a href="<?= htmlspecialchars($dl_link) ?>" target="_blank">
                                                            <i class="glyphicon glyphicon-file rd-file-icon-tds"></i> <?= htmlspecialchars($f) ?>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($files_coa)): ?>
                                            <div class="rd-file-cat-coa"><strong>COA (Laboratoř):</strong><br>
                                                <?php foreach(array_unique($files_coa) as $f):
                                                    $dl_link = $safe_dl_base . rawurlencode($f);
                                                    ?>
                                                    <div class="rd-file-link">
                                                        <a href="<?= htmlspecialchars($dl_link) ?>" target="_blank">
                                                            <i class="glyphicon glyphicon-file rd-file-icon-coa"></i> <?= htmlspecialchars($f) ?>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($files_other)): ?>
                                            <div class="rd-file-cat-other"><strong>Ostatní:</strong><br>
                                                <?php foreach(array_unique($files_other) as $f):
                                                    $dl_link = $safe_dl_base . rawurlencode($f);
                                                    ?>
                                                    <div class="rd-file-link">
                                                        <a href="<?= htmlspecialchars($dl_link) ?>" target="_blank">
                                                            <i class="glyphicon glyphicon-file rd-file-icon-other"></i> <?= htmlspecialchars($f) ?>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <h5 class="rd-offer-chat-title"><i class="glyphicon glyphicon-list-alt" style="color:#aaa; font-size:11px;"></i> Deník událostí k nabídce</h5>
                            <div class="rd-offer-chat-scroll" style="background: #fafafa; border: 1px solid #e3e3e3; padding: 8px; border-radius: 3px; max-height: 250px; overflow-y: auto;">
                                <?php if (empty($history_off[$off['id']])): ?>
                                    <em class="text-muted" style="font-size: 12px;">Zatím bez záznamů.</em>
                                <?php else: ?>
                                    <?php foreach ($history_off[$off['id']] as $h):
                                        $is_system = !in_array($h['typ_zaznamu'], ['komentar', 'komentar_urgentni']);
                                        $is_urgent_msg = ($h['typ_zaznamu'] === 'komentar_urgentni');
                                        $can_delete = ($h['id_user'] == $current_uid || $is_adm);

                                        $icon = 'glyphicon-cog text-muted';
                                        if ($h['typ_zaznamu'] == 'urgence') $icon = 'glyphicon-flash text-warning';
                                        if ($h['typ_zaznamu'] == 'zalozeni') $icon = 'glyphicon-plus text-success';
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
                                        <div style="margin-bottom: 6px; font-size: 11.5px; line-height: 1.3; border-bottom: 1px dotted #e9e9e9; padding-bottom: 4px;">
                                            <div style="color: #777; font-size: 10.5px; margin-bottom: 2px;">
                                                <i class="glyphicon <?= $icon ?>" style="font-size: 9px; margin-right: 3px;"></i>
                                                <?= htmlspecialchars($h['jmeno_user']) ?> &bull; <?= date('j.n. H:i', strtotime($h['vytvoreno'])) ?>
                                                <?php if ($can_delete): ?>
                                                    <span style="float: right;">
                                                        <i class="glyphicon glyphicon-pencil text-primary btn-edit-history" data-id="<?= $h['id'] ?>" data-text="<?= htmlspecialchars($h['text_hodnota'], ENT_QUOTES) ?>" title="Upravit poznámku" style="cursor: pointer; font-size: 10px; margin-right: 6px;"></i>
                                                        <i class="glyphicon glyphicon-remove text-danger btn-delete-history" data-id="<?= $h['id'] ?>" title="Smazat poznámku" style="cursor: pointer; font-size: 10px;"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="padding-left: 14px; <?= $is_system ? 'color: #555;' : 'color: #222; font-weight: 500;' ?>">
                                                <span <?= $is_urgent_msg ? 'style="'.$text_style.'"' : '' ?>><?= nl2br(htmlspecialchars($h['text_hodnota'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>