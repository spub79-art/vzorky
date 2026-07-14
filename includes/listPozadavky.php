<?php
include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("boardOfferRow.php");

@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

$has_pozadavky_produkty = false;
$pp_check = @mysqli_query($conn, "SHOW TABLES LIKE 'pozadavky_produkty'");
if ($pp_check && mysqli_num_rows($pp_check) > 0) {
    $has_pozadavky_produkty = true;
}
$produkty_sql_extra = $has_pozadavky_produkty
    ? "(SELECT GROUP_CONCAT(CONCAT(COALESCE(zp.nazev, '—'), ' → ', pr.nazev) ORDER BY zp.nazev, pr.nazev SEPARATOR ' · ')
         FROM pozadavky_produkty pp
         JOIN produkty pr ON pp.id_produkt = pr.id AND pr.ukonceny = 0
         LEFT JOIN zakaznici zp ON pr.id_zakaznik = zp.id
         WHERE pp.id_pozadavek = p.id) AS produkty_seznam,
       (SELECT GROUP_CONCAT(pp.id_produkt ORDER BY pp.id_produkt SEPARATOR ',')
         FROM pozadavky_produkty pp
         JOIN produkty pr ON pp.id_produkt = pr.id AND pr.ukonceny = 0
         WHERE pp.id_pozadavek = p.id) AS produkty_ids,
       (SELECT MAX(pr.priorita)
         FROM pozadavky_produkty pp
         JOIN produkty pr ON pp.id_produkt = pr.id AND pr.ukonceny = 0
         WHERE pp.id_pozadavek = p.id) AS produkt_urgent,"
    : "NULL AS produkty_seznam, NULL AS produkty_ids, NULL AS produkt_urgent,";

if (!isset($can_nakup)) {
    include_once("permissions.php");
    $perms = loadSessionPermissions();
    $can_nakup = $perms['can_nakup'];
    $can_claim_nakup = $perms['can_claim_nakup'];
    $current_uid = $perms['current_uid'];
}
$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);
$is_orders = (!empty($_SESSION['orders']) && $_SESSION['orders'] == 1);
$is_vyvoj = (!empty($_SESSION['vyvoj']) && $_SESSION['vyvoj'] == 1);
$is_quality = (!empty($_SESSION['kvalita']) && $_SESSION['kvalita'] == 1);
$is_cumil = (!empty($_SESSION['cumil']) && $_SESSION['cumil'] == 1);
if (!isset($current_uid)) {
    $current_uid = (int)($_SESSION['uid'] ?? 0);
}
$users_tbl = defined('DB_TBL_USERS') ? DB_TBL_USERS : 'users';

$last_change_time = file_exists('last_change.txt') ? file_get_contents('last_change.txt') : time();

// ==============================================================================
// 1. ČISTÝ DOTAZ NA POŽADAVKY
// ==============================================================================
$sql_req = "SELECT p.*, s.nazev AS surovina_nazev, u_nak.jmeno AS nakupci_jmeno,
        $produkty_sql_extra
        (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ') 
         FROM pozadavky_zakaznici pz 
         JOIN zakaznici z ON pz.id_zakaznik = z.id 
         WHERE pz.id_pozadavek = p.id) as zakaznici_seznam,
         (SELECT GROUP_CONCAT(pz.id_zakaznik SEPARATOR ',') 
         FROM pozadavky_zakaznici pz 
         WHERE pz.id_pozadavek = p.id) as zakaznici_ids
        FROM pozadavky p 
        LEFT JOIN suroviny s ON p.id_surovina = s.id 
        LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
        WHERE p.id_status != 6 ORDER BY p.datumPozadavek DESC";

$res_req = mysqli_query($conn, $sql_req);
$pozadavky = [];
$req_ids = [];

if ($res_req) {
    while ($row = mysqli_fetch_assoc($res_req)) {
        $row['nabidky_pole'] = [];
        $pozadavky[$row['id']] = $row;
        $req_ids[] = $row['id'];
    }
}

