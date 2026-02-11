<?php
// Načtení dat přímo v souboru, aby proměnná $result vždy existovala
include_once("includes/db_connect.php");

$sql = "SELECT p.*, 
               z.nazev AS zakaznik_nazev, 
               s.nazev AS surovina_nazev 
        FROM pozadavky p 
        LEFT JOIN zakaznik z ON p.id_zakaznik = z.id 
        LEFT JOIN suroviny s ON p.id_surovina = s.id 
        ORDER BY p.datumPozadavek DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Chyba v SQL dotazu: " . mysqli_error($conn));
}

// Kontrola admina pro zobrazení tlačítek
$is_admin_session = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3 text-dark">
        <h2 class="mb-0">Správa požadavků</h2>
        <a href="index.php?add_Pozadavek=1" class="btn btn-primary">
            <i class="fa fa-plus"></i> Nový požadavek
        </a>
    </div>

    <div class="table-responsive shadow-sm">
        <table id="editableTable" data-table="pozadavky" class="table table-bordered table-striped align-middle bg-white table-sjednocena">
            <thead class="table-dark text-nowrap">
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Datum</th>
                <th>Surovina</th>
                <th>Vlastnosti</th>
                <th>Zákazník</th>
                <th>Množství</th>
                <th class="text-center" style="width: 120px;">Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)):
                $datumRaw = $row['datumPozadavek'];
                $is_today = (date('Y-m-d') === date('Y-m-d', strtotime($datumRaw)));
                // Admin může vše, uživatel jen dnešní záznamy
                $can_modify = ($is_admin_session || $is_today);
                ?>
                <tr>
                    <td class="text-center text-muted small"><?= $row['id'] ?></td>
                    <td data-sort="<?= $datumRaw ?>">
                        <?= (!empty($datumRaw)) ? date('d.m.Y', strtotime($datumRaw)) : '-' ?>
                    </td>
                    <td><strong><?= htmlspecialchars($row['surovina_nazev'] ?? 'Neznámá surovina') ?></strong></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if(!empty($row['bio'])): ?><span class="badge bg-success">BIO</span><?php endif; ?>
                            <?php if(!empty($row['vegan'])): ?><span class="badge bg-info">VGN</span><?php endif; ?>
                            <?php if(!empty($row['bezlepek'])): ?><span class="badge bg-warning text-dark">BL</span><?php endif; ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($row['zakaznik_nazev'] ?? 'Neznámý') ?></td>
                    <td data-sort="<?= $row['Mnozstvi'] ?>">
                        <?= number_format((float)($row['Mnozstvi'] ?? 0), 2, ',', ' ') ?> <?= htmlspecialchars($row['mj'] ?? '') ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                          <!--  <a href="index.php?add_Pozadavek=1&edit_id=<?= $row['id'] ?>"
                               class="btn btn-success btn-sm" title="Editovat">
                                <i class="fa fa-save"> Uložit</i>
                            </a>-->

                            <?php if ($can_modify): ?>
                                <a href="javascript:void(0);"
                                   class="btn btn-danger btn-sm btn-delete-ajax"
                                   data-id="<?= $row['id'] ?>"
                                   data-table="pozadavky"
                                   title="Smazat">
                                    <i class="fa fa-trash">X</i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled title="Historii maže jen admin">
                                    <i class="fa fa-lock"> Zamčeno</i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>