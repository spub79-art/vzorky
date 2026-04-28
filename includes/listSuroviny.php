<?php
include_once("db_connect.php");
if (!$is_adm && !$is_orders && !$is_dev) die("Nepovolený přístup.");

// SQL dotaz: Vytáhneme suroviny a spočítáme pokusy
$sql = "SELECT s.*, 
        (SELECT COUNT(pn.id) 
         FROM pozadavky_nabidky pn 
         JOIN pozadavky p ON pn.id_pozadavek = p.id 
         WHERE p.id_surovina = s.id) as pocet_pokusu
        FROM suroviny s 
        ORDER BY s.nazev ASC";
$res = mysqli_query($conn, $sql);
?>

<div class="panel panel-primary shadow suroviny-panel">
    <div class="panel-heading suroviny-heading">
        <h3 class="panel-title"><i class="fa fa-flask"></i> Knihovna surovin a překlady</h3>
        <div class="panel-actions" style="float: right; margin-top: -22px;">
            <button class="btn btn-sm btn-success btn-save-all-translations">
                <i class="fa fa-save"></i> Uložit vše naráz
            </button>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-suroviny">
                <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-nazev">Název (CZ)</th>
                    <th class="col-preklad">Anglický překlad (pro generátor)</th>
                    <th class="col-is">Stav v IS (SkupZbo | RegCis)</th>
                    <th class="col-akce text-center">Historie a akce</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($res)) { ?>
                    <tr data-id="<?= $row['id'] ?>">
                        <td class="text-muted" style="vertical-align: middle;"><?= $row['id'] ?></td>
                        <td style="vertical-align: middle;">
                            <input type="text" class="form-control input-sm edit-nazev" value="<?= htmlspecialchars($row['nazev']) ?>">
                        </td>
                        <td style="vertical-align: middle;">
                            <input type="text" class="form-control input-sm edit-nazev-en" placeholder="English name..." value="<?= htmlspecialchars($row['nazev_en'] ?? '') ?>">
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="erp-wrapper">
                                <input type="text" class="form-control input-sm edit-skupzbo erp-input" placeholder="SkupZbo" value="<?= htmlspecialchars($row['skupzbo'] ?? '') ?>">
                                <input type="text" class="form-control input-sm edit-regcis erp-input" placeholder="RegCis" value="<?= htmlspecialchars($row['regcis'] ?? '') ?>">

                                <?php if (!empty($row['skupzbo']) && !empty($row['regcis'])) { ?>
                                    <span class="label label-success" title="Zavedeno v IS"><i class="fa fa-check"></i></span>
                                <?php } else { ?>
                                    <span class="label label-warning" title="Pouze ve vývoji"><i class="fa fa-flask"></i></span>
                                <?php } ?>
                            </div>
                        </td>
                        <td class="text-center nowrap" style="vertical-align: middle;">
                            <?php if ($row['pocet_pokusu'] > 0) { ?>
                                <button class="btn btn-sm btn-info btn-show-historie-sur" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['nazev'], ENT_QUOTES) ?>">
                                    <i class="fa fa-history"></i> (<?= $row['pocet_pokusu'] ?>)
                                </button>
                            <?php } ?>

                            <button class="btn btn-sm btn-danger btn-delete-ajax" data-id="<?= $row['id'] ?>" data-table="suroviny">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="mHistorieSuroviny" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><i class="fa fa-history"></i> Historie pokusů: <span id="modalSurName"></span></h4>
            </div>
            <div class="modal-body" id="modalSurBody">
            </div>
        </div>
    </div>
</div>
