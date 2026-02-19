<?php
if (empty($_SESSION['username'])) exit;
?>

<div class="panel panel-info">
    <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="panel-title"><b><i class="glyphicon glyphicon-th-list"></i> Správa produktů (Katalog Lifefood)</b></h3>
        <button class="btn btn-xs btn-success" data-toggle="modal" data-target="#mProd"><i class="glyphicon glyphicon-plus"></i> Nový produkt</button>
    </div>
    <div class="panel-body">
        <div class="well well-sm">
            <input type="text" id="prodSearch" class="form-control" placeholder="Hledat podle názvu nebo kódu IS...">
        </div>
        <table class="table table-striped table-hover" id="tableProds">
            <thead>
            <tr>
                <th>Kód (IS)</th>
                <th>Název produktu</th>
                <th>Počet surovin</th>
                <th>Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT p.*, (SELECT COUNT(*) FROM produkty_suroviny WHERE id_produkt = p.id) as pocet_sur 
                        FROM produkty p ORDER BY p.nazev ASC";
            $res = mysqli_query($conn, $sql);
            while($row = mysqli_fetch_assoc($res)): ?>
                <tr class="prod-row">
                    <td><span class="label label-primary"><?= $row['skupzbo'] ?>-<?= $row['regcis'] ?></span></td>
                    <td><strong><?= htmlspecialchars($row['nazev']) ?></strong></td>
                    <td><span class="badge"><?= $row['pocet_sur'] ?></span></td>
                    <td>
                        <button class="btn btn-xs btn-default btn-edit-prod"
                                data-id="<?= $row['id'] ?>"
                                data-name="<?= htmlspecialchars($row['nazev']) ?>"
                                data-skup="<?= $row['skupzbo'] ?>"
                                data-reg="<?= $row['regcis'] ?>"><i class="glyphicon glyphicon-pencil"></i></button>
                        <button class="btn btn-xs btn-info btn-link-sur"
                                data-id="<?= $row['id'] ?>"
                                data-name="<?= htmlspecialchars($row['nazev']) ?>"><i class="glyphicon glyphicon-link"></i> Suroviny</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="mProd" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background:#31708f; color:#fff;"><h4>Karta produktu</h4></div>
            <div class="modal-body">
                <input type="hidden" id="mProdId">
                <div class="form-group"><label>Název výrobku:</label><input type="text" id="mProdNazev" class="form-control"></div>
                <div class="row">
                    <div class="col-xs-6"><div class="form-group"><label>Skupina:</label><input type="text" id="mProdSkup" class="form-control"></div></div>
                    <div class="col-xs-6"><div class="form-group"><label>Reg. č.:</label><input type="text" id="mProdReg" class="form-control"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-block" id="btnProdSave">Uložit produkt</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mLinkSur" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#5bc0de; color:#fff;"><h4 id="linkSurTitle">Složení produktu</h4></div>
            <div class="modal-body">
                <input type="hidden" id="linkProdId">
                <div class="well well-sm">
                    <label>Přidat schválenou surovinu:</label>
                    <div class="input-group">
                        <select id="selectSur" class="form-control">
                            <?php
                            $sur_res = mysqli_query($conn, "SELECT id, nazev, skupzbo, regcis FROM suroviny WHERE skupzbo IS NOT NULL ORDER BY nazev ASC");
                            while($s = mysqli_fetch_assoc($sur_res)) echo "<option value='{$s['id']}'>{$s['nazev']} ({$s['skupzbo']}-{$s['regcis']})</option>";
                            ?>
                        </select>
                        <span class="input-group-btn"><button class="btn btn-success" id="btnAddSurToProd">Přidat</button></span>
                    </div>
                </div>
                <div id="currentSurList"></div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function(){
        $("#prodSearch").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#tableProds tbody tr").filter(function() { $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1) });
        });

        $('.btn-edit-prod').on('click', function(){
            $('#mProdId').val($(this).data('id'));
            $('#mProdNazev').val($(this).data('name'));
            $('#mProdSkup').val($(this).data('skup'));
            $('#mProdReg').val($(this).data('reg'));
            $('#mProd').modal('show');
        });

        $('#btnProdSave').on('click', function(){
            $.post('includes/ajax_product_actions.php', {
                action: 'save_prod',
                id: $('#mProdId').val(),
                nazev: $('#mProdNazev').val(),
                skupzbo: $('#mProdSkup').val(),
                regcis: $('#mProdReg').val()
            }, function(r){ if(r.trim()==="OK") location.reload(); else alert(r); });
        });

        $('.btn-link-sur').on('click', function(){
            var pid = $(this).data('id');
            $('#linkProdId').val(pid);
            $('#linkSurTitle').text('Složení produktu: ' + $(this).data('name'));
            loadProductMaterials(pid);
            $('#mLinkSur').modal('show');
        });

        $('#btnAddSurToProd').on('click', function(){
            $.post('includes/ajax_product_actions.php', {
                action: 'link_material',
                id_produkt: $('#linkProdId').val(),
                id_surovina: $('#selectSur').val()
            }, function(r){ loadProductMaterials($('#linkProdId').val()); });
        });
    });

    function loadProductMaterials(pid) {
        $.get('includes/ajax_product_actions.php', { action: 'get_materials', id: pid }, function(data){
            $('#currentSurList').html(data);
        });
    }

    $(document).on('click', '.btn-unlink-sur', function(){
        if(!confirm('Odebrat surovinu ze složení?')) return;
        $.post('includes/ajax_product_actions.php', { action: 'unlink_material', id: $(this).data('id') }, function(){
            loadProductMaterials($('#linkProdId').val());
        });
    });
</script>