<?php
include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("boardOfferRow.php");

@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);
$is_orders = (!empty($_SESSION['orders']) && $_SESSION['orders'] == 1);
$is_vyvoj = (!empty($_SESSION['vyvoj']) && $_SESSION['vyvoj'] == 1);
$is_quality = (!empty($_SESSION['kvalita']) && $_SESSION['kvalita'] == 1);
$is_cumil = (!empty($_SESSION['cumil']) && $_SESSION['cumil'] == 1);

$last_change_time = file_exists('last_change.txt') ? file_get_contents('last_change.txt') : time();

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

// ZMĚNA: Zákazníci se nyní tahají přes vnořený SELECT z vazební tabulky
$sql = "SELECT p.*, s.nazev AS surovina_nazev, 
        (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ') 
         FROM pozadavky_zakaznici pz 
         JOIN zakaznici z ON pz.id_zakaznik = z.id 
         WHERE pz.id_pozadavek = p.id) as zakaznici_seznam,
        GROUP_CONCAT(CONCAT(
            IFNULL(d.nazev, 'Neznámý'), '|', 
            IFNULL(pn.cena_nabidka, '0'), '|', 
            IFNULL(pn.mena, 'CZK'), '|', 
            IFNULL(pn.id, '0'), '|', 
            IFNULL(cs.barva_hex, '#ccc'), '|', 
            IFNULL(pn.vzorek_dorazil, ''), '|', 
            IFNULL(pn.id, '0'), '|', 
            IFNULL(pn.id_status, '0'), '|', 
            IFNULL(pn.updated_at, ''), '|', 
            IFNULL(pn.poznamka_cena, ''), '|', 
            IFNULL(pn.poznamka_vzorek, ''), '|', 
            IFNULL(pn.vzorek_objednan, ''), '|', 
            IFNULL(pn.link_dokumentace, ''), '|', 
            IFNULL(pn.seznam_souboru, ''), '|', 
            IFNULL(pn.sarze, ''), '|', 
            IFNULL(pn.pozadovane_mnozstvi, ''), '|',
            IFNULL(pn.moq_mnozstvi, ''), '|',
            IFNULL(pn.moq_mj, 'kg'), '|',
            IFNULL(pn.poznamka_nakup, '')
        ) SEPARATOR ';;') as nabidky_raw
        FROM pozadavky p 
        LEFT JOIN suroviny s ON p.id_surovina = s.id 
        LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
        LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
        LEFT JOIN ciselnik_statusu cs ON pn.id_status = cs.id
        GROUP BY p.id HAVING p.id_status != 6 ORDER BY p.datumPozadavek DESC";

$result = mysqli_query($conn, $sql);
$f1 = $f2 = $f3 = [];

$history_req = [];
$history_off = [];
$res_hist = mysqli_query($conn, "SELECT * FROM historie_pozadavku ORDER BY vytvoreno DESC");
if ($res_hist) {
    while ($h = mysqli_fetch_assoc($res_hist)) {
        if ($h['id_nabidka'] == 0) {
            $history_req[$h['id_pozadavek']][] = $h;
        } else {
            $history_off[$h['id_nabidka']][] = $h;
        }
    }
}

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {

        $p1_active = $p2_active = $p3_active = $has_any_active = false;
        $p1_visible = $p2_visible = $p3_visible = false;

        $is_req_cancelled = in_array((int)$row['id_status'], [5, 7]);

        if (!empty($row['nabidky_raw'])) {
            foreach (explode(';;', $row['nabidky_raw']) as $o) {
                $pts = explode('|', $o);
                if (count($pts) < 8 || $pts[6] == '0') continue;
                $s_id = (int)$pts[7];
                if (!in_array($s_id, [5, 7])) {
                    $has_any_active = true;

                    if (in_array($s_id, [10, 4])) {
                        $p3_active = true; $p3_visible = true;
                    } elseif (in_array($s_id, [3, 8, 9, 11, 12, 13])) {
                        $p2_active = true; $p2_visible = true;
                    } else {
                        $p1_active = true; $p1_visible = true;
                    }
                } else {
                    $p1_active = true;
                }
            }
        }

        if (!$has_any_active) {
            $p1_active = true;
            $row['buyer_must_act'] = !$is_req_cancelled;
        } else {
            $row['buyer_must_act'] = false;
        }

        $visible_phases = ($p1_visible ? 1 : 0) + ($p2_visible ? 1 : 0) + ($p3_visible ? 1 : 0);
        $is_spread = ($visible_phases > 1);
        $row['color_bg'] = getUniqueColor($row['id'], $is_spread);

        if ($is_req_cancelled) {
            $f1[] = $row;
        } else {
            if ($p1_active) $f1[] = $row;
            if ($p2_active) $f2[] = $row;
            if ($p3_active) $f3[] = $row;
        }
    }
}
?>

<?php if ($is_orders || $is_adm): ?>
    <div class="row" style="margin-bottom: 15px; padding: 0 15px;">
        <button id="btnOpenExportModal" class="btn btn-primary" style="float: right;">
            <i class="glyphicon glyphicon-list-alt"></i> Generátor "Co sháníme"
        </button>
    </div>
<?php endif; ?>

