<?php
include_once("db_connect.php");
if (!$is_adm && !$is_orders) die("Nepovolený přístup.");

$res = mysqli_query($conn, "SELECT * FROM dodavatele ORDER BY nazev ASC");
?>

<div class="panel panel-primary shadow">
    <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="panel-title"><i class="fa fa-truck"></i> Správa dodavatelů</h3>
        <button class="btn btn-sm btn-success btn-add-dodavatel">
            <i class="fa fa-plus-circle"></i> Přidat nového dodavatele
        </button>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sjednocena" style="vertical-align: middle;">
                <thead>
                <tr style="background-color: #f9f9f9;">
                    <th style="width: 50px;">ID</th>
                    <th>Název společnosti</th>
                    <th>Kontakt</th>
                    <th>Email / Telefon</th>
                    <th style="width: 130px;" class="text-center">Akce</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td style="vertical-align: middle;"><?= $row['id'] ?></td>
                        <td style="vertical-align: middle;"><strong><?= htmlspecialchars($row['nazev']) ?></strong></td>
                        <td style="vertical-align: middle;"><?= htmlspecialchars($row['kontaktni_osoba'] ?? '-') ?></td>
                        <td style="vertical-align: middle;">
                            <small>
                                <i class="fa fa-envelope text-muted"></i> <?= htmlspecialchars($row['email'] ?? '-') ?><br>
                                <i class="fa fa-phone text-muted"></i> <?= htmlspecialchars($row['telefon'] ?? '-') ?>
                            </small>
                        </td>
                        <td class="text-center" style="vertical-align: middle; white-space: nowrap;">
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

<script>
    // Skript pro modály zůstává stejný, jen jsme změnili vzhled tlačítek
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
</script>