<?php
// Načtení surovin s počtem jejich použití
include_once("includes/db_connect.php");

$sql = "SELECT s.*, 
               (SELECT COUNT(p.id) FROM pozadavky p WHERE p.id_surovina = s.id) as pouzito_krat
        FROM suroviny s 
        ORDER BY s.nazev ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Chyba v SQL dotazu: " . mysqli_error($conn));
}
?>

<div class="container-fluid mt-4" style="padding-bottom: 80px;">
    <div class="d-flex justify-content-between align-items-center mb-3 text-dark">
        <h2 class="mb-0"><i class="fa fa-leaf text-success me-2"></i>Knihovna surovin</h2>
        <span class="badge bg-secondary">Celkem: <?= mysqli_num_rows($result) ?></span>
    </div>

    <div class="table-responsive shadow-sm border rounded">
        <table id="tableSuroviny" class="table table-hover align-middle bg-white table-sjednocena mb-0">
            <thead class="table-dark">
            <tr>
                <th style="width: 60px;" class="text-center">ID</th>
                <th style="width: 35%;">Název suroviny (CZ)</th>
                <th style="width: 35%;">Anglický název (EN)</th>
                <th class="text-center">Vlastnosti</th>
                <th class="text-center">Počet poptávek</th>
            </tr>
            <tr class="search-row" style="background-color: #f8f9fa;">
                <th><input type="text" class="form-control form-control-sm col-search" placeholder="ID..."></th>
                <th><input type="text" class="form-control form-control-sm col-search" placeholder="Hledat CZ..."></th>
                <th><input type="text" class="form-control form-control-sm col-search" placeholder="Hledat EN..."></th>
                <th><input type="text" class="form-control form-control-sm col-search" placeholder="Vlastnost..."></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td class="text-center text-muted"><?= $row['id'] ?></td>
                    <td>
                        <input type="text" class="form-control form-control-sm inline-input"
                               data-id="<?= $row['id'] ?>" data-col="cz"
                               data-orig="<?= htmlspecialchars($row['nazev'], ENT_QUOTES) ?>"
                               value="<?= htmlspecialchars($row['nazev'], ENT_QUOTES) ?>"
                               style="font-weight: bold; border: 1px solid transparent; background: transparent; transition: all 0.2s;">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm inline-input"
                               data-id="<?= $row['id'] ?>" data-col="en"
                               data-orig="<?= htmlspecialchars($row['nazev_en'] ?? '', ENT_QUOTES) ?>"
                               value="<?= htmlspecialchars($row['nazev_en'] ?? '', ENT_QUOTES) ?>"
                               placeholder="Zadejte EN překlad..."
                               style="color: #0d6efd; border: 1px solid transparent; background: transparent; transition: all 0.2s;">
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <?php if(!empty($row['bio'])): ?><span class="badge bg-success">BIO</span><?php endif; ?>
                            <?php if(!empty($row['vegan'])): ?><span class="badge bg-info">VGN</span><?php endif; ?>
                            <?php if(!empty($row['bezlepek'])): ?><span class="badge bg-warning text-dark">BL</span><?php endif; ?>
                            <?php if(empty($row['bio']) && empty($row['vegan']) && empty($row['bezlepek'])): ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border"><?= $row['pouzito_krat'] ?>×</span>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="floatingSaveBox" style="display: none; position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 9999; background: white; padding: 15px 30px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); border: 2px solid #f0ad4e;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <div>
            <strong style="color: #d9534f; font-size: 16px;"><i class="fa fa-exclamation-triangle"></i> Máte neuložené změny!</strong><br>
            <span class="text-muted small">Upravených surovin: <b id="modifiedCount">0</b></span>
        </div>
        <button id="btnBatchSave" class="btn btn-warning" style="font-weight: bold; font-size: 16px; padding: 10px 20px;">
            <i class="fa fa-save"></i> ULOŽIT VŠE
        </button>
    </div>
</div>

<script>
    $(document).ready(function() {
        var tableID = '#tableSuroviny';

        var tableSuroviny = $(tableID).DataTable({
            "retrieve": true,
            "paging": false,
            "autoWidth": false,
            "order": [[1, "asc"]],
            "dom": 't'
        });

        $(tableID + ' .col-search').on('keyup change', function() {
            var index = $(this).closest('th').index();
            tableSuroviny.column(index).search(this.value).draw();
        });

        $(tableID + ' .col-search').on('keydown', function(e) {
            if (e.keyCode == 13) { e.preventDefault(); return false; }
        });

        // EFEKT NAJETÍ MYŠÍ PRO POLÍČKA
        $('.inline-input').on('mouseenter', function() {
            if (!$(this).hasClass('is-modified')) { $(this).css({'border': '1px solid #ced4da', 'background': '#fff'}); }
        }).on('mouseleave', function() {
            if (!$(this).hasClass('is-modified') && !$(this).is(':focus')) { $(this).css({'border': '1px solid transparent', 'background': 'transparent'}); }
        }).on('focus', function() {
            $(this).css({'border': '1px solid #86b7fe', 'background': '#fff'});
        }).on('blur', function() {
            if (!$(this).hasClass('is-modified')) { $(this).css({'border': '1px solid transparent', 'background': 'transparent'}); }
        });

        // SLEDOVÁNÍ ZMĚN (INLINE EDIT)
        var modifiedData = {};

        $(document).on('input', '.inline-input', function() {
            var input = $(this);
            var id = input.data('id');
            var col = input.data('col');
            var orig = input.data('orig').toString();
            var val = input.val().toString();

            if (orig !== val) {
                input.addClass('is-modified').css({'border': '2px solid #f0ad4e', 'background': '#fffcf5'});
                if (!modifiedData[id]) modifiedData[id] = {};
                modifiedData[id][col] = val;
            } else {
                input.removeClass('is-modified').css({'border': '1px solid #86b7fe', 'background': '#fff'});
                if (modifiedData[id]) {
                    delete modifiedData[id][col];
                    if (Object.keys(modifiedData[id]).length === 0) delete modifiedData[id];
                }
            }

            var modCount = Object.keys(modifiedData).length;
            if (modCount > 0) {
                $('#modifiedCount').text(modCount);
                $('#floatingSaveBox').fadeIn(200);
            } else {
                $('#floatingSaveBox').fadeOut(200);
            }
        });

        // ODESLÁNÍ VŠECH ZMĚN NA SERVER
        $('#btnBatchSave').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-refresh fa-spin"></i> Ukládám...');

            $.post('includes/ajax_update_surovina_batch.php', { data: modifiedData }, function(r) {
                if (r.trim() === 'OK') {
                    if (typeof safeReload === "function") safeReload(); else window.location.reload();
                } else {
                    alert("Kritická chyba při ukládání: " + r);
                    btn.prop('disabled', false).html('<i class="fa fa-save"></i> ULOŽIT VŠE');
                }
            });
        });
    });
</script>