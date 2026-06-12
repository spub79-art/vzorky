<?php
include_once("db_connect.php");
if (!isset($can_nakup)) {
    include_once("permissions.php");
    $can_nakup = loadSessionPermissions()['can_nakup'];
}
if (!$can_nakup) die("Nepovolený přístup.");

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
            <table class="table table-striped table-hover table-sjednocena dodavatele-table align-middle">
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
                        <td class="text-muted" style="vertical-align: middle;"><?= $row['id'] ?></td>
                        <td style="vertical-align: middle;"><strong><?= htmlspecialchars($row['nazev']) ?></strong></td>
                        <td style="vertical-align: middle;"><?= htmlspecialchars($row['kontaktni_osoba'] ?? '-') ?></td>
                        <td class="dodavatel-kontakt" style="vertical-align: middle;">
                            <i class="fa fa-envelope text-muted"></i> <?= htmlspecialchars($row['email'] ?? '-') ?><br>
                            <i class="fa fa-phone text-muted"></i> <?= htmlspecialchars($row['telefon'] ?? '-') ?>
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            <?php if ($row['pocet_vzorku'] > 0): ?>
                                <button class="btn btn-sm btn-info btn-show-vzorky" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['nazev'], ENT_QUOTES) ?>" title="Zobrazit historii vzorků">
                                    <i class="fa fa-archive"></i> <?= $row['pocet_vzorku'] ?>
                                </button>
                            <?php else: ?>
                                <span class="badge bg-light text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center nowrap" style="vertical-align: middle;">
                            <button class="btn btn-sm btn-warning btn-edit-dodavatel" data-id="<?= $row['id'] ?>" title="Upravit dodavatele">
                                <i class="fa fa-pencil"></i> Upravit
                            </button>

                            <?php if ($row['pocet_vzorku'] == 0): ?>
                                <button class="btn btn-sm btn-danger btn-delete-ajax"
                                        data-id="<?= $row['id'] ?>"
                                        data-table="dodavatele"
                                        data-confirm="Opravdu chcete smazat dodavatele <?= htmlspecialchars($row['nazev']) ?>?"
                                        title="Smazat dodavatele">
                                    <i class="fa fa-trash"></i> X
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-default" disabled title="Nelze smazat - dodavatel má v systému <?= $row['pocet_vzorku'] ?> nabídek/vzorků">
                                    <i class="fa fa-lock text-muted"></i> Zamčeno
                                </button>
                            <?php endif; ?>
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
<div class="modal fade" id="remoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div id="modal-loader" style="display:none; padding: 50px; text-align: center; color: #777;">
                <i class="glyphicon glyphicon-refresh spinning" style="font-size: 30px;"></i><br><br>Načítám formulář...
            </div>
            <div id="modal-dynamic-content"></div>
        </div>
    </div>
</div>