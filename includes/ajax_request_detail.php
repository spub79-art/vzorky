<?php
session_start();
include_once("db_connect.php");
@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

if (!isset($_POST['id'])) exit;
$id_pozadavek = (int)$_POST['id'];

$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);
$current_uid = $_SESSION['uid'] ?? 0;

// 1. Načtení detailu požadavku
$q_req = mysqli_query($conn, "SELECT p.*, s.nazev AS surovina_nazev, cs.nazev as status_nazev, cs.barva_hex 
                              FROM pozadavky p 
                              LEFT JOIN suroviny s ON p.id_surovina = s.id 
                              LEFT JOIN ciselnik_statusu cs ON p.id_status = cs.id
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
?>

<!-- SKRYTÉ ID PRO JAVASCRIPT (Aby věděl, co má po editaci překreslit) -->
<input type="hidden" id="currentReqDetailId" value="<?= $id_pozadavek ?>">

<div class="row" style="margin: 0;">

    <div class="col-md-4 rd-left-col">
        <h2 class="rd-header-title" style="flex-wrap: wrap; gap: 10px;">
            <span><?= htmlspecialchars($req['surovina_nazev']) ?></span>
            <small class="rd-header-id">#<?= $req['id'] ?> (zadáno: <?= date('j.n.Y', strtotime($req['datumPozadavek'])) ?>)</small>
        </h2>

        <div class="rd-badges-wrapper">
            <?php if ($req['bio'] == 1) echo '<span class="label label-success rd-badge">BIO</span>'; ?>
            <?php if ($req['bezlepek'] == 1) echo '<span class="label label-warning rd-badge">BEZ LEPKU</span>'; ?>
            <?php if ($req['vegan'] == 1) echo '<span class="label label-success rd-badge">VEGAN</span>'; ?>
            <?php if ($req['kosher'] == 1) echo '<span class="label rd-badge rd-badge-kosher">KOSHER</span>'; ?>
            <?php if ($req['halal'] == 1) echo '<span class="label rd-badge rd-badge-halal">HALAL</span>'; ?>
            <?php if ($req['priorita'] == 1) echo '<span class="label label-danger rd-badge">URGENT</span>'; ?>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><b>Zadání požadavku</b></div>
            <div class="panel-body rd-task-body" style="background-color: #fcfcfc;">
                <?= !empty(trim($req['poznamka'])) ? nl2br(htmlspecialchars(trim($req['poznamka']))) : '<i class="text-muted">Bez zadání</i>' ?>
            </div>
        </div>

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
            <div class="row">
                <?php
                $off_counter = 0;
                foreach ($offers as $off):
                    $off_counter++;
                    $is_ko = in_array($off['id_status'], [5, 7]);
                    $border_color = $off['barva_hex'] ?: '#ccc';

                    $bg_color = $is_ko ? '#fafafa' : '#fff';
                    $opacity = $is_ko ? '0.6' : '1';

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
                    <div class="col-md-6" style="margin-bottom: 20px;">
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
                                    <div class="rd-offer-price-main">
                                        <?= number_format($off['cena_nabidka'], 2, ',', ' ') ?> <?= htmlspecialchars($off['mena']) ?>
                                    </div>

                                    <?php
                                    $cena_czk = $off['cena_nabidka'];
                                    if (strtoupper($off['mena']) === 'EUR') {
                                        $kurz = defined('CNB_EUR_RATE') ? CNB_EUR_RATE : 25.0;
                                        $cena_czk = $off['cena_nabidka'] * $kurz;
                                    }

                                    $dopravne = 0;
                                    if (!empty($off['poznamka_nakup']) && preg_match('/\[Dopravné:\s*([0-9.,]+)\s*Kč\/MJ\]/ui', $off['poznamka_nakup'], $m)) {
                                        $dopravne = (float)str_replace(',', '.', $m[1]);
                                    }

                                    $celkem_czk = $cena_czk + $dopravne;

                                    if ($celkem_czk > 0 && (strtoupper($off['mena']) !== 'CZK' || $dopravne > 0)):
                                        ?>
                                        <div class="rd-offer-price-czk" title="Přepočteno kurzem + přičteno dopravné">
                                            ∑ <?= number_format($celkem_czk, 2, ',', ' ') ?> CZK <span class="rd-offer-price-note">(vč. dopravy)</span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="rd-offer-moq">
                                        MOQ: <?= $off['moq_mnozstvi'] ? $off['moq_mnozstvi'].' '.htmlspecialchars($off['moq_mj']) : '-' ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty(trim($off['poznamka_nakup']))): ?>
                                <div class="rd-offer-note-buyer" style="background: #eef7fa; border-left: 3px solid #5bc0de; padding: 6px 10px; margin-bottom: 10px; font-size: 12px;">
                                    <strong>Poznámka nákupu:</strong><br>
                                    <?= nl2br(htmlspecialchars(trim($off['poznamka_nakup']))) ?>
                                </div>
                            <?php endif; ?>

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
                    <?php if ($off_counter % 2 == 0) echo '<div class="clearfix visible-md visible-lg"></div>'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>