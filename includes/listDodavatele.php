<?php
include_once("db_connect.php");
if (!$is_adm && !$is_orders) die("Nepovolený přístup.");

// SQL dotaz s přidaným zjištěním počtu vzorků
$sql = "SELECT d.*, 
        (SELECT COUNT(id) FROM pozadavky_nabidky pn WHERE pn.id_dodavatel = d.id) as pocet_vzorku
        FROM dodavatele d 
        ORDER BY d.nazev ASC";
$res = mysqli_query($conn, $sql);
?>

<div class="panel panel-primary shadow dodavatele-panel">
    <div class="panel-heading dodavatele-heading">
        <h3 class="panel-title"><i class="fa fa-truck"></i> Správa dodavatelů</h3>
        <button class="btn btn-sm btn-success btn-add-dodavatel">
            <i class="fa fa-plus-circle"></i> Přidat nového dodavatele
        </button>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sjednocena dodavatele-table">
                <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th>Název společnosti</th>
                    <th>Kontakt</th>
                    <th>Email / Telefon</th>
                    <th class="col-vzorky text-center">Poskytnuté vzorky</th>
                    <th class="col-akce text-center">Akce</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['nazev']) ?></strong></td>
                        <td><?= htmlspecialchars($row['kontaktni_osoba'] ?? '-') ?></td>
                        <td class="dodavatel-kontakt">
                            <i class="fa fa-envelope text-muted"></i> <?= htmlspecialchars($row['email'] ?? '-') ?><br>
                            <i class="fa fa-phone text-muted"></i> <?= htmlspecialchars($row['telefon'] ?? '-') ?>
                        </td>
                        <td class="text-center">
                            <?php if ($row['pocet_vzorku'] > 0): ?>
                                <button class="btn btn-sm btn-info btn-show-vzorky" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['nazev'], ENT_QUOTES) ?>" title="Zobrazit historii vzorků">
                                    <i class="fa fa-archive"></i> <?= $row['pocet_vzorku'] ?>
                                </button>
                            <?php else: ?>
                                <span class="badge bg-light text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center nowrap">
                            <button class="btn btn-sm btn-warning btn-edit-dodavatel" data-id="<?= $row['id'] ?>" title="Upravit dodavatele">
                                <i class="fa fa-pencil"></i> Upravit
                            </button>

                            <button class="btn btn-sm btn-danger btn-delete-ajax" data-id="<?= $row['id'] ?>" data-table="dodavatele" title="Smazat dodavatele">
                                <i class="fa fa-trash">X</i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="mVzorkyDodavatele" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <button type="button" class="close btn-close text-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="float: right;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h5 class="modal-title"><i class="fa fa-archive"></i> Historie vzorků: <span id="modalDodavatelName" class="fw-bold"></span></h5>
            </div>
            <div class="modal-body" id="modalVzorkyBody">
                <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Načítám data...</div>
            </div>
        </div>
    </div>
</div>

<script>
    // Správa dodavatelů - přidání/editace
    $('.btn-add-dodavatel').off('click').click(function() {
        $('#modal-loader').show();
        $('#modal-dynamic-content').html('');
        $('#modal-dynamic-content').load('includes/formDodavatel.php', function() {
            $('#modal-loader').hide();
            $('#remoteModal').modal('show');
        });
    });

    $(document).off('click', '.btn-edit-dodavatel').on('click', '.btn-edit-dodavatel', function() {
        var id = $(this).data('id');
        $('#modal-loader').show();
        $('#modal-dynamic-content').html('');
        $('#modal-dynamic-content').load('includes/formDodavatel.php?id=' + id, function() {
            $('#modal-loader').hide();
            $('#remoteModal').modal('show');
        });
    });

    // Zobrazení historie vzorků
    $(document).off('click', '.btn-show-vzorky').on('click', '.btn-show-vzorky', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');

        $('#modalDodavatelName').text(name);
        $('#modalVzorkyBody').html('<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Načítám data...</div>');
        $('#mVzorkyDodavatele').modal('show');

        // Zavoláme nový AJAX skript pro data
        $.post('includes/ajax_get_dodavatel_vzorky.php', { id: id }, function(response) {
            $('#modalVzorkyBody').html(response);
        }).fail(function() {
            $('#modalVzorkyBody').html('<div class="alert alert-danger">Chyba při načítání dat. Zkuste to prosím znovu.</div>');
        });
    });
</script>