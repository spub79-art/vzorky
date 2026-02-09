<?php
include_once("./db_connect.php");

$current_page_url = "index.php?Zakaznik=1";

// --- 1. ZPRACOVÁNÍ AKCÍ ---

// PŘIDÁNÍ
if (isset($_POST['save_customer'])) {
    $nazev = mysqli_real_escape_string($conn, $_POST['nazev_novy'] ?? '');
    if (!empty($nazev)) {
        $sql = "INSERT INTO zakaznik (nazev) VALUES ('$nazev')";
        mysqli_query($conn, $sql);
        echo "<script>window.location.href='$current_page_url';</script>";
        exit;
    }
}

// EDITACE (přidána kontrola, zda není zákazník použit)
if (isset($_POST['update_zakaznik'])) {
    $id = intval($_POST['id']);
    $novy_nazev = mysqli_real_escape_string($conn, $_POST['nazev']);

    // Kontrola pro jistotu i na straně PHP
    $check = mysqli_query($conn, "SELECT id FROM pozadavky WHERE id_zakaznik = $id LIMIT 1");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "UPDATE zakaznik SET nazev = '$novy_nazev' WHERE id = $id");
    }
}

// SMAZÁNÍ
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);

    // Kontrola před smazáním
    $check = mysqli_query($conn, "SELECT id FROM pozadavky WHERE id_zakaznik = $id LIMIT 1");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "DELETE FROM zakaznik WHERE id = $id");
    }
    echo "<script>window.location.href='$current_page_url';</script>";
    exit;
}

// --- 2. NAČTENÍ DAT (včetně kontroly vazeb) ---
// Pomocí LEFT JOIN zjistíme, kolik požadavků má který zákazník
$query = "SELECT z.*, COUNT(p.id) as pocet_pozadavku 
          FROM zakaznik z 
          LEFT JOIN pozadavky p ON z.id = p.id_zakaznik 
          GROUP BY z.id 
          ORDER BY z.id DESC";
$result = mysqli_query($conn, $query);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Seznam zákazníků</h2>
        <button type="button" class="btn btn-primary" onclick="toggleAddRow()">
            <i class="fa fa-plus"></i> Přidat zákazníka
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
            <tr>
                <th style="width: 80px;">ID</th>
                <th>Název zákazníka</th>
                <th style="width: 180px;" class="text-center">Akce</th>
            </tr>
            </thead>
            <tbody>
            <tr id="addRow" style="display: none; background-color: #f0fdf4;">
                <td class="text-center"><span class="badge bg-success">Nový</span></td>
                <form method="post" action="<?= $current_page_url ?>">
                    <td>
                        <input type="text" name="nazev_novy" class="form-control form-control-sm" placeholder="Jméno nového zákazníka..." required>
                    </td>
                    <td class="text-center">
                        <button type="submit" name="save_customer" class="btn btn-success btn-sm">
                            <i class="fa fa-check"></i> Uložit
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAddRow()">
                            <i class="fa fa-times"></i>
                        </button>
                    </td>
                </form>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($result)):
                $is_locked = ($row['pocet_pozadavku'] > 0);
                ?>
                <tr>
                    <td class="text-center text-muted small"><?= $row['id'] ?></td>
                    <form method="post" action="<?= $current_page_url ?>">
                        <td>
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="text" name="nazev"
                                   class="form-control form-control-sm <?= $is_locked ? 'bg-light' : '' ?>"
                                   value="<?= htmlspecialchars($row['nazev']) ?>"
                                   required
                                   <?= $is_locked ? 'readonly title="Zákazník je použit v požadavcích"' : '' ?>>
                        </td>
                        <td class="text-center">
                            <?php if (!$is_locked): ?>
                                <button type="submit" name="update_zakaznik" class="btn btn-sm btn-success">
                                    <i class="fa fa-save"></i> Uložit
                                </button>
                                <a href="<?= $current_page_url ?>&delete_id=<?= $row['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Opravdu smazat?')">
                                    <i class="fa fa-trash"></i> Smazat
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary" title="Nelze měnit - použit v požadavcích">
                                    <i class="fa fa-lock"></i> Uzamčeno
                                </span>
                            <?php endif; ?>
                        </td>
                    </form>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function toggleAddRow() {
        var row = document.getElementById("addRow");
        if (row.style.display === "none") {
            row.style.display = "table-row";
            row.querySelector('input[name="nazev_novy"]').focus();
        } else {
            row.style.display = "none";
        }
    }
</script>