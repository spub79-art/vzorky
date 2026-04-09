<?php
session_start();
include_once("db_connect.php");
@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

if (!isset($_POST['id'])) exit;
$id_pozadavek = (int)$_POST['id'];

$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);

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

// 2. Načtení komentářů k požadavku
$req_comments = [];
$q_com_req = mysqli_query($conn, "SELECT * FROM board_poznamky WHERE typ_entity = 'pozadavek' AND id_entity = $id_pozadavek ORDER BY vytvoreno ASC");
if ($q_com_req) {
    while ($c = mysqli_fetch_assoc($q_com_req)) { $req_comments[] = $c; }
}

// 3. Načtení všech nabídek a jejich komentářů
$offers = [];
$q_off = mysqli_query($conn, "SELECT pn.*, d.nazev as dodavatel_nazev, cs.nazev as status_nazev, cs.barva_hex 
                              FROM pozadavky_nabidky pn 
                              LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id 
                              LEFT JOIN ciselnik_statusu cs ON pn.id_status = cs.id 
                              WHERE pn.id_pozadavek = $id_pozadavek 
                              ORDER BY pn.cena_nabidka ASC");
if ($q_off) {
    while ($o = mysqli_fetch_assoc($q_off)) {
        $o_id = $o['id'];
        $o['comments'] = [];
        $q_com_off = mysqli_query($conn, "SELECT * FROM board_poznamky WHERE typ_entity = 'nabidka' AND id_entity = $o_id ORDER BY vytvoreno ASC");
        if ($q_com_off) {
            while ($co = mysqli_fetch_assoc($q_com_off)) { $o['comments'][] = $co; }
        }
        $offers[] = $o;
    }
}
?>

<div class="row" style="margin: 0;">

    <div class="col-md-4 rd-left-col">
        <h2 class="rd-header-title">
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
            <div class="panel-body rd-task-body">
                <?= !empty(trim($req['poznamka'])) ? nl2br(htmlspecialchars(trim($req['poznamka']))) : '<i class="text-muted">Bez zadání</i>' ?>
            </div>
        </div>

        <h4 class="rd-disc-title">Diskuze k zadání</h4>
        <div class="rd-disc-scroll">
            <?php if (empty($req_comments)): ?>
                <div class="text-muted" style="font-size: 13px;">Zatím žádné komentáře.</div>
            <?php else: ?>
                <?php foreach($req_comments as $c): ?>
                    <div class="rd-comment-box">
                        <strong class="rd-comment-author"><?= htmlspecialchars($c['autor_jmeno']) ?></strong>
                        <span class="rd-comment-time">(<?= date('j.n.Y H:i', strtotime($c['vytvoreno'])) ?>)</span>
                        <div class="rd-comment-text"><?= nl2br(htmlspecialchars($c['text_poznamky'])) ?></div>
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

                    // Zde zůstávají dynamické styly (barvy se mění podle PHP)
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

                            if ($ftype == 'spec') $files_tds[] = $fname;
                            elseif ($ftype == 'lab') $files_coa[] = $fname;
                            else $files_other[] = $fname;
                        }
                    }
                    ?>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <div class="rd-offer-card" style="background-color: <?= $bg_color ?>; border-top-color: <?= $border_color ?>; opacity: <?= $opacity ?>;">

                            <div class="rd-offer-header">
                                <div>
                                    <h4 class="rd-offer-vendor">
                                        <?= htmlspecialchars($off['dodavatel_nazev']) ?>
                                        <span class="rd-offer-id">(#<?= $off['id'] ?>)</span>
                                    </h4>
                                    <span class="label rd-offer-status" style="background-color: <?= $border_color ?>;">
                                        <?= htmlspecialchars($off['status_nazev']) ?>
                                    </span>
                                </div>
                                <div class="rd-offer-price-col">
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
                                <div class="rd-offer-note-buyer">
                                    <?= nl2br(htmlspecialchars(trim($off['poznamka_nakup']))) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty(trim($off['poznamka_cena']))): ?>
                                <div class="rd-offer-syslog">
                                    <?= htmlspecialchars(trim($off['poznamka_cena'])) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($files_tds) || !empty($files_coa) || !empty($files_other)):

// Získáme přesnou cestu ke složce z databáze
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

                                // Nativní Nextcloud UI odkaz s parametrem scrollto
                                $safe_dl_base = "https://nextcloud.lifefood.eu/index.php/apps/files/?dir=" . $dir_path . "&scrollto=";
                                ?>
                                <div class="rd-offer-files-box">
                                    <div class="rd-offer-files-head">
                                        <i class="glyphicon glyphicon-paperclip"></i> Přiložené soubory
                                    </div>
                                    <div class="rd-offer-files-body">
                                        <?php if (!empty($files_tds)): ?>
                                            <div class="rd-file-cat-tds"><strong>TDS (Specifikace):</strong><br>
                                                <?php foreach($files_tds as $f):
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
                                                <?php foreach($files_coa as $f):
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
                                                <?php foreach($files_other as $f):
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

                            <h5 class="rd-offer-chat-title">Komunikace k nabídce</h5>
                            <div class="rd-offer-chat-scroll">
                                <?php if (empty($off['comments'])): ?>
                                    <em class="text-muted" style="font-size: 12px;">Žádné poznámky.</em>
                                <?php else: ?>
                                    <?php foreach ($off['comments'] as $co): ?>
                                        <div class="rd-offer-chat-item">
                                            <span class="rd-offer-chat-author"><?= htmlspecialchars($co['autor_jmeno']) ?>:</span>
                                            <span class="rd-offer-chat-text"><?= nl2br(htmlspecialchars($co['text_poznamky'])) ?></span>
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