<?php
include_once("includes/db_connect.php");

// 1. DEFINICE ROLÍ
$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);
$is_orders = (!empty($_SESSION['orders']) && $_SESSION['orders'] == 1); // NÁKUP
$is_vyvoj = (!empty($_SESSION['vyvoj']) && $_SESSION['vyvoj'] == 1);     // VÝVOJ
$is_quality = (!empty($_SESSION['kvalita']) && $_SESSION['kvalita'] == 1); // KVALITA

// 2. POMOCNÉ FUNKCE
function renderBadges($row) { ?>
    <div style="display: flex; gap: 3px; flex-wrap: wrap;">
        <?php if(!empty($row['bio'])): ?><span class="badge" style="background-color:#28a745; font-size:9px;">BIO</span><?php endif; ?>
        <?php if(!empty($row['vegan'])): ?><span class="badge" style="background-color:#17a2b8; font-size:9px;">VGN</span><?php endif; ?>
        <?php if(!empty($row['bezlepek'])): ?><span class="badge" style="background-color:#ffc107; color:#000; font-size:9px;">BL</span><?php endif; ?>
    </div>
<?php }

function renderOffers($rawString, $is_adm, $is_orders, $is_vyvoj, $is_quality) {
    if (empty($rawString)) {
        echo '<span class="text-muted small">Bez nabídek</span>';
        return;
    }

    $offers = explode(';;', $rawString);
    $ted_timestamp = strtotime(date('Y-m-d'));

    echo '<div class="offers-container" style="font-size: 11px; line-height: 1.2;">';
    foreach ($offers as $offer) {
        $p = explode('|', $offer);
        if (count($p) < 13 || ($p[0] == 'Neznámý dod.' && $p[1] == '0')) continue;

        $p_nabidka_id = $p[6];
        $p_status_id  = (int)$p[7];
        $p_updated_at = $p[8];
        $p_poznamka_cena = $p[9] ?? '';
        $p_poznamka_vzorek = $p[10] ?? '';
        $p_datum_vzorku_raw = $p[11] ?? '';
        $p_link = $p[12] ?? '';

        // Skrývání starých zamítnutých
        $is_rejected = in_array($p_status_id, [5, 7]);
        if ($p_status_id == 7 && !empty($p_updated_at) && (time() - strtotime($p_updated_at)) > 86400) continue;

        $bg_style = ($is_rejected) ? 'background: #fff5f5; border-left: 2px solid #dc3545;' : '';
        ?>
        <div class="offer-row <?= $is_rejected ? 'offer-rejected' : '' ?>" style="margin-bottom: 8px; padding: 6px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: flex-start; <?= $bg_style ?> border-radius: 4px;">
            <div style="flex-grow: 1;">
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 5px;">
                    <strong><?= htmlspecialchars($p[0]) ?></strong>:
                    <b style="color: #333;"><?= number_format((float)$p[1], 2, ',', ' ') ?> CZK</b>
                    <span class="label" style="background-color:<?= $p[4] ?>; font-size: 9px;"><?= htmlspecialchars($p[3]) ?></span>

                    <?php if (!empty($p_link)): ?>
                        <a href="<?= htmlspecialchars($p_link) ?>" target="_blank" class="label label-info" style="font-size: 9px; cursor:pointer;"><i class="glyphicon glyphicon-folder-open"></i> Dokumentace</a>
                    <?php endif; ?>
                </div>

                <?php
                if (!empty($p_datum_vzorku_raw) && $p_datum_vzorku_raw !== '0000-00-00' && $p_status_id != 7) {
                    $ts_vzorku = strtotime($p_datum_vzorku_raw);
                    echo '<span class="text-' . ($ts_vzorku > $ted_timestamp ? 'warning' : 'success') . '" style="font-size: 9px; display:block; margin-top:2px;"><i class="glyphicon glyphicon-' . ($ts_vzorku > $ted_timestamp ? 'plane' : 'import') . '"></i> ' . ($ts_vzorku > $ted_timestamp ? 'Očekáváno: ' : 'Doručeno: ') . date('d.m.Y', $ts_vzorku) . '</span>';
                }
                ?>

                <?php if (!empty($p_poznamka_cena)): ?><div class="small text-danger" style="margin-top: 4px;"><b>Důvod KO:</b> <?= htmlspecialchars($p_poznamka_cena) ?></div><?php endif; ?>
                <?php if (!empty($p_poznamka_vzorek)): ?><div class="small text-muted" style="margin-top: 2px;"><b>QC/Vývoj:</b> <?= htmlspecialchars($p_poznamka_vzorek) ?></div><?php endif; ?>

                <div class="akce-workflow" style="margin-top: 6px; display: flex; gap: 5px; flex-wrap: wrap;">
                    <?php if ($is_orders || $is_adm || $is_quality): ?>
                        <button class="btn btn-xs btn-default btn-workflow-trigger" data-id="<?= $p_nabidka_id ?>" data-status="no_change" data-upload="1" title="Nahrát dokumentaci ke konkrétní nabídce">
                            <i class="glyphicon glyphicon-cloud-upload"></i> Nahrát dok.
                        </button>
                    <?php endif; ?>

                    <?php if ($is_orders || $is_adm): ?>
                        <button class="btn btn-xs btn-primary btn-add-offer" data-id-pozadavek="<?= $row['id'] ?>" title="Přidat další nabídku k této surovině">
                            <i class="glyphicon glyphicon-plus"></i> Nová cena
                        </button>
                    <?php endif; ?>

                    <?php if ($p_status_id == 2 && ($is_vyvoj || $is_adm)): ?>
                        <button class="btn btn-xs btn-success btn-workflow-trigger" data-id="<?= $p_nabidka_id ?>" data-status="3">Cena OK</button>
                        <button class="btn btn-xs btn-danger btn-workflow-trigger" data-id="<?= $p_nabidka_id ?>" data-status="7">KO cena</button>
                    <?php endif; ?>
                </div>
            </div>

            <div style="white-space: nowrap; margin-left: 5px; display: flex; align-items: center; gap: 4px;">
                <?php if ($is_adm || $is_orders): ?>
                    <?php if ($p_status_id == 9): ?>
                        <button class="btn btn-xs btn-warning btn-objednat-vzorek" data-id="<?= $p_nabidka_id ?>"><i class="glyphicon glyphicon-send"></i> Vzorek</button>
                    <?php endif; ?>
                    <a href="javascript:void(0);" class="btn-delete-ajax text-danger" data-id="<?= $p_nabidka_id ?>" data-table="pozadavky_nabidky"><i class="glyphicon glyphicon-trash"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    echo '</div>';
}

// 3. SQL DOTAZ
$sql = "SELECT p.*, z.nazev AS zakaznik_nazev, s.nazev AS surovina_nazev, cs_main.nazev AS status_nazev_hlavni, cs_main.barva_hex AS status_barva_hlavni,
               GROUP_CONCAT(CONCAT(IFNULL(d.nazev, 'Neznámý dod.'), '|', IFNULL(pn.cena_nabidka, '0'), '|', IFNULL(pn.mena, ''), '|', IFNULL(cs.nazev, 'Nový'), '|', IFNULL(cs.barva_hex, '#ccc'), '|', IFNULL(pn.vzorek_dorazil, ''), '|', IFNULL(pn.id, '0'), '|', IFNULL(pn.id_status, '0'), '|', IFNULL(pn.updated_at, ''), '|', IFNULL(pn.poznamka_cena, ''), '|', IFNULL(pn.poznamka_vzorek, ''), '|', IFNULL(pn.vzorek_objednan, ''), '|', IFNULL(pn.link_dokumentace, '')) SEPARATOR ';;') as nabidky_raw
        FROM pozadavky p 
        LEFT JOIN zakaznik z ON p.id_zakaznik = z.id 
        LEFT JOIN suroviny s ON p.id_surovina = s.id 
        LEFT JOIN ciselnik_statusu cs_main ON p.id_status = cs_main.id 
        LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
        LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
        LEFT JOIN ciselnik_statusu cs ON pn.id_status = cs.id
        GROUP BY p.id HAVING p.id_status < 5 OR p.id_status = 8 ORDER BY p.datumPozadavek DESC";

$result = mysqli_query($conn, $sql);
$faze_vyvoj = [];
$faze_kvalita = [];

while ($row = mysqli_fetch_assoc($result)) {
    $offers = !empty($row['nabidky_raw']) ? explode(';;', $row['nabidky_raw']) : [];
    $vsechny_ceny_schvaleny = true;
    $existuje_nabidka = false;

    foreach ($offers as $o) {
        $parts = explode('|', $o);
        if (count($parts) < 8 || ($parts[0] == 'Neznámý dod.' && $parts[1] == '0')) continue;
        $existuje_nabidka = true;
        if (in_array((int)$parts[7], [1, 2])) $vsechny_ceny_schvaleny = false;
    }

    if ($existuje_nabidka && $vsechny_ceny_schvaleny) $faze_kvalita[] = $row;
    else $faze_vyvoj[] = $row;
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <?php
        $panels = [
            ['data' => $faze_vyvoj, 'title' => '1. Fáze: Schvalování Ceny (Vývoj / Nákup)', 'icon' => 'usd', 'class' => 'info'],
            ['data' => $faze_kvalita, 'title' => '2. Fáze: Dokumentace & Vzorky (QC)', 'icon' => 'filter', 'class' => 'success']
        ];
        foreach ($panels as $panel): ?>
            <div class="col-lg-6">
                <div class="panel panel-<?= $panel['class'] ?> shadow-sm">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="glyphicon glyphicon-<?= $panel['icon'] ?>"></i> <?= $panel['title'] ?></h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed bg-white mb-0">
                            <thead>
                            <tr class="bg-<?= $panel['class'] ?>">
                                <th style="width: 40px;">ID</th>
                                <th>Surovina</th>
                                <th>Nabídky / Akce</th>
                                <th style="width: 40px;"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($panel['data'] as $row): ?>
                                <tr>
                                    <td class="text-center text-muted small"><?= $row['id'] ?></td>
                                    <td>
                                        <div style="margin-bottom: 5px;"><span class="label" style="background-color: <?= $row['status_barva_hlavni'] ?: '#ccc' ?>; font-size: 10px;"><?= htmlspecialchars($row['status_nazev_hlavni'] ?: 'Nový') ?></span></div>
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                            <strong><?= htmlspecialchars($row['surovina_nazev'] ?? 'Neznámá') ?></strong>
                                            <?php if ($is_orders || $is_adm): ?>
                                                <button class="btn btn-xs btn-primary btn-add-offer" data-id-pozadavek="<?= $row['id'] ?>"><i class="glyphicon glyphicon-plus"></i> Nabídka</button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($panel['class'] === 'success') echo '<div style="margin-top:5px;"><small class="text-primary">'.htmlspecialchars($row['zakaznik_nazev'] ?? 'Interní').'</small></div>'; ?>
                                    </td>
                                    <td>
                                        <?php renderBadges($row); ?>
                                        <div style="margin-top: 8px; padding-top: 5px; border-top: 1px dashed #ddd;">
                                            <?php renderOffers($row['nabidky_raw'], $is_adm, $is_orders, $is_vyvoj, $is_quality); ?>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php if ($is_adm): ?><a href="javascript:void(0);" class="btn btn-danger btn-xs btn-delete-ajax" data-id="<?= $row['id'] ?>" data-table="pozadavky"><i class="glyphicon glyphicon-trash"></i></a><?php endif; ?></td>
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

<div class="modal fade" id="modalPotvrzeni" tabindex="-1" role="dialog" style="z-index: 9999;">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div id="modalHeaderColor" class="modal-header" style="color: white; background-color: #337ab7;">
                <button type="button" class="close" data-dismiss="modal" style="color:white;">&times;</button>
                <h4 class="modal-title" id="modalTitle">Potvrdit akci</h4>
            </div>
            <div class="modal-body">
                <div id="div-datum" class="form-group" style="display:none;">
                    <label class="small">Předpokládaný příchod vzorku:</label>
                    <input type="date" id="input_datum" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <div id="div-upload" class="form-group" style="display:none; border: 2px dashed #337ab7; padding: 25px; text-align: center; border-radius: 10px; background: #f9f9f9; cursor: pointer;">
                    <label style="cursor:pointer; width: 100%;">
                        <i class="glyphicon glyphicon-cloud-upload" style="font-size: 32px; color: #337ab7;"></i><br>
                        <strong>Přetáhněte dokumenty sem</strong><br>
                        <span class="small text-muted">nebo klikněte pro výběr (PDF, JPG, ...)</span>
                        <input type="file" id="file_input" multiple style="display:none;">
                    </label>
                    <div id="upload_status" style="margin-top:15px; text-align: left; font-size: 11px;"></div>
                </div>

                <div class="form-group mb-0">
                    <label id="label-poznamka" class="small">Vyjádření / Poznámka:</label>
                    <textarea id="input_poznamka" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Zavřít</button>
                <button type="button" id="btnConfirmGlobal" class="btn btn-sm btn-success">Uložit změny</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovaNabidka" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background: #337ab7; color: white;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Nová nabídka</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="new_offer_id_pozadavek">
                <div class="form-group">
                    <label>Dodavatel:</label>
                    <select id="new_offer_dodavatel" class="form-control">
                        <?php
                        $d_res = mysqli_query($conn, "SELECT id, nazev FROM dodavatele ORDER BY nazev ASC");
                        while($d = mysqli_fetch_assoc($d_res)) echo "<option value='{$d['id']}'>{$d['nazev']}</option>";
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cena (CZK):</label>
                    <input type="text" id="new_offer_cena" class="form-control" placeholder="0.00">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnSaveNewOffer" class="btn btn-primary btn-block">Uložit nabídku</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var activeData = { id: null, status: null };

        // 1. MODÁL PRO NOVOU NABÍDKU
        $(document).on('click', '.btn-add-offer', function() {
            $('#new_offer_id_pozadavek').val($(this).data('id-pozadavek'));
            $('#modalNovaNabidka').modal('show');
        });

        $('#btnSaveNewOffer').on('click', function() {
            var payload = {
                id_pozadavek: $('#new_offer_id_pozadavek').val(),
                id_dodavatel: $('#new_offer_dodavatel').val(),
                cena: $('#new_offer_cena').val()
            };
            $.post('includes/ajax_add_offer.php', payload, function(res) {
                if(res.trim() === "OK") location.reload(); else alert(res);
            });
        });

        // 2. WORKFLOW A NAHRÁVÁNÍ
        function openWorkflowModal(id, status, title, color, showDate, showUpload, placeholder) {
            activeData = { id: id, status: status };
            $('#modalTitle').text(title);
            $('#modalHeaderColor').css('background-color', color);
            $('#input_poznamka').val('').attr('placeholder', placeholder);
            $('#upload_status').empty();
            $('#file_input').val('');

            if(showDate) $('#div-datum').show(); else $('#div-datum').hide();

            if(status === 'no_change') {
                $('#btnConfirmGlobal').hide();
                $('#div-upload').show();
            } else {
                $('#btnConfirmGlobal').show();
                if(showUpload) $('#div-upload').show(); else $('#div-upload').hide();
            }
            $('#modalPotvrzeni').modal('show');
        }

        $(document).on('click', '.btn-workflow-trigger', function() {
            var btn = $(this);
            var st = btn.data('status');
            var color = (st == 5 || st == 7) ? '#d9534f' : ((st == 'no_change') ? '#777' : '#5cb85c');
            openWorkflowModal(btn.data('id'), st, btn.text(), color, false, (btn.data('upload') == 1), 'Vyjádření...');
        });

        $(document).on('click', '.btn-objednat-vzorek', function() {
            openWorkflowModal($(this).data('id'), 4, 'Objednání vzorku', '#f0ad4e', true, false, 'Poznámka k objednávce...');
        });

        $('#file_input').on('change', function() {
            for (var i = 0; i < this.files.length; i++) uploadFile(this.files[i]);
        });

        function uploadFile(file) {
            var formData = new FormData();
            formData.append('file', file);
            formData.append('id_nabidka', activeData.id);
            var fid = 'up_' + Math.floor(Math.random() * 1000);
            $('#upload_status').append('<div id="'+fid+'"><i class="glyphicon glyphicon-refresh spin"></i> '+file.name+'...</div>');

            $.ajax({
                url: 'includes/upload_to_nextcloud.php',
                type: 'POST', data: formData, contentType: false, processData: false,
                success: function(r) {
                    $('#'+fid).html(r.trim()==="OK" ? '<span class="text-success">✔ '+file.name+'</span>' : '<span class="text-danger">✘ '+file.name+'</span>');
                }
            });
        }

        $('#btnConfirmGlobal').on('click', function() {
            var p = { id: activeData.id, status: activeData.status, poznamka: $('#input_poznamka').val() };
            if (activeData.status === 4) p.datum_objednani = $('#input_datum').val();
            $.post('includes/update_status_nabidka.php', p, function(res) {
                if(res.trim() === "OK") location.reload(); else alert(res);
            });
        });
    });
</script>

<style>
    .spin { animation: spin 2s infinite linear; }
    @keyframes spin { from {transform:rotate(0deg);} to {transform:rotate(359deg);} }
    #div-upload:hover { border-color: #285e8e !important; background: #eef7fd !important; }
    .offer-row:hover { background: #fcfcfc; }
</style>