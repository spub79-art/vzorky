<?php
include_once("db_connect.php");
include_once("boardFunctions.php");
include_once("boardOfferRow.php");

// Prevence oříznutí seznamu souborů v GROUP_CONCAT
@mysqli_query($conn, "SET SESSION group_concat_max_len = 10000");

// Role a oprávnění z relace
$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);
$is_orders = (!empty($_SESSION['orders']) && $_SESSION['orders'] == 1);
$is_vyvoj = (!empty($_SESSION['vyvoj']) && $_SESSION['vyvoj'] == 1);
$is_quality = (!empty($_SESSION['kvalita']) && $_SESSION['kvalita'] == 1);

// Čas poslední změny pro auto-refresh
$last_change_time = file_exists('last_change.txt') ? file_get_contents('last_change.txt') : time();

// SQL Dotaz pro načtení požadavků a jejich nabídek
$sql = "SELECT p.*, s.nazev AS surovina_nazev, 
        GROUP_CONCAT(CONCAT(IFNULL(d.nazev, 'Neznámý'), '|', IFNULL(pn.cena_nabidka, '0'), '|', IFNULL(pn.mena, 'CZK'), '|', IFNULL(cs.nazev, 'Nový'), '|', IFNULL(cs.barva_hex, '#ccc'), '|', IFNULL(pn.vzorek_dorazil, ''), '|', IFNULL(pn.id, '0'), '|', IFNULL(pn.id_status, '0'), '|', IFNULL(pn.updated_at, ''), '|', IFNULL(pn.poznamka_cena, ''), '|', IFNULL(pn.poznamka_vzorek, ''), '|', IFNULL(pn.vzorek_objednan, ''), '|', IFNULL(pn.link_dokumentace, ''), '|', IFNULL(pn.seznam_souboru, ''), '|', IFNULL(pn.sarze, ''), '|', IFNULL(pn.pozadovane_mnozstvi, '')) SEPARATOR ';;') as nabidky_raw
        FROM pozadavky p 
        LEFT JOIN suroviny s ON p.id_surovina = s.id 
        LEFT JOIN pozadavky_nabidky pn ON pn.id_pozadavek = p.id
        LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
        LEFT JOIN ciselnik_statusu cs ON pn.id_status = cs.id
        GROUP BY p.id HAVING p.id_status != 6 ORDER BY p.datumPozadavek DESC";

$result = mysqli_query($conn, $sql);
$f1 = $f2 = $f3 = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['color_bg'] = getUniqueColor($row['id']);

        // Příznaky pro aktivní nabídky v jednotlivých fázích
        $p1_active = $p2_active = $p3_active = $has_any_active = false;

        if (!empty($row['nabidky_raw'])) {
            foreach (explode(';;', $row['nabidky_raw']) as $o) {
                $pts = explode('|', $o);
                if (count($pts) < 8 || $pts[6] == '0') continue;
                $s_id = (int)$pts[7];

                // Nabídka ovlivňuje pozici karty JEN pokud není zamítnutá (5, 7)
                if (!in_array($s_id, [5, 7])) {
                    $has_any_active = true;
                    // Rozřazení do fází podle statusu nabídky
                    if (in_array($s_id, [10, 4])) $p3_active = true;
                    elseif (in_array($s_id, [3, 8, 9, 11])) $p2_active = true;
                    else $p1_active = true; // Status 1 a 2
                }
            }
        }

        // Pokud surovina nemá ŽÁDNOU aktivní nabídku, musí svítit nákupu k dohledání (Urgent v 1. fázi)
        if (!$has_any_active) {
            $p1_active = true;
            $row['buyer_must_act'] = true;
        } else {
            $row['buyer_must_act'] = false;
        }

        if ($p1_active) $f1[] = $row;
        if ($p2_active) $f2[] = $row;
        if ($p3_active) $f3[] = $row;
    }
}
?>