<div class="row" id="board-container">
    <?php
    $cols = [
        ['data'=>$f1, 't'=>'1. Fáze: Výběr', 'c'=>'info', 'id'=>1],
        ['data'=>$f2, 't'=>'2. Fáze: Dokumenty', 'c'=>'success', 'id'=>2],
        ['data'=>$f3, 't'=>'3. Fáze: Testování', 'c'=>'warning', 'id'=>3]
    ];

    foreach ($cols as $col): ?>
        <div class="col-lg-4 board-col">
            <div class="panel panel-<?= $col['c'] ?> board-panel">
                <div class="panel-heading board-panel-heading">
                    <b><?= $col['t'] ?></b>
                    <span class="badge pull-right">
                        <?php
                        $active_count = 0;
                        foreach($col['data'] as $item) {
                            if (!in_array((int)$item['id_status'], [5, 7])) $active_count++;
                        }
                        echo $active_count;
                        ?>
                    </span>
                </div>
                <div class="panel-body board-panel-body">
                    <?php foreach ($col['data'] as $row):
                        $is_total_cancel = in_array((int)$row['id_status'], [5, 7]);
                        $all_offers_for_this = [];

                        if (!empty($row['nabidky_raw'])) {
                            foreach(explode(';;', $row['nabidky_raw']) as $o) {
                                $pts = explode('|', $o);
                                if (count($pts) > 6 && $pts[6] != '0') {
                                    $s_id = (int)$pts[7];
                                    $is_ko = in_array($s_id, [5, 7]);

                                    $phase_of_offer = 1;
                                    if (!$is_ko) {
                                        if (in_array($s_id, [10, 4])) $phase_of_offer = 3;
                                        elseif (in_array($s_id, [3, 8, 9, 11, 12, 13])) $phase_of_offer = 2;
                                    }

                                    if ($is_total_cancel && $col['id'] == 1) {
                                        $all_offers_for_this[] = $pts;
                                    } elseif (!$is_total_cancel && $phase_of_offer == $col['id']) {
                                        $all_offers_for_this[] = $pts;
                                    }
                                }
                            }
                        }

                        if (empty($all_offers_for_this) && $col['id'] != 1) {
                            continue;
                        }

                        $is_urgent = ($row['buyer_must_act'] && ($is_orders || $is_adm) && $col['id'] == 1 && !$is_total_cancel);

                        $is_grey = ($row['color_bg'] == '#e2e6ea');
                        if (!$is_grey) {
                            list($r, $g, $b) = sscanf($row['color_bg'], "#%02x%02x%02x");
                            $card_bg = $is_total_cancel ? '#f9f9f9' : "rgba($r, $g, $b, 0.08)";
                        } else {
                            $card_bg = $is_total_cancel ? '#f9f9f9' : '#fafafa';
                        }

                        $border_top_color = $is_total_cancel ? '#ccc' : ($is_grey ? '#d1d5da' : $row['color_bg']);

                        $all_ko = (!empty($all_offers_for_this));
                        foreach ($all_offers_for_this as $pt) { if (!in_array((int)$pt[7], [5, 7])) $all_ko = false; }
                        $card_classes = "req-card " . ($is_urgent ? 'req-card-urgent needs-my-action ' : '');
                        if ($is_total_cancel || ($col['id'] == 1 && $all_ko && !$is_urgent && empty($all_offers_for_this) == false)) {
                            $card_classes .= ' offer-rejected';
                        }

                        $status_class = $is_total_cancel ? 'is-cancelled' : 'is-active';
                        $badge_text_color = getContrastColor($row['color_bg']);
                        ?>

                        <div class="<?= $card_classes ?>" data-req-id="<?= $row['id'] ?>" data-req-name="<?= htmlspecialchars(strtolower($row['surovina_nazev'])) ?>" style="background-color: <?= $card_bg ?>; border-top-color: <?= $border_top_color ?>;">

                            <div class="req-header">
                                <div class="req-title-row btn-open-detail" data-id="<?= $row['id'] ?>" style="cursor: pointer; transition: color 0.2s;" onmouseover="this.style.color='#337ab7'" onmouseout="this.style.color=''">

                                    <?php
                                    $date_created = strtotime($row['datumPozadavek']);
                                    $diff_hours = (time() - $date_created) / 3600;

                                    $aging_style = "";
                                    $aging_icon = "";

                                    if (!$is_total_cancel) {
                                        if ($diff_hours > 100) {
                                            $aging_style = "color: #d9534f; font-weight: bold;";
                                            $aging_icon = " <span title='Více než 100h v procesu!'>🔥</span>";
                                        } elseif ($diff_hours > 72) {
                                            $aging_style = "color: #f0ad4e;";
                                        }
                                    }
                                    ?>
                                    <strong class="req-title <?= $status_class ?>" style="pointer-events: none; <?= $aging_style ?>">
                                        <?= htmlspecialchars($row['surovina_nazev']) ?>
                                        <span class="text-muted" style="font-weight:normal; font-size: 11px; margin-left: 5px; <?= $aging_style ? 'color:inherit;' : '' ?>">
                                            (<?= date('j.n.', $date_created) ?>)
                                        </span>
                                        <?= $aging_icon ?>
                                    </strong>

                                    <div class="req-actions" style="pointer-events: none;">
                                        <i class="glyphicon <?= ($row['priorita'] == 1) ? 'glyphicon-exclamation-sign text-danger' : 'glyphicon-unchecked text-muted' ?> btn-toggle-priority no-detail-trigger"
                                           style="pointer-events: auto; cursor:pointer; margin-right: 8px;"
                                           data-id="<?= $row['id'] ?>"
                                           data-prio="<?= $row['priorita'] ?>"
                                           title="Přepnout urgentní prioritu"></i>

                                        <?php if (!$is_total_cancel): ?>
                                            <i class="glyphicon glyphicon-flash text-warning btn-urge-task no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer; font-size: 14px; margin-right: 8px;"
                                               data-id="<?= $row['id'] ?>"
                                               data-sur="<?= htmlspecialchars($row['surovina_nazev']) ?>"
                                               title="Urgovat řešitele (Pošle upozornění zodpovědnému oddělení na Telegram)"></i>
                                        <?php endif; ?>

                                        <?php if (!$is_orders && !$is_total_cancel): ?>
                                            <i class="glyphicon glyphicon-bell text-info btn-ping-purchasing no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer; font-size: 14px; margin-right: 8px;"
                                               data-id="<?= $row['id'] ?>"
                                               data-sur="<?= htmlspecialchars($row['surovina_nazev']) ?>"
                                               title="Vyžádat dohledání další nabídky od Nákupu"></i>
                                        <?php endif; ?>

                                        <?php if (($is_vyvoj || $is_adm) && $row['id_status'] == 1): ?>
                                            <i class="glyphicon glyphicon-pencil btn-edit-req no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer; margin-right: 8px;"
                                               data-id="<?= $row['id'] ?>"
                                               data-sur="<?= htmlspecialchars($row['surovina_nazev']) ?>"
                                               data-bio="<?= $row['bio'] ?>"
                                               data-vegan="<?= $row['vegan'] ?>"
                                               data-bezlepek="<?= $row['bezlepek'] ?>"
                                               data-kosher="<?= $row['kosher'] ?>"
                                               data-halal="<?= $row['halal'] ?>"
                                               data-prio="<?= $row['priorita'] ?>"
                                               data-note="<?= htmlspecialchars($row['poznamka'] ?? '') ?>"
                                               data-zakaznik="<?= htmlspecialchars($row['zakaznik'] ?? '') ?>"
                                               title="Editovat požadavek"></i>

                                            <i class="glyphicon glyphicon-trash text-danger btn-delete-req no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer;"
                                               data-id="<?= $row['id'] ?>"
                                               title="Zrušit požadavek"></i>
                                        <?php endif; ?>

                                        <span class="req-id-badge <?= $status_class ?> no-detail-trigger" <?php if(!$is_total_cancel) echo "style='background-color: ".$row['color_bg']."; color: ".$badge_text_color.";'"; ?> title="Otevřít detail">
                                            <i class="glyphicon glyphicon-zoom-in" style="font-size: 11px; margin-right: 2px;"></i><?= $row['id'] ?>
                                        </span>
                                    </div>
                                </div>

                                <?php renderBadges($row); ?>

                                <?php // ZMĚNA: Přidáno pole zákazník (vykreslení z vazební tabulky) ?>
                                <?php if (!empty($row['zakaznici_seznam'])): ?>
                                    <div style="font-size: 11px; color: #8e44ad; font-weight: bold; margin-bottom: 4px; padding-left: 2px;">
                                        <i class="glyphicon glyphicon-user"></i> Zákazníci: <?= htmlspecialchars($row['zakaznici_seznam']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty(trim($row['poznamka']))): ?>
                                    <div class="req-note-box <?= $status_class ?>">
                                        <i class="glyphicon glyphicon-info-sign req-note-icon <?= $status_class ?>"></i>
                                        <strong>Zadání:</strong> <?= nl2br(htmlspecialchars(trim($row['poznamka']))) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($history_req[$row['id']])): ?>
                                    <div class="offer-sys-msg" style="margin-top: 6px; padding: 4px 6px; background: #fafafa; border: 1px solid #e3e3e3; border-radius: 3px; max-height: 120px; overflow-y: auto;">
                                        <?php
                                        $all_req_hist = $history_req[$row['id']];
                                        $zobrazeno_req_hist = array_slice($all_req_hist, 0, 5);

                                        foreach($zobrazeno_req_hist as $h):
                                            $is_system = !in_array($h['typ_zaznamu'], ['komentar', 'komentar_urgentni']);
                                            $is_urgent_msg = ($h['typ_zaznamu'] === 'komentar_urgentni');
                                            $is_mine = (isset($_SESSION['uid']) && $h['id_user'] == $_SESSION['uid']);
                                            $can_delete = (!$is_system && ($is_mine || $is_adm));

                                            $icon = 'glyphicon-cog text-muted';
                                            if ($h['typ_zaznamu'] == 'urgence') $icon = 'glyphicon-flash text-warning';
                                            if ($h['typ_zaznamu'] == 'zalozeni') $icon = 'glyphicon-plus text-success';

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

                                                <span <?= $is_urgent_msg ? 'style="'.$text_style.'"' : '' ?> id="comment_text_<?= $h['id'] ?>"><?= nl2br(htmlspecialchars($h['text_hodnota'])) ?></span>

                                                <?php // ZMĚNA: Přidána editace pro zadání ?>
                                                <?php if ($can_delete): ?>
                                                    <span style="float: right; margin-top: 1px;">
                                                        <i class="glyphicon glyphicon-pencil text-primary btn-edit-history" data-id="<?= $h['id'] ?>" data-text="<?= htmlspecialchars($h['text_hodnota'], ENT_QUOTES) ?>" title="Upravit poznámku" style="cursor: pointer; font-size: 10px; margin-right: 6px;"></i>
                                                        <i class="glyphicon glyphicon-remove text-danger btn-delete-history" data-id="<?= $h['id'] ?>" title="Smazat poznámku" style="cursor: pointer; font-size: 10px;"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (count($all_req_hist) > 5): ?>
                                            <div style="font-size: 10px; color: #999; text-align: center; margin-top: 4px; border-top: 1px dashed #ddd; padding-top: 2px;">
                                                ... a dalších <?= count($all_req_hist) - 5 ?> starších záznamů (viz detail)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$is_total_cancel): ?>
                                    <div class="chat-flex-container">
                                        <input type="text" class="form-control inline-comment-text" data-id="<?= $row['id'] ?>" data-type="pozadavek" placeholder="Napsat poznámku k zadání...">
                                        <button class="btn btn-warning btn-urgent btn-inline-comment" data-id="<?= $row['id'] ?>" data-type="pozadavek" data-urgent="1" title="Odeslat jako URGENTNÍ">
                                            <i class="glyphicon glyphicon-flash"></i>
                                        </button>
                                        <button class="btn btn-default btn-send btn-inline-comment" data-id="<?= $row['id'] ?>" data-type="pozadavek" data-urgent="0" title="Odeslat">
                                            <i class="glyphicon glyphicon-send text-primary"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <div class="req-body">
                                <?php if ($is_urgent): ?>
                                    <?php foreach ($all_offers_for_this as $p) renderOfferRow($p, $is_adm, $is_orders, $is_vyvoj, $is_quality, $col['id'], $row['color_bg'], $history_off[$p[6]] ?? []); ?>
                                    <button class="btn btn-sm btn-block btn-warning btn-add-offer btn-search-offer" data-id="<?= $row['id'] ?>">
                                        <i class="glyphicon glyphicon-search"></i> DOHLEDAT DODAVATELE
                                    </button>
                                <?php elseif (empty($all_offers_for_this) && $col['id'] == 1): ?>
                                    <div class="req-empty-msg <?= $status_class ?>" style="background: rgba(255,255,255,0.7);">
                                        <?= $is_total_cancel ? 'Požadavek byl zrušen.' : 'Čeká se na vložení nabídky...' ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($all_offers_for_this as $p) renderOfferRow($p, $is_adm, $is_orders, $is_vyvoj, $is_quality, $col['id'], $row['color_bg'], $history_off[$p[6]] ?? []); ?>
                                    <?php if ($col['id'] == 3 && !$is_total_cancel): ?>
                                        <a href="technologie.php" class="btn btn-sm btn-block btn-primary btn-goto-lab">
                                            <i class="glyphicon glyphicon-flask"></i> PŘEJÍT DO LABORATOŘE
                                        </a>
                                    <?php endif; ?>
                                    <?php if (($is_orders || $is_adm) && in_array($col['id'], [1, 2]) && !$is_total_cancel): ?>
                                        <button class="btn btn-xs btn-link btn-add-offer btn-add-offer-link" data-id="<?= $row['id'] ?>"><i class="glyphicon glyphicon-plus-sign"></i> PŘIDAT DALŠÍ NABÍDKU</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include_once("boardModals.php"); ?>

