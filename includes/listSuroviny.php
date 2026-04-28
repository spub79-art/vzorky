<?php
include_once("db_connect.php");
if (!$is_adm && !$is_orders && !$is_dev) die("Nepovolený přístup.");

// SQL dotaz: Zjišťujeme počty požadavků (pro zámek) a nabídek (pro historii)
$sql = "SELECT s.*, 
        (SELECT COUNT(p.id) FROM pozadavky p WHERE p.id_surovina = s.id) as pocet_pozadavku,
        (SELECT COUNT(pn.id) 
         FROM pozadavky_nabidky pn 
         JOIN pozadavky p ON pn.id_pozadavek = p.id 
         WHERE p.id_surovina = s.id) as pocet_pokusu
        FROM suroviny s 
        ORDER BY s.nazev ASC";
$res = mysqli_query($conn, $sql);

if (!$res) {
    die("<div class='alert alert-danger m-3'><strong>Chyba SQL:</strong> " . mysqli_error($conn) . "</div>");
}
?>

<div class="panel panel-primary shadow suroviny-panel">
    <div class="panel-heading suroviny-heading">
        <h3 class="panel-title"><i class="fa fa-flask"></i> Knihovna surovin a překlady</h3>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-suroviny align-middle">
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
                <?php while ($row = mysqli_fetch_assoc($res)):
                    $is_locked = ($row['pocet_pozadavku'] > 0);
                    ?>
                    <tr data-id="<?= $row['id'] ?>">
                        <td class="text-muted" style="vertical-align: middle;"><?= $row['id'] ?></td>

                        <td style="vertical-align: middle;">
                            <?php if ($is_adm): ?>
                                <input type="text" class="form-control input-sm edit-nazev" value="<?= htmlspecialchars($row['nazev']) ?>">
                            <?php else: ?>
                                <input type="text" class="form-control input-sm edit-nazev bg-light border-0 shadow-none" value="<?= htmlspecialchars($row['nazev']) ?>" readonly title="Název může měnit pouze administrátor">
                            <?php endif; ?>
                        </td>

                        <td style="vertical-align: middle;">
                            <input type="text" class="form-control input-sm edit-nazev-en"
                                   placeholder="English name..."
                                   value="<?= htmlspecialchars($row['nazev_en'] ?? '') ?>">
                        </td>

                        <td style="vertical-align: middle;">
                            <div class="erp-wrapper" style="display: flex; align-items: center; gap: 5px;">
                                <input type="text" class="form-control input-sm edit-skupzbo erp-input" style="width: 70px;" placeholder="Skup" value="<?= htmlspecialchars($row['skupzbo'] ?? '') ?>">
                                <input type="text" class="form-control input-sm edit-regcis erp-input" style="width: 70px;" placeholder="Reg" value="<?= htmlspecialchars($row['regcis'] ?? '') ?>">

                                <?php if (!empty($row['skupzbo']) && !empty($row['regcis'])): ?>
                                    <span class="label label-success" title="Zavedeno v IS"><i class="fa fa-check"></i></span>
                                <?php else: ?>
                                    <span class="label label-warning" title="Pouze ve vývoji"><i class="fa fa-flask"></i></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="text-center nowrap" style="vertical-align: middle;">
                            <?php if ($row['pocet_pokusu'] > 0): ?>
                                <button class="btn btn-sm btn-info btn-show-historie-sur"
                                        style="margin-right: 4px;"
                                        data-id="<?= $row['id'] ?>"
                                        data-name="<?= htmlspecialchars($row['nazev'], ENT_QUOTES) ?>">
                                    <i class="fa fa-history"></i> (<?= $row['pocet_pokusu'] ?>)
                                </button>
                            <?php endif; ?>

                            <?php if (!$is_locked): ?>
                                <button class="btn btn-sm btn-danger btn-delete-ajax"
                                        data-id="<?= $row['id'] ?>"
                                        data-table="suroviny"
                                        data-confirm="Opravdu smazat surovinu <?= htmlspecialchars($row['nazev']) ?>?">
                                    <i class="fa fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-default" disabled title="Nelze smazat - je použita v požadavcích (<?= $row['pocet_pozadavku'] ?>x)">
                                    <i class="fa fa-lock text-muted"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div id="saveSurovinyContainer" class="text-center" style="margin-top: 25px; padding: 15px; border-top: 1px solid #eee; display: none;">
            <button class="btn btn-lg btn-warning btn-save-all-translations" style="box-shadow: 0 4px 15px rgba(240, 173, 78, 0.4); font-weight: bold; padding: 12px 50px;">
                <i class="fa fa-save"></i> ULOŽIT VŠECHNY ZMĚNY
            </button>
        </div>
    </div>
</div>