<?php
include_once(__DIR__ . '/db_connect.php');

if (!isset($can_nakup)) {
    include_once(__DIR__ . '/permissions.php');
    $perms = loadSessionPermissions();
    $can_nakup = $perms['can_nakup'];
}
$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);
$users_tbl = defined('DB_TBL_USERS') ? DB_TBL_USERS : 'users';

function archiv_render_offers($rawString, $is_adm, $can_nakup) {
    if (empty($rawString)) {
        echo '<span class="text-muted small">Bez nabídek</span>';
        return;
    }

    $offers = explode(';;', $rawString);
    echo '<div class="offers-container" style="font-size: 11px; line-height: 1.2;">';

    foreach ($offers as $offer) {
        $p = explode('|', $offer);
        if (count($p) < 7 || ($p[0] == 'Neznámý dod.' && $p[1] == '0')) {
            continue;
        }
        ?>
        <div style="margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <strong><?= htmlspecialchars($p[0]) ?></strong>:
                <?= number_format((float)$p[1], 2, ',', ' ') ?> <?= htmlspecialchars($p[2]) ?>
                <span class="badge" style="background-color:<?= htmlspecialchars($p[4]) ?>; font-size: 8px;"><?= htmlspecialchars($p[3]) ?></span>
                <?php if (!empty($p[5]) && $p[5] !== '0000-00-00'): ?>
                    <br><span class="text-success" style="font-size: 9px;">
                        <i class="glyphicon glyphicon-calendar"></i> Doručeno: <?= date('d.m.Y', strtotime($p[5])) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($is_adm || $can_nakup): ?>
                <div style="white-space: nowrap; margin-left: 5px;" class="no-archiv-detail">
                    <a href="javascript:void(0);" class="text-warning btn-edit-offer"
                       data-id="<?= (int)$p[6] ?>"
                       data-dodavatel="<?= htmlspecialchars($p[0], ENT_QUOTES) ?>"
                       data-cena="<?= htmlspecialchars($p[1], ENT_QUOTES) ?>"
                       data-mena="<?= htmlspecialchars($p[2] ?: 'CZK', ENT_QUOTES) ?>"
                       title="Upravit nabídku">
                        <i class="glyphicon glyphicon-pencil"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    echo '</div>';
}

// Oprava starších dat: nabídka TEST OK (6), požadavek ještě otevřený
mysqli_query($conn, "UPDATE pozadavky p
    INNER JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id AND pn.id_status = 6
    SET p.id_status = 6
    WHERE p.id_status NOT IN (5, 6, 7, 8)");

@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

$sql = "SELECT p.*,
               s.nazev AS surovina_nazev,
               u_nak.jmeno AS nakupci_jmeno,
               cs_main.nazev AS status_nazev_hlavni,
               cs_main.barva_hex AS status_barva_hlavni,
               (SELECT GROUP_CONCAT(z.nazev SEPARATOR ', ')
                  FROM pozadavky_zakaznici pz
                  JOIN zakaznici z ON pz.id_zakaznik = z.id
                 WHERE pz.id_pozadavek = p.id) AS zakaznik_nazev,
               GROUP_CONCAT(
                   CONCAT(
                       IFNULL(d.nazev, 'Neznámý dod.'), '|',
                       IFNULL(pn.cena_nabidka, '0'), '|',
                       IFNULL(pn.mena, 'CZK'), '|',
                       IFNULL(cs.nazev, 'Nový'), '|',
                       IFNULL(cs.barva_hex, '#ccc'), '|',
                       IFNULL(pn.vzorek_dorazil, ''), '|',
                       IFNULL(pn.id, '0')
                   ) SEPARATOR ';;'
               ) AS nabidky_raw
        FROM pozadavky p
        LEFT JOIN suroviny s ON p.id_surovina = s.id
        LEFT JOIN $users_tbl u_nak ON p.id_nakupci = u_nak.id
        LEFT JOIN ciselnik_statusu cs_main ON p.id_status = cs_main.id
        LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
        LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
        LEFT JOIN ciselnik_statusu cs ON pn.id_status = cs.id
        WHERE p.id_status >= 5
           OR EXISTS (
                SELECT 1 FROM pozadavky_nabidky pn_ok
                WHERE pn_ok.id_pozadavek = p.id AND pn_ok.id_status = 6
           )
        GROUP BY p.id
        ORDER BY p.datumPozadavek DESC";

$result = mysqli_query($conn, $sql);
$rows = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
}

$total_all = count($rows);
$total_bez_ko = 0;
foreach ($rows as $_r) {
    $st = (int)($_r['id_status'] ?? 0);
    if ($st !== 5 && $st !== 7) {
        $total_bez_ko++;
    }
}
$total_ko = $total_all - $total_bez_ko;
?>

<div class="container-fluid archiv-page mt-4">
    <?php if (!$result): ?>
        <div class="alert alert-danger">Archiv se nepodařilo načíst: <?= htmlspecialchars(mysqli_error($conn)) ?></div>
    <?php endif; ?>

    <div class="archiv-toolbar panel panel-default">
        <div class="panel-body" style="padding:12px 14px;">
            <div class="row" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <div style="flex:1; min-width:220px;">
                    <div class="input-group">
                        <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
                        <input type="text" id="archivSearch" class="form-control" placeholder="Hledat surovinu, #ID, zákazníka, nákupčího…">
                    </div>
                </div>
                <div class="btn-group" id="archivStatusFilter" role="group">
                    <button type="button" class="btn btn-sm btn-primary archiv-status-btn active" data-status="bez_ko">Vše <span class="badge"><?= $total_bez_ko ?></span></button>
                    <button type="button" class="btn btn-sm btn-default archiv-status-btn" data-status="6">Hotovo</button>
                    <button type="button" class="btn btn-sm btn-default archiv-status-btn" data-status="8">K ledu</button>
                </div>
                <button type="button" class="btn btn-sm btn-default" id="archivShowKo" data-on="0" title="Zobrazit zamítnuté požadavky (KO)">
                    <i class="glyphicon glyphicon-ban-circle"></i> Zobrazit KO<?php if ($total_ko > 0): ?> <span class="badge"><?= $total_ko ?></span><?php endif; ?>
                </button>
                <div class="text-muted small" id="archivFilterCount" style="min-width:90px;"></div>
            </div>
        </div>
    </div>

    <div class="panel panel-success shadow-sm archiv-panel">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="glyphicon glyphicon-folder-close"></i> Archiv požadavků
                <span class="badge pull-right archiv-panel-count"><?= $total_bez_ko ?></span>
            </h3>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-condensed table-sjednocena bg-white mb-0 archiv-table">
                <thead>
                <tr class="bg-success">
                    <th style="width: 50px;">ID</th>
                    <th style="width: 28%;">Surovina / Status</th>
                    <th style="width: 22%;">Zákazník</th>
                    <th>Nabídky</th>
                    <th style="width: 44px;">Akce</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr class="archiv-empty"><td colspan="5" class="text-muted text-center" style="padding:20px;">Zatím prázdné</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row):
                    $st = (int)$row['id_status'];
                    $is_today = (date('Y-m-d') === date('Y-m-d', strtotime($row['datumPozadavek'])));
                    $zak = trim($row['zakaznik_nazev'] ?? '');
                    if ($zak === '' && !empty($row['zakaznik'])) {
                        $zak = $row['zakaznik'];
                    }
                    $search_blob = strtolower(implode(' ', [
                        $row['id'],
                        $row['surovina_nazev'] ?? '',
                        $zak,
                        $row['nakupci_jmeno'] ?? '',
                        $row['status_nazev_hlavni'] ?? '',
                        $row['nabidky_raw'] ?? '',
                    ]));
                    ?>
                    <tr class="archiv-row"
                        data-req-id="<?= (int)$row['id'] ?>"
                        data-status="<?= $st ?>"
                        data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES) ?>"
                        tabindex="0"
                        role="link"
                        title="Otevřít detail #<?= (int)$row['id'] ?>">
                        <td class="text-center text-muted small">#<?= (int)$row['id'] ?></td>
                        <td>
                            <div style="margin-bottom: 5px;">
                                <span class="label" style="background-color: <?= htmlspecialchars($row['status_barva_hlavni'] ?: '#ccc') ?>; font-size: 10px; text-transform: uppercase;">
                                    <?= htmlspecialchars($row['status_nazev_hlavni'] ?: 'Nový') ?>
                                </span>
                            </div>
                            <strong><?= htmlspecialchars($row['surovina_nazev'] ?? 'Neznámá') ?></strong>
                            <?php if (!empty($row['nakupci_jmeno'])): ?>
                                <div style="font-size: 10px; color: #555; margin-top: 4px;">
                                    <i class="glyphicon glyphicon-briefcase"></i> <?= htmlspecialchars($row['nakupci_jmeno']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-primary"><?= htmlspecialchars($zak !== '' ? $zak : '—') ?></small>
                            <div style="margin-top:4px;">
                                <?php if (!empty($row['bio'])) echo '<span class="badge" style="background-color:#28a745; font-size:9px;">BIO</span> '; ?>
                                <?php if (!empty($row['vegan'])) echo '<span class="badge" style="background-color:#17a2b8; font-size:9px;">VGN</span> '; ?>
                                <?php if (!empty($row['bezlepek'])) echo '<span class="badge" style="background-color:#ffc107; color:#000; font-size:9px;">BL</span> '; ?>
                            </div>
                        </td>
                        <td>
                            <?php archiv_render_offers($row['nabidky_raw'], $is_adm, $can_nakup); ?>
                        </td>
                        <td class="text-center no-archiv-detail">
                            <?php if ($is_adm || $is_today): ?>
                                <a href="javascript:void(0);" class="btn btn-danger btn-xs btn-delete-ajax" data-id="<?= (int)$row['id'] ?>" data-table="pozadavky"><i class="glyphicon glyphicon-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
