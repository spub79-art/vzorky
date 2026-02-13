<?php
include_once("includes/db_connect.php");

// 1. DEFINICE ROLÍ
$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);
$is_orders = (!empty($_SESSION['orders']) && $_SESSION['orders'] == 1);

// 2. POMOCNÉ FUNKCE

function renderBadges($row) { ?>
    <div style="display: flex; gap: 3px; flex-wrap: wrap;">
        <?php if(!empty($row['bio'])): ?><span class="badge" style="background-color:#28a745; font-size:9px;">BIO</span><?php endif; ?>
        <?php if(!empty($row['vegan'])): ?><span class="badge" style="background-color:#17a2b8; font-size:9px;">VGN</span><?php endif; ?>
        <?php if(!empty($row['bezlepek'])): ?><span class="badge" style="background-color:#ffc107; color:#000; font-size:9px;">BL</span><?php endif; ?>
    </div>
<?php }

function renderOffers($rawString, $is_adm, $is_orders) {
    if (empty($rawString)) {
        echo '<span class="text-muted small">Bez nabídek</span>';
        return;
    }

    $offers = explode(';;', $rawString);
    echo '<div class="offers-container" style="font-size: 11px; line-height: 1.2;">';

    foreach ($offers as $offer) {
        $p = explode('|', $offer);
        // Indexy: 0:Dodavatel, 1:Cena, 2:Měna, 3:Status, 4:Barva, 5:Datum, 6:ID_nabidky
        if (count($p) < 7 || ($p[0] == 'Neznámý dod.' && $p[1] == '0')) continue;
        ?>
        <div style="margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <strong><?= htmlspecialchars($p[0]) ?></strong>:
                <?= number_format((float)$p[1], 2, ',', ' ') ?> <?= htmlspecialchars($p[2]) ?>
                <span class="badge" style="background-color:<?= $p[4] ?>; font-size: 8px;"><?= htmlspecialchars($p[3]) ?></span>

                <?php if (!empty($p[5]) && $p[5] !== '0000-00-00'): ?>
                    <br><span class="text-success" style="font-size: 9px;">
                        <i class="glyphicon glyphicon-calendar"></i> Doručeno: <?= date('d.m.Y', strtotime($p[5])) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($is_adm || $is_orders): ?>
                <div style="white-space: nowrap; margin-left: 5px;">
                    <a href="javascript:void(0);" class="text-warning btn-edit-offer" data-id="<?= $p[6] ?>" title="Upravit nabídku">
                        <i class="glyphicon glyphicon-pencil"></i>
                    </a>
                    <a href="javascript:void(0);" class="btn-delete-ajax text-danger" data-id="<?= $p[6] ?>" data-table="pozadavky_nabidky" style="margin-left:5px;" title="Smazat nabídku">
                        <i class="glyphicon glyphicon-trash"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    echo '</div>';
}

// 3. SQL DOTAZ
$sql = "SELECT p.*, 
               z.nazev AS zakaznik_nazev, 
               s.nazev AS surovina_nazev,
               cs_main.nazev AS status_nazev_hlavni,
               cs_main.barva_hex AS status_barva_hlavni,
               GROUP_CONCAT(
                   CONCAT(
                       IFNULL(d.nazev, 'Neznámý dod.'), '|', 
                       IFNULL(pn.cena_nabidka, '0'), '|', 
                       IFNULL(pn.mena, ''), '|',
                       IFNULL(cs.nazev, 'Nový'), '|',
                       IFNULL(cs.barva_hex, '#ccc'), '|',
                       IFNULL(pn.vzorek_dorazil, ''), '|',
                       IFNULL(pn.id, '0')
                   ) SEPARATOR ';;'
               ) as nabidky_raw
        FROM pozadavky p 
        LEFT JOIN zakaznik z ON p.id_zakaznik = z.id 
        LEFT JOIN suroviny s ON p.id_surovina = s.id 
        LEFT JOIN ciselnik_statusu cs_main ON p.id_status = cs_main.id 
        LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
        LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
        LEFT JOIN ciselnik_statusu cs ON pn.id_status = cs.id
        GROUP BY p.id
        HAVING p.id_status >= 5  -- V archivu uvidíš jen ty hotové
        ORDER BY p.datumPozadavek DESC";

$result = mysqli_query($conn, $sql);
$poptavky_ceny = [];
$zadosti_vzorky = [];
while ($row = mysqli_fetch_assoc($result)) {
    if (isset($row['typ']) && $row['typ'] === 'poptavka') $poptavky_ceny[] = $row;
    else $zadosti_vzorky[] = $row;
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <?php
        $panels = [
            ['data' => $poptavky_ceny, 'title' => 'Zjištění ceny', 'icon' => 'usd', 'class' => 'info', 'label' => 'Vlastnosti'],
            ['data' => $zadosti_vzorky, 'title' => 'Žádosti o vzorky', 'icon' => 'filter', 'class' => 'success', 'label' => 'Zákazník']
        ];

        foreach ($panels as $panel): ?>
            <div class="col-lg-6">
                <div class="panel panel-<?= $panel['class'] ?> shadow-sm">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-<?= $panel['icon'] ?>"></i> <?= $panel['title'] ?></h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed table-sjednocena bg-white mb-0">
                            <thead>
                            <tr class="bg-<?= $panel['class'] ?>">
                                <th style="width: 40px;">ID</th>
                                <th>Surovina / Status</th>
                                <th><?= $panel['label'] ?> / Nabídky</th>
                                <th style="width: 40px;">Akce</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($panel['data'] as $row):
                                $is_today = (date('Y-m-d') === date('Y-m-d', strtotime($row['datumPozadavek']))); ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= $row['id'] ?></td>
                                    <td>
                                        <div style="margin-bottom: 5px;">
                                            <span class="label" style="background-color: <?= $row['status_barva_hlavni'] ?: '#ccc' ?>; font-size: 10px; text-transform: uppercase;">
                                                <?= htmlspecialchars($row['status_nazev_hlavni'] ?: 'Nový') ?>
                                            </span>
                                        </div>
                                        <strong><?= htmlspecialchars($row['surovina_nazev'] ?? 'Neznámá') ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        if ($panel['class'] === 'success') {
                                            echo '<small class="text-primary" style="display:block; margin-bottom:5px;">' . htmlspecialchars($row['zakaznik_nazev'] ?? 'Interní') . '</small>';
                                        } else {
                                            renderBadges($row);
                                        }
                                        ?>
                                        <div style="margin-top: 8px; padding-top: 5px; border-top: 1px dashed #ddd;">
                                            <?php renderOffers($row['nabidky_raw'], $is_adm, $is_orders); ?>
                                            <?php if ($is_adm || $is_orders): ?>
                                                <a href="index.php?add_Nabidka=1&id_pozadavek=<?= $row['id'] ?>" class="btn btn-link btn-xs" style="padding:0; font-size: 10px; margin-top: 5px; display: block;">
                                                    <i class="glyphicon glyphicon-plus"></i> Přidat nabídku
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($is_adm || $is_today): ?>
                                            <a href="javascript:void(0);" class="btn btn-danger btn-xs btn-delete-ajax" data-id="<?= $row['id'] ?>" data-table="pozadavky"><i class="glyphicon glyphicon-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>