<style>
    .offer-rejected { display: none; }
    .req-card-urgent { border: 2px solid #f0ad4e !important; background: #fffdfa !important; box-shadow: 0 4px 8px rgba(240,173,78,0.1); }
    .spinning { animation: spin 1s infinite linear; display: inline-block; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<div class="row" style="margin-bottom: 10px; padding: 0 5px;">
    <div class="col-md-12">
        <button id="btnToggleRejected" class="btn btn-xs btn-default"><i class="glyphicon glyphicon-eye-open"></i> Zobrazit zamítnuté (KO)</button>
    </div>
</div>

<div class="row" id="board-container">
    <?php
    $cols = [
        ['data'=>$f1, 't'=>'1. Fáze: Výběr', 'c'=>'info', 'id'=>1],
        ['data'=>$f2, 't'=>'2. Fáze: Dokumenty', 'c'=>'success', 'id'=>2],
        ['data'=>$f3, 't'=>'3. Fáze: Testování', 'c'=>'warning', 'id'=>3]
    ];

    foreach ($cols as $col): ?>
        <div class="col-lg-4" style="padding: 0 5px;">
            <div class="panel panel-<?= $col['c'] ?>" style="margin-bottom: 10px;">
                <div class="panel-heading" style="padding: 5px 10px;">
                    <b><?= $col['t'] ?></b> <span class="badge pull-right"><?= count($col['data']) ?></span>
                </div>
                <div class="panel-body" style="background:#f8f9fa; min-height:800px; padding:6px;">
                    <?php foreach ($col['data'] as $row):
                        $all_offers_for_this = [];
                        if (!empty($row['nabidky_raw'])) {
                            foreach(explode(';;', $row['nabidky_raw']) as $o) {
                                $pts = explode('|', $o);
                                if (count($pts) > 6 && $pts[6] != '0') $all_offers_for_this[] = $pts;
                            }
                        }
                        $is_urgent = ($row['buyer_must_act'] && ($is_orders || $is_adm) && $col['id'] == 1);

                        // Výpočet nádechu barvy pro záhlaví informačního okna
                        list($r, $g, $b) = sscanf($row['color_bg'], "#%02x%02x%02x");
                        $header_bg = "rgba($r, $g, $b, 0.12)";
                        ?>
                        <div class="req-card <?= $is_urgent ? 'req-card-urgent' : '' ?>"
                             style="background:#fff; margin-bottom:15px; border-radius:8px; border:1px solid #ddd; border-top: 6px solid <?= $row['color_bg'] ?>; box-shadow: 0 2px 6px rgba(0,0,0,0.05); overflow:hidden;">

                            <div style="background: <?= $header_bg ?>; padding: 12px 10px; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <div style="display:flex; justify-content:space-between; align-items: flex-start;">
                                    <strong style="font-size:14px; color:#222; text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars($row['surovina_nazev']) ?></strong>
                                    <span class="text-muted" style="font-size:10px; font-weight:bold;">#<?= $row['id'] ?></span>
                                </div>
                                <?php renderBadges($row); ?>
                            </div>

                            <div style="padding:10px;">
                                <?php if ($is_urgent): ?>
                                    <?php foreach ($all_offers_for_this as $p) renderOfferRow($p, $is_adm, $is_orders, $is_vyvoj, $is_quality, $col['id'], $row['color_bg']); ?>
                                    <button class="btn btn-sm btn-block btn-warning btn-add-offer" data-id="<?= $row['id'] ?>" style="font-weight:bold; margin-top: 5px; border: 2px dashed #f0ad4e; background: #fffcf5; color: #856404;">
                                        <i class="glyphicon glyphicon-search"></i> DOHLEDAT DODAVATELE
                                    </button>
                                <?php elseif (empty($all_offers_for_this)): ?>
                                    <div class="text-muted small text-center" style="padding:15px; background: #f9f9f9; border-radius: 4px; border: 1px dashed #ccc;">Čeká se na vložení nabídky...</div>
                                <?php else: ?>
                                    <?php foreach ($all_offers_for_this as $p) renderOfferRow($p, $is_adm, $is_orders, $is_vyvoj, $is_quality, $col['id'], $row['color_bg']); ?>
                                    <?php if (($is_orders || $is_adm) && $col['id'] == 1): ?>
                                        <button class="btn btn-xs btn-link btn-add-offer" data-id="<?= $row['id'] ?>" style="font-size:11px; padding:8px 0; text-decoration:none; color: #337ab7;"><i class="glyphicon glyphicon-plus-sign"></i> PŘIDAT DALŠÍ NABÍDKU</button>
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

<script>
    window.manualRefreshHandling = true;
    var localLastChange = <?= $last_change_time ?>;
    var showRejected = false;
    var cur = { id: 0, st: 0, hasLab: false };

    function applyRejectedVisibility() {
        if(showRejected) {
            $('.offer-rejected').show();
            $('#btnToggleRejected').html('<i class="glyphicon glyphicon-eye-close"></i> Skrýt KO').addClass('btn-danger');
        } else {
            $('.offer-rejected').hide();
            $('#btnToggleRejected').html('<i class="glyphicon glyphicon-eye-open"></i> Zobrazit KO').removeClass('btn-danger');
        }
    }

    function safeReload() {
        $('#board-container').load(window.location.href + ' #board-container > *', function() {
            applyRejectedVisibility();
            if (typeof $.fn.select2 !== 'undefined') {
                $('.select2-dod').select2({ dropdownParent: $('#mNN'), tags: true });
            }
        });
    }

    $(document).on('click', '#btnToggleRejected', function() { showRejected = !showRejected; applyRejectedVisibility(); });

    setInterval(function() {
        $.get('includes/check_changes.php', function(s) { if (s > localLastChange) { localLastChange = s; safeReload(); } });
    }, 2000);

    $(document).ready(function() {
        if (typeof $.fn.select2 !== 'undefined') {
            $('.select2-dod').select2({ dropdownParent: $('#mNN'), tags: true });
        }

        // --- PŘÍDÁNÍ NOVÉ NABÍDKY ---
        $(document).on('click', '.btn-add-offer', function() {
            $('#mNNId').val($(this).data('id'));
            $('#mNNCena').val('');
            $('#mNNDod').val('').trigger('change');
            $('#mNN').modal('show');
        });

        $('#mNNSave').on('click', function() {
            var d = $('#mNNDod').val(), c = $('#mNNCena').val(), idp = $('#mNNId').val();
            if(!d || !c) { alert("Vyplňte dodavatele a cenu."); return; }
            $.post('includes/ajax_add_offer.php', { id_pozadavek: idp, dodavatel_raw: d, cena: c }, function() {
                $('#mNN').modal('hide'); safeReload();
            });
        });

        // --- SPECIÁLNÍ PROMPT PRO MNOŽSTVÍ (CENA OK) ⚖️ ---
        $(document).on('click', '.btn-prompt-qty-note', function() {
            var b = $(this);
            $('#mQNId').val(b.data('id'));
            $('#mQNStatus').val(b.data('status'));
            $('#mQNQty').val('');
            $('#mQNNote').val(b.data('note') || '');
            $('#mQtyNote').modal('show');
        });

        $('#mQNSave').on('click', function() {
            var qty = $('#mQNQty').val().trim();
            if(!qty) { alert("Zadejte prosím požadované množství."); return; }
            var btn = $(this);
            btn.prop('disabled', true).text('Ukládám...');
            $.post('includes/update_status_nabidka.php', {
                id: $('#mQNId').val(),
                status: $('#mQNStatus').val(),
                qty: qty,
                poznamka: $('#mQNNote').val()
            }, function() {
                $('#mQtyNote').modal('hide');
                btn.prop('disabled', false).text('Potvrdit schválení');
                safeReload();
            });
        });

        // --- PŘÍMÉ AKCE (CENA OK / OBJEDNÁNO / DORAZILO / DOK. OK / TEST OK) ---
        $(document).on('click', '.btn-wf-direct, .btn-wf-check', function(e) {
            e.preventDefault();
            var bid = $(this).data('id');
            var bst = $(this).data('status');
            var btn = $(this);

            var msg = 'Aktualizováno.';
            if (bst == 3) msg = 'Cena schválena.';
            else if (bst == 8) msg = 'Dokumentace schválena.';
            else if (bst == 11) msg = 'Vzorek byl fyzicky objednán u dodavatele.';
            else if (bst == 10) msg = 'Vzorek dorazil do Lifefoodu.';
            else if (bst == 6) msg = 'Technologický test úspěšně dokončen. Surovina schválena.';

            btn.prop('disabled', true).html('<i class="glyphicon glyphicon-refresh spinning"></i>');
            $.post('includes/update_status_nabidka.php', {
                id: bid, status: bst, poznamka: msg
            }, function() { safeReload(); });
        });

        $(document).on('click', '.btn-prompt-reason', function() {
            var b = $(this);
            $('#mReasonId').val(b.data('id'));
            $('#mReasonStatus').val(b.data('status'));
            $('#mReasonText').val('');
            if(b.data('status') == 9) $('#mReason .modal-header').css('background', '#f0ad4e');
            else $('#mReason .modal-header').css('background', '#d9534f');
            $('#mReason').modal('show');
        });

        $('#mReasonSave').on('click', function() {
            var txt = $('#mReasonText').val().trim();
            if(!txt) { alert("Zadejte prosím důvod."); return; }
            var btn = $(this);
            btn.prop('disabled', true).text('Ukládám...');
            $.post('includes/update_status_nabidka.php', {
                id: $('#mReasonId').val(), status: $('#mReasonStatus').val(), poznamka: txt
            }, function() {
                $('#mReason').modal('hide'); btn.prop('disabled', false).text('Potvrdit akci'); safeReload();
            });
        });

        // --- MODÁL SPRÁVY (SOUBORY / ŠARŽE) ⚙️ ---
        $(document).on('click', '.btn-toggle-upload', function() {
            $('#upload' + $(this).data('target')).slideToggle();
        });

        $(document).on('change', '#mWFFileLab', function() {
            if (this.files.length > 0) $('#mWFSarzeBox').slideDown();
            else if (!cur.hasLab) $('#mWFSarzeBox').slideUp();
        });

        $(document).on('click', '.btn-wf', function() {
            var b = $(this); cur.id = b.data('id'); cur.st = b.data('status');
            $('#mWFNote').val(b.data('note') || ''); $('#mWFSarze').val(b.data('sarze') || '');
            $('#mWFQty, #mWFFileSpec, #mWFFileLab, #mWFFileOther').val('');
            $('#mWFFileStatus').html('');
            $('#btnDoUpload, #uploadTDS, #uploadCOA, #uploadOther').hide();

            $('#mWFQtyBox').toggle(b.data('need-qty') == 1);
            $('#mWFItemCodeBox').toggle(b.data('final') == 1);

            var fStr = b.data('files');
            cur.hasLab = (fStr && fStr.toString().indexOf('~lab') !== -1);

            var h = { TDS: '', COA: '', Other: '' };
            if (fStr && fStr.toString().length > 0) {
                fStr.toString().split('^').forEach(function(f) {
                    if(!f) return;
                    var p = f.split('~');
                    var html = '<div style="display:flex; justify-content:space-between; margin-bottom:2px; font-size:11px; background:#f9f9f9; padding:2px 5px; border-radius:3px;">' +
                        '<span><i class="glyphicon glyphicon-file"></i> ' + p[0] + '</span>' +
                        '<i class="glyphicon glyphicon-remove text-danger btn-delete-file" style="cursor:pointer;" data-id="'+cur.id+'" data-file="'+f+'"></i></div>';
                    if (p[1] === 'spec') h.TDS += html;
                    else if (p[1] === 'lab') h.COA += html;
                    else h.Other += html;
                });
            }
            $('#listTDS').html(h.TDS || '<em class="text-muted small">Žádný</em>');
            $('#listCOA').html(h.COA || '<em class="text-muted small">Žádný</em>');
            $('#listOther').html(h.Other || '<em class="text-muted small">Žádný</em>');

            if (cur.hasLab || b.data('sarze')) $('#mWFSarzeBox').show(); else $('#mWFSarzeBox').hide();
            $('#mWF').modal('show');
        });

        function executeUpload(callback) {
            var fd = new FormData();
            fd.append('id_nabidka', cur.id);
            var hasFiles = false;
            var iS = $('#mWFFileSpec')[0]; for(var i=0; i<iS.files.length; i++) { fd.append('files_spec[]', iS.files[i]); hasFiles=true; }
            var iL = $('#mWFFileLab')[0]; for(var i=0; i<iL.files.length; i++) { fd.append('files_lab[]', iL.files[i]); hasFiles=true; }
            var iO = $('#mWFFileOther')[0]; for(var i=0; i<iO.files.length; i++) { fd.append('files_other[]', iO.files[i]); hasFiles=true; }

            if(!hasFiles) { if(callback) callback(); return; }

            $('#mWFFileStatus').html('<span class="text-info"><i class="glyphicon glyphicon-refresh spinning"></i> Nahrávám soubory...</span>');
            $.ajax({
                url: 'includes/upload_to_nextcloud.php', type: 'POST', data: fd, contentType: false, processData: false,
                success: function() {
                    if(callback) callback();
                    else $('#mWFFileStatus').html('<b class="text-success">Nahráno.</b>');
                },
                error: function() { alert("Chyba při nahrávání!"); $('#mWFFileStatus').html(''); }
            });
        }

        $('#btnDoUpload').on('click', function() { executeUpload(); });

        $('#mWFSave').on('click', function() {
            var btn = $(this);
            var sarzeVal = $('#mWFSarze').val();

            if (($('#mWFFileLab')[0].files.length > 0 || cur.hasLab) && !sarzeVal) {
                alert("ŠARŽE je povinná u položek s laboratorní analýzou!");
                $('#mWFSarze').focus(); return;
            }

            btn.prop('disabled', true).text('Ukládám...');

            executeUpload(function() {
                $.post('includes/update_status_nabidka.php', {
                    id: cur.id, status: cur.st, poznamka: $('#mWFNote').val(), qty: $('#mWFQty').val(), sarze: sarzeVal
                }, function() {
                    $('#mWF').modal('hide');
                    btn.prop('disabled', false).text('Potvrdit');
                    safeReload();
                });
            });
        });

        $(document).on('click', '.btn-delete-file', function() {
            if(!confirm("Smazat soubor?")) return;
            $.post('includes/delete_file.php', { id: $(this).data('id'), file: $(this).data('file') }, function() {
                $('#mWF').modal('hide'); safeReload();
            });
        });

        applyRejectedVisibility();
    });
</script>