<div id="mEditHistoryModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-pencil"></i> Upravit poznámku</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mEditHistoryId">
                <textarea id="mEditHistoryText" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary" id="mEditHistorySave">Uložit</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.manualRefreshHandling = true;
    var localLastChange = <?= $last_change_time ?? time() ?>;

    // ZMĚNA: Přidány měnové kurzy
    const CNB_EUR_RATE = 25.10;
    const CNB_USD_RATE = 23.50;

    var showRejected = false;
    var showOnlyMyTasks = false;
    var currentSearchFilter = '';

    var cur = { id: 0, st: 0, hasLab: false };

    function applyFilters() {
        var term = currentSearchFilter.toLowerCase().trim();
        var cleanTerm = term.replace('#', '');

        $('.req-card').each(function() {
            var card = $(this);
            var isRejected = card.hasClass('offer-rejected');

            var id = card.data('req-id') ? card.data('req-id').toString() : '';
            var name = card.data('req-name') ? card.data('req-name').toString() : '';

            var matchesSearch = true;
            if (term !== '') {
                matchesSearch = (id.indexOf(cleanTerm) > -1 || name.indexOf(term) > -1);
            }

            var matchesRejected = true;
            if (isRejected && !showRejected) {
                matchesRejected = false;
            }

            var matchesMyTasks = true;
            if (showOnlyMyTasks) {
                var cardNeedsAction = card.hasClass('needs-my-action');
                var hasOfferNeedingAction = card.find('.offer-row.needs-my-action').length > 0;
                if (!cardNeedsAction && !hasOfferNeedingAction) {
                    matchesMyTasks = false;
                }
            }

            if (matchesSearch && matchesRejected && matchesMyTasks) {
                card.show();

                if (showOnlyMyTasks) {
                    card.find('.offer-row').each(function() {
                        if ($(this).hasClass('needs-my-action')) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                } else {
                    card.find('.offer-row').show();
                }

            } else {
                card.hide();
            }
        });

        if(showRejected) {
            $('#btnToggleRejected').html('<i class="glyphicon glyphicon-eye-close"></i> Skrýt KO').addClass('btn-danger').removeClass('btn-default');
        } else {
            $('#btnToggleRejected').html('<i class="glyphicon glyphicon-eye-open"></i> Zamítnuté (KO)').removeClass('btn-danger').addClass('btn-default');
        }

        if(showOnlyMyTasks) {
            $('#btnToggleMyTasks').html('<i class="glyphicon glyphicon-filter"></i> Zobrazit VŠE').addClass('btn-primary').removeClass('btn-default');
        } else {
            $('#btnToggleMyTasks').html('<i class="glyphicon glyphicon-filter"></i> Jen k řešení').removeClass('btn-primary').addClass('btn-default');
        }

        $('.offer-comments-wrapper').each(function() {
            var el = $(this);
            if (this.scrollHeight > 66) el.addClass('can-expand'); else el.removeClass('can-expand');
        });
    }

    function safeReload() {
        if ($('.modal.in').length > 0) return;
        $('#board-container').load(window.location.href + ' #board-container > *', function() {
            applyFilters();
            if (typeof $.fn.select2 !== 'undefined') { $('.select2-dod').select2({ dropdownParent: $('#mNN'), tags: true }); }
        });
    }

    $(document).on('click', '#btnToggleRejected', function() {
        showRejected = !showRejected;
        applyFilters();
    });

    $(document).on('input', '#searchInput', function() {
        currentSearchFilter = $(this).val();
        applyFilters();
    });

    // Spuštění generátoru "Co sháníme"
    $(document).on('click', '#btnOpenExportModal', function() {
        // Otevře modál
        $('#mExportModal').modal('show');
        // Nastaví načítací text
        $('#mExportModalBody').html('<div class="text-center text-muted" style="padding: 40px;"><i class="glyphicon glyphicon-refresh spinning" style="font-size: 30px;"></i><br><br>Sestavuji seznam (může to chvilku trvat)...</div>');

        // Zavolá skript na serveru
        $.ajax({
            url: 'includes/ajax_export_wanted.php',
            type: 'GET',
            success: function(data) {
                // Přepíše vnitřek modálu vygenerovaným seznamem
                $('#mExportModalBody').html(data);
            },
            error: function(xhr, status, error) {
                // Pokud soubor neexistuje nebo spadne, napíše to chybu!
                $('#mExportModalBody').html('<div class="alert alert-danger" style="margin: 20px;"><strong>Chyba:</strong> Nepodařilo se spojit se skriptem ajax_export_wanted.php.<br>Detaily: ' + error + '</div>');
            }
        });
    });

    // Logika pro tlačítko "Kopírovat do schránky"
    $(document).on('click', '#btnCopyExport', function() {
        var textToCopy = $('#exportTextarea').val();
        if (!textToCopy) return;

        navigator.clipboard.writeText(textToCopy).then(function() {
            var btn = $('#btnCopyExport');
            var originalText = btn.html();
            btn.removeClass('btn-success').addClass('btn-info').html('<i class="glyphicon glyphicon-ok"></i> Zkopírováno!');
            setTimeout(function() { btn.removeClass('btn-info').addClass('btn-success').html(originalText); }, 2000);
        });
    });

    $(document).on('click', '.offer-comments-wrapper.can-expand', function() {
        var content = $(this).html();
        $('#mFullCommentsBody').html(content);
        $('#mFullCommentsBody').find('.offer-comments-wrapper').css({'max-height': 'none', 'overflow': 'visible'});
        $('#mFullComments').modal('show');
    });

    $(document).on('click', '.btn-ping-purchasing', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var btn = $(this);
        $('#pingReqId').val(btn.data('id'));
        $('#pingSurRaw').val(btn.data('sur'));
        $('#pingSurName').text(btn.data('sur'));
        $('#mPingPurchasing').modal('show');
    });

    $('#btnConfirmPing').on('click', function() {
        var reqId = $('#pingReqId').val();
        var sur = $('#pingSurRaw').val();
        var modalBtn = $(this);

        var originalBtn = $('.btn-ping-purchasing[data-id="'+reqId+'"]');
        modalBtn.prop('disabled', true).text('Odesílám...');
        originalBtn.removeClass('glyphicon-bell text-info').addClass('glyphicon-refresh spinning text-muted');

        $.post('includes/ajax_ping_purchasing.php', { id: reqId, surovina: sur }, function(r) {
            if (r.trim() === "OK") {
                $('#mPingPurchasing').modal('hide');
                modalBtn.prop('disabled', false).text('Ano, odeslat');
                originalBtn.removeClass('glyphicon-refresh spinning text-muted').addClass('glyphicon-ok text-success');
                setTimeout(function() { safeReload(); }, 1500);
            } else {
                alert(r);
                modalBtn.prop('disabled', false).text('Ano, odeslat');
                originalBtn.removeClass('glyphicon-refresh spinning text-muted').addClass('glyphicon-bell text-info');
            }
        });
    });

    $(document).on('click', '.btn-urge-task', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var btn = $(this);
        $('#mUrgeReqId').val(btn.data('id'));
        $('#mUrgeSurRaw').val(btn.data('sur'));
        $('#mUrgeTaskModal').modal('show');
    });

    $('#btnConfirmUrge').on('click', function() {
        var reqId = $('#mUrgeReqId').val();
        var sur = $('#mUrgeSurRaw').val();
        var modalBtn = $(this);

        var originalBtn = $('.btn-urge-task[data-id="'+reqId+'"]');
        modalBtn.prop('disabled', true).text('Odesílám...');
        originalBtn.removeClass('glyphicon-flash text-warning').addClass('glyphicon-refresh spinning text-muted');

        $.post('includes/ajax_urge_task.php', { id: reqId, surovina: sur }, function(r) {
            if (r.trim() === "OK") {
                $('#mUrgeTaskModal').modal('hide');
                modalBtn.prop('disabled', false).text('Ano, urgovat');
                originalBtn.removeClass('glyphicon-refresh spinning text-muted').addClass('glyphicon-ok text-success');
                setTimeout(function() { safeReload(); }, 2000);
            } else {
                alert(r);
                modalBtn.prop('disabled', false).text('Ano, urgovat');
                originalBtn.removeClass('glyphicon-refresh spinning text-muted').addClass('glyphicon-flash text-warning');
            }
        });
    });

    $(document).on('click', '.btn-toggle-priority', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        var icon = $(this);
        var reqId = icon.data('id');
        var currentPrio = icon.data('prio');
        var newPrio = (currentPrio == 1) ? 0 : 1;

        icon.removeClass('glyphicon-exclamation-sign glyphicon-unchecked text-danger text-muted')
            .addClass('glyphicon-refresh spinning');

        $.post('includes/ajax_update_priority.php', { id: reqId, priorita: newPrio }, function(r) {
            if (r.trim() === "OK") {
                safeReload();
            } else {
                alert("Chyba při změně priority: " + r);
                safeReload();
            }
        });
    });

    setInterval(function() {
        if ($('.modal.in').length === 0) {
            $.get('includes/check_changes.php', function(s) { if (s > localLastChange) { localLastChange = s; safeReload(); } });
        }
    }, 2000);

    $(document).ready(function() {
        if (typeof $.fn.select2 !== 'undefined') {
            $('.select2-dod').select2({ dropdownParent: $('#mNN'), tags: true, placeholder: "Napište název nového nebo vyberte...", allowClear: true });
        }

        $('#mNNCena, #mNNMena').on('input change', function() {
            var cena = parseFloat($('#mNNCena').val()) || 0;
            var mena = $('#mNNMena').val();
            if (cena > 0) $('#mNNDopravaWrapper').slideDown(200); else $('#mNNDopravaWrapper').slideUp(200);

            // ZMĚNA: Aktualizace pro zobrazení kurzu i pro USD
            if ((mena === 'EUR' || mena === 'USD') && cena > 0) {
                var kurz = (mena === 'EUR') ? CNB_EUR_RATE : CNB_USD_RATE;
                var czk = cena * kurz;
                $('#mNNCzkCalc').text(czk.toFixed(2));
                $('#mNNKurzInfo').slideDown(200);
            } else {
                $('#mNNKurzInfo').slideUp(200);
            }
        });

        $('#mNNDoprava').on('change', function() {
            if ($(this).val() === 'custom') $('#mNNDopravaCustomWrapper').slideDown(200);
            else $('#mNNDopravaCustomWrapper').slideUp(200);
        });

        $('#mNN').on('show.bs.modal', function (e) {
            $('#mNNDoprava, #mNNDopravaCustom').val('');
            $('#mNNDopravaWrapper, #mNNDopravaCustomWrapper, #mNNKurzInfo').hide();
            if ($(this).attr('data-mode') === 'add') $('#mNNMena').val('CZK');
        });

        $(document).on('input', '#mNNMoqQty', function() {
            if ($(this).val() !== "") $('#mNNMoqMjWrapper').fadeIn(200); else $('#mNNMoqMjWrapper').fadeOut(200);
        });

        $(document).on('click', '.btn-inline-comment', function() {
            var id = $(this).data('id');
            var type = $(this).data('type');
            var urgent = $(this).data('urgent') || 0;
            var text = $('.inline-comment-text[data-id="'+id+'"][data-type="'+type+'"]').val().trim();
            if (!text) return;
            var btn = $(this);
            btn.prop('disabled', true);
            $.post('includes/ajax_add_comment.php', { id_entity: id, typ_entity: type, text: text, is_urgent: urgent }, function(r) {
                if (r.trim() === "OK") { safeReload(); } else { alert(r); btn.prop('disabled', false); }
            });
        });

        $(document).on('keypress', '.inline-comment-text', function(e) {
            if(e.which == 13) {
                $('.btn-inline-comment[data-id="'+$(this).data('id')+'"][data-type="'+$(this).data('type')+'"][data-urgent="0"]').click();
            }
        });

        // ----------------------------------------------------
        // LOGIKA PRO MAZÁNÍ A EDITACI HISTORIE
        // ----------------------------------------------------
        $(document).on('click', '.btn-delete-history', function() {
            $('#mDeleteHistoryId').val($(this).data('id'));
            $('#mDeleteHistoryModal').modal('show');
        });

        $('#mDeleteHistorySave').on('click', function() {
            var btn = $(this);
            var id = $('#mDeleteHistoryId').val();

            btn.prop('disabled', true).text('Mažu...');

            $.post('includes/ajax_delete_comment.php', { id: id }, function(r) {
                if (r.trim() === "OK") {
                    $('#mDeleteHistoryModal').modal('hide');
                    btn.prop('disabled', false).text('Ano, smazat');
                    safeReload();
                } else {
                    alert(r);
                    btn.prop('disabled', false).text('Ano, smazat');
                }
            });
        });

        // ZMĚNA: JS pro otevření a uložení editace poznámky
        $(document).on('click', '.btn-edit-history', function() {
            $('#mEditHistoryId').val($(this).data('id'));
            // Dekódování HTML entit zpět na text do textarea
            var txt = $('<textarea />').html($(this).data('text')).text();
            $('#mEditHistoryText').val(txt);
            $('#mEditHistoryModal').modal('show');
        });

        $('#mEditHistorySave').on('click', function() {
            var id = $('#mEditHistoryId').val();
            var text = $('#mEditHistoryText').val().trim();
            if (!text) return alert("Text nesmí být prázdný.");

            var btn = $(this);
            btn.prop('disabled', true).text('Ukládám...');

            $.post('includes/ajax_edit_comment.php', { id: id, text: text }, function(r) {
                if (r.trim() === "OK") {
                    $('#mEditHistoryModal').modal('hide');
                    btn.prop('disabled', false).text('Uložit');
                    safeReload();
                } else {
                    alert(r);
                    btn.prop('disabled', false).text('Uložit');
                }
            });
        });


        $(document).on('click', '.btn-edit-offer', function() {
            var b = $(this);
            $('#mNN').modal('show');
            $('#mNN .modal-title').html('<i class="glyphicon glyphicon-pencil"></i> Upravit nabídku');
            $('#mNNSave').text('ULOŽIT ZMĚNY').addClass('btn-warning').removeClass('btn-primary');

            $('#mNNId').val(b.data('id'));
            $('#mNNDod').val(b.data('dodavatel')).trigger('change');
            $('#mNNCena').val(b.data('cena'));
            $('#mNNMena').val(b.data('mena') || 'CZK').trigger('change');
            $('#mNNMoqQty').val(b.data('moq-qty'));
            $('#mNNMoqMj').val(b.data('moq-mj'));
            $('#mNNPozn').val('');
            $('#mNN').attr('data-mode', 'edit');

            if (b.data('moq-qty') !== "") $('#mNNMoqMjWrapper').show(); else $('#mNNMoqMjWrapper').hide();
        });

        $(document).on('click', '.btn-add-offer', function() {
            $('#mNN').attr('data-mode', 'add');
            $('#mNN .modal-title').html('<i class="glyphicon glyphicon-plus"></i> Nová nabídka');
            $('#mNNSave').text('Uložit nabídku').addClass('btn-primary').removeClass('btn-warning');
            $('#mNNId').val($(this).data('id'));
            $('#mNNCena, #mNNMoqQty, #mNNPozn').val('');
            $('#mNNDod').val('').trigger('change');
            $('#mNNMena').val('CZK').trigger('change');
            $('#mNNMoqMjWrapper').hide();
            $('#mNN').modal('show');
        });

        $('#mNNSave').on('click', function() {
            var mode = $('#mNN').attr('data-mode');
            var targetScript = (mode === 'edit') ? 'includes/ajax_update_offer.php' : 'includes/ajax_add_offer.php';
            var d = $('#mNNDod').val(), c = $('#mNNCena').val(), id = $('#mNNId').val();

            if(!d || !c) { alert("Vyplňte dodavatele a cenu."); return; }

            var finalNote = $('#mNNPozn').val().trim();
            var dopravaVal = $('#mNNDoprava').val();

            if (dopravaVal) {
                var dCena = (dopravaVal === 'custom') ? $('#mNNDopravaCustom').val() : dopravaVal;
                if (dCena) { finalNote = "[Dopravné: " + dCena + " Kč/MJ]\n" + finalNote; }
            }

            $.post(targetScript, {
                id_nabidka: (mode === 'edit' ? id : 0),
                id_pozadavek: (mode === 'add' ? id : 0),
                dodavatel_raw: d,
                cena: c,
                mena: $('#mNNMena').val() || 'CZK',
                moq_qty: $('#mNNMoqQty').val(),
                moq_mj: $('#mNNMoqMj').val(),
                poznamka_nakup: finalNote
            }, function(r) { if(r.trim() == "OK") { $('#mNN').modal('hide'); safeReload(); } else { alert(r); } });
        });

        $(document).on('click', '.btn-edit-req', function() {
            var b = $(this);
            $('#mEditReqId').val(b.data('id'));
            $('#mEditReqTitle').text(b.data('sur'));
            $('#mEditReqBio').prop('checked', b.data('bio') == 1);
            $('#mEditReqVegan').prop('checked', b.data('vegan') == 1);
            $('#mEditReqBezlepek').prop('checked', b.data('bezlepek') == 1);
            $('#mEditReqKosher').prop('checked', b.data('kosher') == 1);
            $('#mEditReqHalal').prop('checked', b.data('halal') == 1);
            $('#mEditReqPrio').val(b.data('prio'));
            $('#mEditReqNote').val(b.data('note') === null || b.data('note') === 'null' ? '' : b.data('note'));

            // ZMĚNA: Načtení zákazníka do editačního okna
            $('#mEditReqZakaznik').val(b.data('zakaznik') === null || b.data('zakaznik') === 'null' ? '' : b.data('zakaznik'));

            $('#mEditReq').modal('show');
        });

        $('#mEditReqSave').on('click', function() {
            var btn = $(this); btn.prop('disabled', true).text('Ukládám...');
            $.post('includes/ajax_update_request.php', {
                id: $('#mEditReqId').val(),
                bio: $('#mEditReqBio').is(':checked') ? 1 : 0,
                vegan: $('#mEditReqVegan').is(':checked') ? 1 : 0,
                bezlepek: $('#mEditReqBezlepek').is(':checked') ? 1 : 0,
                kosher: $('#mEditReqKosher').is(':checked') ? 1 : 0,
                halal: $('#mEditReqHalal').is(':checked') ? 1 : 0,
                priorita: $('#mEditReqPrio').val(),
                poznamka: $('#mEditReqNote').val(),
                // ZMĚNA: Uložení zákazníka
                zakaznik: $('#mEditReqZakaznik').val()
            }, function(r) {
                if(r.trim() == "OK") { $('#mEditReq').modal('hide'); safeReload(); } else { alert(r); }
                btn.prop('disabled', false).text('Uložit změny');
            });
        });

        $('#btnOpenCancelReq').click(function() {
            var reqId = $('#mEditReqId').val();
            $('#mCancelReqId').val(reqId);
            $('#mCancelReqReason').val('');
            $('#mEditReq').modal('hide');
            setTimeout(function() { $('#mCancelReqModal').modal('show'); }, 400);
        });

        $('#mCancelReqSave').click(function() {
            var id = $('#mCancelReqId').val();
            var reason = $('#mCancelReqReason').val().trim();
            if(!reason) { alert("Vyplňte prosím důvod zrušení."); return; }
            $.post('includes/ajax_cancel_request.php', { id: id, poznamka: reason }, function(r) {
                if(r.trim() === "OK") { $('#mCancelReqModal').modal('hide'); safeReload(); } else { alert(r); }
            });
        });

        $(document).on('click', '.btn-prompt-qty-note', function() {
            var b = $(this); $('#mQNId').val(b.data('id')); $('#mQNStatus').val(b.data('status')); $('#mQNQty, #mQNNote').val(''); $('#mQtyNote').modal('show');
        });

        $('#mQNSave').on('click', function() {
            var qty = $('#mQNQty').val().trim(); if(!qty) { alert("Zadejte požadované množství."); return; }
            var btn = $(this); btn.prop('disabled', true).text('Ukládám...');
            $.post('includes/update_status_nabidka.php', { id: $('#mQNId').val(), status: $('#mQNStatus').val(), qty: qty, poznamka: $('#mQNNote').val() }, function() { $('#mQtyNote').modal('hide'); btn.prop('disabled', false).text('Potvrdit schválení'); safeReload(); });
        });

        $(document).on('click', '.btn-wf-direct, .btn-wf-check', function(e) {
            e.preventDefault();
            var bid = $(this).data('id'), bst = $(this).data('status'), btn = $(this);
            var nazevAkce = btn.text().trim();
            btn.prop('disabled', true).html('<i class="glyphicon glyphicon-refresh spinning"></i>');
            $.post('includes/update_status_nabidka.php', { id: bid, status: bst, poznamka: 'Systémová akce: ' + nazevAkce }, function() { safeReload(); });
        });

        $(document).on('click', '.btn-prompt-reason', function() {
            var b = $(this); $('#mReasonId').val(b.data('id')); $('#mReasonStatus').val(b.data('status')); $('#mReasonText').val('');
            if(b.data('status') == 9) $('#mReason .modal-header').css('background', '#f0ad4e'); else $('#mReason .modal-header').css('background', '#d9534f');
            $('#mReason').modal('show');
        });

        $('#mReasonSave').on('click', function() {
            var txt = $('#mReasonText').val().trim(); if(!txt) { alert("Zadejte prosím důvod."); return; }
            var btn = $(this); btn.prop('disabled', true).text('Ukládám...');
            $.post('includes/update_status_nabidka.php', { id: $('#mReasonId').val(), status: $('#mReasonStatus').val(), poznamka: txt }, function() { $('#mReason').modal('hide'); btn.prop('disabled', false).text('Potvrdit akci'); safeReload(); });
        });

        $(document).on('click', '.btn-toggle-upload', function(e) {
            e.preventDefault();
            $('#upload' + $(this).data('target')).slideToggle();
            $('#btnDoUpload').slideDown();
        });

        $(document).on('change', '#mWFFileLab', function() { if (this.files.length > 0) $('#mWFSarzeBox').slideDown(); else if (!cur.hasLab) $('#mWFSarzeBox').slideUp(); });

        $(document).on('click', '.btn-wf', function() {
            var b = $(this); cur.id = b.data('id'); cur.st = b.data('status');
            $('#mWFNote').val('');
            $('#mWFSarze').val(b.data('sarze') || ''); $('#mWFQty, #mWFFileSpec, #mWFFileLab, #mWFFileOther').val(''); $('#mWFFileStatus').html(''); $('#btnDoUpload, #uploadTDS, #uploadCOA, #uploadOther').hide(); $('#mWFQtyBox').toggle(b.data('need-qty') == 1); $('#mWFItemCodeBox').toggle(b.data('final') == 1); var fStr = b.data('files'); cur.hasLab = (fStr && fStr.toString().indexOf('~lab') !== -1);
            var h = { TDS: '', COA: '', Other: '' }; if (fStr && fStr.toString().length > 0) { fStr.toString().split('^').forEach(function(f) { if(!f) return; var p = f.split('~'); var html = '<div style="display:flex; justify-content:space-between; margin-bottom:2px; font-size:11px; background:#f9f9f9; padding:2px 5px; border-radius:3px;"><span><i class="glyphicon glyphicon-file"></i> ' + p[0] + '</span><i class="glyphicon glyphicon-remove text-danger btn-delete-file" style="cursor:pointer;" data-id="'+cur.id+'" data-file="'+f+'"></i></div>'; if (p[1] === 'spec') h.TDS += html; else if (p[1] === 'lab') h.COA += html; else h.Other += html; }); }
            $('#listTDS').html(h.TDS || '<em class="text-muted small">Žádný</em>'); $('#listCOA').html(h.COA || '<em class="text-muted small">Žádný</em>'); $('#listOther').html(h.Other || '<em class="text-muted small">Žádný</em>'); if (cur.hasLab || b.data('sarze')) $('#mWFSarzeBox').show(); else $('#mWFSarzeBox').hide(); $('#mWF').modal('show');
        });

        function executeUpload(callback) {
            var fd = new FormData();
            fd.append('id_nabidka', cur.id);
            var hasFiles = false;

            var iS = $('#mWFFileSpec')[0];
            for(var i=0; i<iS.files.length; i++) { fd.append('files_spec[]', iS.files[i]); hasFiles=true; }
            var iL = $('#mWFFileLab')[0];
            for(var i=0; i<iL.files.length; i++) { fd.append('files_lab[]', iL.files[i]); hasFiles=true; }
            var iO = $('#mWFFileOther')[0];
            for(var i=0; i<iO.files.length; i++) { fd.append('files_other[]', iO.files[i]); hasFiles=true; }

            if(!hasFiles) { if(callback) callback(); return; }

            $('#mWFFileStatus').html('<span class="text-info"><i class="glyphicon glyphicon-refresh spinning"></i> Nahrávám...</span>');

            $.ajax({
                url: 'includes/upload_to_nextcloud.php',
                type: 'POST',
                data: fd,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.trim() === "OK") {
                        $('#mWFFileStatus').html('<b class="text-success">Nahráno.</b>');

                        $.post('includes/ajax_get_offer_files.php', { id_nabidka: cur.id }, function(data) {
                            try {
                                var json = JSON.parse(data);
                                $('#listTDS').html(json.TDS || '<em class="text-muted small">Žádný</em>');
                                $('#listCOA').html(json.COA || '<em class="text-muted small">Žádný</em>');
                                $('#listOther').html(json.Other || '<em class="text-muted small">Žádný</em>');

                                if (json.hasLab) {
                                    cur.hasLab = true;
                                    $('#mWFSarzeBox').slideDown();
                                }
                            } catch(e) { console.error("Chyba při parsování souborů", e); }
                        });

                        $('#mWFFileSpec, #mWFFileLab, #mWFFileOther').val('');
                        $('#btnDoUpload, #uploadTDS, #uploadCOA, #uploadOther').hide();
                        setTimeout(function() { $('#mWFFileStatus').empty(); }, 3000);

                        if (typeof safeReload === "function") safeReload();
                        if(callback) callback();
                    } else {
                        $('#mWFFileStatus').html('<b class="text-danger">' + response + '</b>');
                    }
                }
            });
        }

        $('#btnDoUpload').on('click', function() { executeUpload(); });

        $('#mWFSave').on('click', function() {
            var btn = $(this); var sarzeVal = $('#mWFSarze').val();
            btn.prop('disabled', true).text('Ukládám...');
            executeUpload(function() {
                $.post('includes/update_status_nabidka.php', { id: cur.id, status: cur.st, poznamka: $('#mWFNote').val(), qty: $('#mWFQty').val(), sarze: sarzeVal }, function() {
                    $('#mWF').modal('hide'); btn.prop('disabled', false).text('Potvrdit'); safeReload();
                });
            });
        });

        $(document).on('click', '.btn-delete-file', function() { if(!confirm("Smazat soubor?")) return; $.post('includes/delete_file.php', { id: $(this).data('id'), file: $(this).data('file') }, function() { $('#mWF').modal('hide'); safeReload(); }); });

        applyFilters();
    });

    $(document).on('click', '.btn-open-detail', function(e) {
        if ($(e.target).closest('.btn-edit-req, .btn-delete-req, .btn-ping-purchasing, .btn-urge-task, .btn-toggle-priority').length > 0) {
            return;
        }

        var reqId = $(this).data('id');
        $('#mReqDetailContent').html('<div style="padding:50px; text-align:center; color:#777;"><i class="glyphicon glyphicon-refresh spinning" style="font-size: 30px;"></i><br><br>Načítám detail požadavku...</div>');
        $('#mReqDetail').modal('show');

        $.post('includes/ajax_request_detail.php', { id: reqId }, function(data) {
            $('#mReqDetailContent').html(data);
        }).fail(function() {
            $('#mReqDetailContent').html('<div class="alert alert-danger" style="margin:20px;">Chyba při načítání dat.</div>');
        });
    });

    $(document).ready(function() {
        var urlParams = new URLSearchParams(window.location.search);
        var autoOpenId = urlParams.get('req_id');

        if (autoOpenId) {
            setTimeout(function() {
                var modalEl = $('#mReqDetail');
                if (modalEl.length === 0) return;
                $('#mReqDetailContent').html('<div style="padding:50px; text-align:center; color:#777;"><i class="glyphicon glyphicon-refresh spinning" style="font-size: 30px;"></i><br><br>Načítám detail požadavku...</div>');
                modalEl.modal('show');

                $.post('includes/ajax_request_detail.php', { id: autoOpenId }, function(data) {
                    $('#mReqDetailContent').html(data);
                }).fail(function() {
                    $('#mReqDetailContent').html('<div class="alert alert-danger" style="margin:20px;">Chyba při načítání dat přes AJAX.</div>');
                });
                window.history.replaceState(null, null, 'index.php?Pozadavek=1');
            }, 500);
        }});

    $(document).on('click', '.btn-delete-req', function(e) {
        e.stopPropagation();
        var reqId = $(this).data('id');
        $('#mCancelReqId').val(reqId);
        $('#mCancelReqReason').val('');
        $('#mCancelReqModal').modal('show');
    });

    $(document).on('click', '.btn-quality-approve', function() {
        var b = $(this);
        $('#mQualId').val(b.data('id'));
        $('#mQualStatus').val(b.data('status'));
        $('#mQualSarze').val(b.data('sarze') || '');
        $('#mQualityModal').modal('show');
    });

    $('#mQualSave').on('click', function() {
        var sarze = $('#mQualSarze').val().trim();
        if(!sarze) {
            alert("Vyplňte prosím číslo šarže z COA dokumentu.");
            $('#mQualSarze').focus();
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('Ukládám...');

        $.post('includes/update_status_nabidka.php', {
            id: $('#mQualId').val(),
            status: $('#mQualStatus').val(),
            sarze: sarze,
            poznamka: 'Systémová akce: KVALITA OK (Šarže: ' + sarze + ')'
        }, function() {
            $('#mQualityModal').modal('hide');
            btn.prop('disabled', false).text('Potvrdit schválení');
            safeReload();
        });
    });
</script>