// ==============================================================================
// 2. ČISTÝ DOTAZ NA NABÍDKY (Asociativní pole!)
// ==============================================================================
if (!empty($req_ids)) {
    $ids_str = implode(',', $req_ids);
    $sql_off = "SELECT pn.*, d.nazev AS dodavatel_nazev, cs.barva_hex 
                FROM pozadavky_nabidky pn
                LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
                LEFT JOIN ciselnik_statusu cs ON pn.id_status = cs.id
                WHERE pn.id_pozadavek IN ($ids_str)";

    $res_off = mysqli_query($conn, $sql_off);
    if ($res_off) {
        while ($off = mysqli_fetch_assoc($res_off)) {
            $pozadavky[$off['id_pozadavek']]['nabidky_pole'][] = $off;
        }
    }
}

$f1 = $f2 = $f3 = [];

$history_req = [];
$history_off = [];
$res_hist = mysqli_query($conn, "SELECT * FROM historie_pozadavku ORDER BY vytvoreno DESC");
if ($res_hist) {
    while ($h = mysqli_fetch_assoc($res_hist)) {
        // Pokud je záznam skrytý a uživatel NENÍ admin, rovnou ho přeskočíme
        if (isset($h['skryto']) && $h['skryto'] == 1 && !$is_adm) {
            continue;
        }

        if ($h['id_nabidka'] == 0) {
            $history_req[$h['id_pozadavek']][] = $h;
        } else {
            $history_off[$h['id_nabidka']][] = $h;
        }
    }
}

