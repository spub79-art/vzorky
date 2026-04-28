<?php
include_once("includes/db_connect.php");
$current_page_url = "index.php?Zakaznik=1";

if (isset($_POST['save_customer'])) {
    $nazev = mysqli_real_escape_string($conn, $_POST['nazev_novy']);
    mysqli_query($conn, "INSERT INTO zakaznici (nazev) VALUES ('$nazev')");
    echo "<script>window.location.href='$current_page_url';</script>";
}

if (isset($_POST['update_zakaznik'])) {
    $id = (int)$_POST['id'];
    $nazev = mysqli_real_escape_string($conn, $_POST['nazev']);
    mysqli_query($conn, "UPDATE zakaznici SET nazev = '$nazev' WHERE id = $id");
    echo "<script>window.location.href='$current_page_url';</script>";
}

$query = "SELECT z.*, COUNT(pz.id_pozadavek) as pocet_pozadavku 
          FROM zakaznici z 
          LEFT JOIN pozadavky_zakaznici pz ON z.id = pz.id_zakaznik 
          GROUP BY z.id 
          ORDER BY z.id DESC";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("<div class='alert alert-danger m-3'><strong>Chyba SQL:</strong> " . mysqli_error($conn) . "</div>");
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-light p-3 rounded shadow-sm">
        <h2 class="mb-0"><i class="fa fa-users text-primary me-2"></i>Správa zákazníků</h2>
        <button type="button" class="btn btn-primary btn-lg shadow btn-toggle-add-customer">
            <i class="fa fa-plus-circle me-1"></i> Nový zákazník
        </button>
    </div>

    <div class="table-responsive shadow-sm border rounded">
        <table class="table table-hover align-middle bg-white mb-0">
            <thead class="table-dark">
            <tr>
                <th style="width: 80px;" class="text-center">ID</th>
                <th>Název zákazníka</th>
                <th class="text-center" style="width: 200px;">Akce</th>
            </tr>
            </thead>
            <tbody>
            <tr id="addRow" style="display: none; background-color: #f0fdf4;">
                <td class="text-center"><span class="badge bg-success">NEW</span></td>
                <form id="form_new" method="post" action="<?= $current_page_url ?>"></form>
                <td>
                    <input type="text" form="form_new" name="nazev_novy" class="form-control" required placeholder="Napište název zákazníka...">
                </td>
                <td class="text-center">
                    <div class="btn-group">
                        <button type="submit" form="form_new" name="save_customer" class="btn btn-success">
                            <i class="fa fa-save me-1"></i> Uložit
                        </button>
                        <button type="button" class="btn btn-secondary btn-toggle-add-customer">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </td>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($result)): $is_locked = ($row['pocet_pozadavku'] > 0); ?>
                <tr>
                    <td class="text-center text-muted"><?= $row['id'] ?></td>

                    <form id="form_edit_<?= $row['id'] ?>" method="post" action="<?= $current_page_url ?>">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    </form>

                    <td>
                        <input type="text" form="form_edit_<?= $row['id'] ?>" name="nazev"
                               class="form-control <?= $is_locked ? 'bg-light border-0 shadow-none' : '' ?>"
                               value="<?= htmlspecialchars($row['nazev']) ?>"
                               required <?= $is_locked ? 'readonly' : '' ?>>
                    </td>

                    <td class="text-center">
                        <div class="btn-group">
                            <?php if (!$is_locked): ?>
                                <button type="submit" form="form_edit_<?= $row['id'] ?>" name="update_zakaznik" class="btn btn-outline-success btn-sm">
                                    <i class="fa fa-save"></i> Uložit
                                </button>
                                <a href="includes/delete_logic.php?table=zakaznici&id=<?= $row['id'] ?>&redirect=Zakaznik"
                                   class="btn btn-danger btn-sm btn-delete-ajax"
                                   data-confirm="Opravdu smazat zákazníka <?= htmlspecialchars($row['nazev']) ?>?">
                                    <i class="fa fa-trash"></i> X
                                </a>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border" style="padding: 6px 10px;"><i class="fa fa-lock me-1"></i> Aktivní (<?= $row['pocet_pozadavku'] ?>×)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>