foreach ($pozadavky as $row) {
    $p1_active = $p2_active = $p3_active = $has_any_active = false;
    $p1_visible = $p2_visible = $p3_visible = false;

    // Odloženo nebo zrušeno
    $is_req_cancelled = in_array((int)$row['id_status'], [5, 7, 8]);

    if (!empty($row['nabidky_pole'])) {
        foreach ($row['nabidky_pole'] as $pts) {
            if (empty($pts['id'])) continue;

            $s_id = (int)$pts['id_status'];
            if (!in_array($s_id, [5, 7, 8])) {
                $has_any_active = true;

                if (in_array($s_id, [10, 4])) {
                    $p3_active = true; $p3_visible = true;
                } elseif (in_array($s_id, nabidkaFaze2Statusy())) {
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
?>

<div class="row" style="margin-bottom: 15px; padding: 0 15px;">
    <?php if ($can_nakup): ?>
        <button id="btnOpenExportModal" class="btn btn-primary btn-sm pull-right" style="font-weight: bold;">
            <i class="glyphicon glyphicon-list-alt"></i> Generátor "Co sháníme"
        </button>
    <?php endif; ?>
</div>

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
                            if (!in_array((int)$item['id_status'], [5, 7, 8])) $active_count++;
                        }
                        echo $active_count;
                        ?>
                    </span>
                </div>
                <div class="panel-body board-panel-body">
                    <?php foreach ($col['data'] as $row):
                        $is_total_cancel = in_array((int)$row['id_status'], [5, 7, 8]);
                        $is_postponed = ((int)$row['id_status'] == 8);

                        $all_offers_for_this = [];

                        if (!empty($row['nabidky_pole'])) {
                            foreach($row['nabidky_pole'] as $pts) {
                                if (!empty($pts['id'])) {
                                    $s_id = (int)$pts['id_status'];
                                    $is_ko = in_array($s_id, [5, 7, 8]);

                                    $phase_of_offer = 1;
                                    if (!$is_ko) {
                                        if (in_array($s_id, [10, 4])) $phase_of_offer = 3;
                                        elseif (in_array($s_id, nabidkaFaze2Statusy())) $phase_of_offer = 2;
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

                        $is_urgent = ($row['buyer_must_act'] && $can_nakup && $col['id'] == 1 && !$is_total_cancel);

                        $id_nakupci = (int)($row['id_nakupci'] ?? 0);
                        $nakupci_jmeno = trim($row['nakupci_jmeno'] ?? '');
                        $is_my_nakup_req = ($id_nakupci > 0 && $id_nakupci === $current_uid);

                        $is_grey = ($row['color_bg'] == '#e2e6ea');
                        if (!$is_grey) {
                            list($r, $g, $b) = sscanf($row['color_bg'], "#%02x%02x%02x");
                            $card_bg = $is_total_cancel ? '#f9f9f9' : "rgba($r, $g, $b, 0.08)";
                        } else {
                            $card_bg = $is_total_cancel ? '#f9f9f9' : '#fafafa';
                        }

                        $border_top_color = $is_total_cancel ? '#ccc' : ($is_grey ? '#d1d5da' : $row['color_bg']);

                        $all_ko = (!empty($all_offers_for_this));
                        foreach ($all_offers_for_this as $pt) { if (!in_array((int)$pt['id_status'], [5, 7, 8])) $all_ko = false; }
                        $card_classes = "req-card " . ($is_urgent ? 'req-card-urgent needs-my-action ' : '');
                        if ($is_total_cancel || ($col['id'] == 1 && $all_ko && !$is_urgent && empty($all_offers_for_this) == false)) {
                            $card_classes .= ' offer-rejected';
                        }

                        $status_class = $is_postponed ? 'is-postponed' : ($is_total_cancel ? 'is-cancelled' : 'is-active');
                        $badge_text_color = getContrastColor($row['color_bg']);
                        ?>

                        <div class="<?= $card_classes ?>" data-req-id="<?= $row['id'] ?>" data-req-name="<?= htmlspecialchars(strtolower($row['surovina_nazev'])) ?>" data-urgent="<?= $row['priorita'] ?>" data-nakupci-id="<?= $id_nakupci ?>" style="background-color: <?= $card_bg ?>; border-top-color: <?= $border_top_color ?>;">

                            <div class="req-header">
                                <div class="req-title-row btn-open-detail hover-text-primary" data-id="<?= $row['id'] ?>">

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

                                        <?php if ($can_nakup && !$is_total_cancel): ?>
                                            <?php
                                            $snooze_active = !empty($row['souhrn_snooze_do']) && strtotime($row['souhrn_snooze_do']) > time();
                                            ?>
                                            <i class="glyphicon glyphicon-time <?= $snooze_active ? 'text-primary' : 'text-muted' ?> btn-digest-snooze no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer; font-size: 14px; margin-right: 8px;"
                                               data-id="<?= $row['id'] ?>"
                                               data-snooze-until="<?= htmlspecialchars($row['souhrn_snooze_do'] ?? '', ENT_QUOTES) ?>"
                                               data-snooze-note="<?= htmlspecialchars($row['souhrn_snooze_poznamka'] ?? '', ENT_QUOTES) ?>"
                                               title="<?= $snooze_active ? 'Souhrn: dlouhé dodání do ' . date('j.n.', strtotime($row['souhrn_snooze_do'])) : 'Souhrn: snížit prioritu (dlouhé dodání)' ?>"></i>
                                        <?php endif; ?>

                                        <?php if (!$is_orders && !$is_total_cancel): ?>
                                            <i class="glyphicon glyphicon-bell text-info btn-ping-purchasing no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer; font-size: 14px; margin-right: 8px;"
                                               data-id="<?= $row['id'] ?>"
                                               data-sur="<?= htmlspecialchars($row['surovina_nazev']) ?>"
                                               title="Vyžádat dohledání další nabídky od Nákupu"></i>
                                        <?php endif; ?>

                                        <?php if ($can_claim_nakup && !$is_total_cancel && !$is_my_nakup_req): ?>
                                                <i class="glyphicon glyphicon-hand-up <?= ($id_nakupci > 0) ? 'text-warning' : 'text-primary' ?> btn-claim-request no-detail-trigger"
                                                   style="pointer-events: auto; cursor: pointer; font-size: 14px; margin-right: 8px;"
                                                   data-id="<?= $row['id'] ?>"
                                                   data-resitel-id="<?= $id_nakupci ?>"
                                                   data-resitel-jmeno="<?= htmlspecialchars($nakupci_jmeno, ENT_QUOTES) ?>"
                                                   title="<?= $id_nakupci > 0 ? 'Převzít (nyní: ' . htmlspecialchars($nakupci_jmeno, ENT_QUOTES) . ')' : 'Převzít požadavek' ?>"></i>
                                        <?php endif; ?>
                                        <?php if (!$is_total_cancel && ($is_my_nakup_req || ($is_adm && $id_nakupci > 0))): ?>
                                                <i class="glyphicon glyphicon-log-out text-muted btn-release-request no-detail-trigger"
                                                   style="pointer-events: auto; cursor: pointer; font-size: 14px; margin-right: 8px;"
                                                   data-id="<?= $row['id'] ?>"
                                                   title="Vzdávám to (uvolnit požadavek)"></i>
                                        <?php endif; ?>

                                        <?php if (($is_vyvoj || $is_adm) && in_array((int)$row['id_status'], [5, 7, 8])): ?>
                                            <i class="glyphicon glyphicon-play text-success btn-revive-req no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer; margin-right: 8px;"
                                               data-id="<?= $row['id'] ?>"
                                               title="Oživit požadavek (Vrátit mezi aktivní k řešení)"></i>
                                        <?php endif; ?>

                                        <?php if (($is_vyvoj || $is_adm) && !$is_total_cancel): ?>
                                            <i class="glyphicon glyphicon-pause text-muted btn-postpone-req no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer; margin-right: 8px;"
                                               data-id="<?= $row['id'] ?>"
                                               title="Odložit k ledu (Schovat z aktivních, zůstane v historii)"></i>
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
                                               data-mnozstvi="<?= htmlspecialchars($row['Mnozstvi'] ?? $row['mnozstvi'] ?? '') ?>"
                                               data-mj="<?= htmlspecialchars($row['mj'] ?? 'kg') ?>"
                                               data-note="<?= htmlspecialchars($row['poznamka'] ?? '') ?>"
                                               data-zakaznici-ids="<?= htmlspecialchars($row['zakaznici_ids'] ?? '') ?>"
                                               data-produkty-ids="<?= htmlspecialchars($row['produkty_ids'] ?? '') ?>"
                                               title="Editovat požadavek"></i>

                                            <i class="glyphicon glyphicon-trash text-danger btn-delete-req no-detail-trigger"
                                               style="pointer-events: auto; cursor: pointer;"
                                               data-id="<?= $row['id'] ?>"
                                               title="Zrušit požadavek (KO)"></i>
                                        <?php endif; ?>

                                        <span class="req-id-badge <?= $status_class ?> no-detail-trigger" <?php if(!$is_total_cancel) echo "style='background-color: ".$row['color_bg']."; color: ".$badge_text_color.";'"; ?> title="Otevřít detail">
                <i class="glyphicon glyphicon-zoom-in" style="font-size: 11px; margin-right: 2px;"></i><?= $row['id'] ?>
            </span>
                                    </div>
                                </div>

                                <?php renderBadges($row); ?>

                                <?php
                                $zadane_mnozstvi = formatPozadavekMnozstvi($row);
                                $poptavky_lines = summarizePoptavkyVyvoje($row['nabidky_pole'] ?? []);
                                $meta_parts = [];
                                if (!$is_total_cancel) {
                                    if ($id_nakupci > 0 && $nakupci_jmeno !== '') {
                                        $nak_cls = $is_my_nakup_req ? 'meta-nakup meta-nakup-mine' : 'meta-nakup';
                                        $meta_parts[] = '<span class="' . $nak_cls . '" title="Řeší nákup"><i class="glyphicon glyphicon-briefcase"></i> ' . htmlspecialchars($nakupci_jmeno) . '</span>';
                                    } else {
                                        $meta_parts[] = '<span class="meta-nakup meta-nakup-free" title="Nikdo z nákupu nepřevzal"><i class="glyphicon glyphicon-briefcase"></i> volné</span>';
                                    }
                                }
                                if (!empty($row['zakaznici_seznam'])) {
                                    $meta_parts[] = '<span class="meta-zak" title="Zákazníci"><i class="glyphicon glyphicon-user"></i> ' . htmlspecialchars($row['zakaznici_seznam']) . '</span>';
                                }
                                if (!empty($row['produkty_seznam'])) {
                                    $meta_parts[] = '<span class="meta-prod" title="Vývojové produkty"><i class="glyphicon glyphicon-briefcase"></i> ' . htmlspecialchars($row['produkty_seznam']) . '</span>';
                                }
                                if (!empty($row['produkt_urgent']) && (int)$row['produkt_urgent'] === 1) {
                                    $meta_parts[] = '<span class="meta-urg-prod" title="Urgentní kvůli propojenému produktu"><i class="glyphicon glyphicon-flash"></i> z produktu</span>';
                                }
                                if ($zadane_mnozstvi) {
                                    $meta_parts[] = '<span class="meta-qty" title="Zadání množství"><i class="glyphicon glyphicon-scale"></i> ' . htmlspecialchars($zadane_mnozstvi) . '</span>';
                                }
                                if (!empty($poptavky_lines)) {
                                    $meta_parts[] = '<span class="meta-pop" title="Poptávka vývoje">' . implode(' · ', $poptavky_lines) . '</span>';
                                }
                                if (!empty($meta_parts)): ?>
                                    <div class="req-meta-compact"><?= implode('<span class="meta-sep">·</span>', $meta_parts) ?></div>
                                <?php endif; ?>

                                <?php if (!empty(trim($row['poznamka']))): ?>
                                    <div class="req-note-box <?= $status_class ?>">
                                        <i class="glyphicon glyphicon-info-sign req-note-icon <?= $status_class ?>"></i>
                                        <strong>Zadání:</strong> <?= nl2br(htmlspecialchars(trim($row['poznamka']))) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($history_req[$row['id']])): ?>
                                    <div class="offer-sys-msg-container">
                                        <?php
                                        // ZOBRAZENÍ VŠECH ZÁZNAMŮ (Akordeon Zoom to v CSS schová)
                                        $current_uid = $_SESSION['uid'] ?? 0;
                                        foreach($history_req[$row['id']] as $h) {
                                            renderHistoryRow($h, $is_adm, $current_uid);
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$is_total_cancel): ?>
                                    <div class="chat-flex-container" style="display: flex; gap: 4px; align-items: center; margin-top: 6px; border-top: 1px solid #eee; padding-top: 5px;">
                                        <button class="btn btn-link btn-inline-comment" data-id="<?= $row['id'] ?>" data-type="pozadavek" data-urgent="1" title="Odeslat jako URGENTNÍ" style="padding: 0 8px; color: #d9534f; font-size: 18px; text-decoration: none; opacity: 1;">
                                            <i class="glyphicon glyphicon-flash"></i>
                                        </button>
                                        <input type="text" class="form-control inline-comment-text" data-id="<?= $row['id'] ?>" data-type="pozadavek" placeholder="Napsat k zadání (požadavku)..." style="height: 30px; font-size: 11px; flex: 1; border-color: #e0e0e0;">
                                        <button class="btn btn-default btn-send btn-inline-comment" data-id="<?= $row['id'] ?>" data-type="pozadavek" data-urgent="0" title="Odeslat" style="padding: 2px 10px; height: 30px;">
                                            <i class="glyphicon glyphicon-send text-primary"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <div class="req-body">
                                <?php if ($is_urgent): ?>
                                    <?php foreach ($all_offers_for_this as $p) renderOfferRow($p, $is_adm, $can_nakup, $is_vyvoj, $is_quality, $col['id'], $row['color_bg'], $history_off[$p['id']] ?? []); ?>
                                    <button class="btn btn-sm btn-block btn-warning btn-add-offer btn-search-offer" data-id="<?= $row['id'] ?>">
                                        <i class="glyphicon glyphicon-search"></i> DOHLEDAT DODAVATELE
                                    </button>
                                <?php elseif (empty($all_offers_for_this) && $col['id'] == 1): ?>
                                    <div class="req-empty-msg <?= $status_class ?>" style="background: rgba(255,255,255,0.7);">
                                        <?= $is_postponed ? 'Požadavek je odložen (Čeká se na lepší časy).' : ($is_total_cancel ? 'Požadavek byl zrušen.' : 'Čeká se na vložení nabídky...') ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($all_offers_for_this as $p) renderOfferRow($p, $is_adm, $can_nakup, $is_vyvoj, $is_quality, $col['id'], $row['color_bg'], $history_off[$p['id']] ?? []); ?>
                                    <?php if ($col['id'] == 3 && !$is_total_cancel): ?>
                                        <a href="technologie.php" class="btn btn-sm btn-block btn-primary btn-goto-lab">
                                            <i class="glyphicon glyphicon-flask"></i> PŘEJÍT DO LABORATOŘE
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($can_nakup && in_array($col['id'], [1, 2]) && !$is_total_cancel): ?>
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

<script>
    var localLastChange = <?= $last_change_time ?? time() ?>;
</script>