<?php
// Pokud db_connect includuješ už v indexu, můžeš tento řádek zakomentovat
include_once("./db_connect.php");

$current_page_url = "index.php?Users=1";

// --- 1. ZPRACOVÁNÍ AKCÍ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_user'])) {
        $jmeno = mysqli_real_escape_string($conn, $_POST['jmeno_novy']);
        $login = mysqli_real_escape_string($conn, $_POST['login_novy']);
        $heslo = mysqli_real_escape_string($conn, $_POST['heslo_novy']);

        $admin   = isset($_POST['admin_novy']) ? 1 : 0;
        $vyvoj   = isset($_POST['vyvoj_novy']) ? 1 : 0;
        $orders  = isset($_POST['orders_novy']) ? 1 : 0;
        $kvalita = isset($_POST['kvalita_novy']) ? 1 : 0;

        $sqlIn = "INSERT INTO users (jmeno, login, heslo, admin, vyvoj, orders, kvalita) 
                  VALUES ('$jmeno', '$login', '$heslo', $admin, $vyvoj, $orders, $kvalita)";
        if(mysqli_query($conn, $sqlIn)) {
            echo "<script>window.location.href='$current_page_url';</script>";
            exit;
        } else {
            die("Chyba při ukládání: " . mysqli_error($conn));
        }
    }

    if (isset($_POST['update_user'])) {
        $id = intval($_POST['id']);
        $jmeno = mysqli_real_escape_string($conn, $_POST['jmeno']);
        $login = mysqli_real_escape_string($conn, $_POST['login']);
        $heslo = mysqli_real_escape_string($conn, $_POST['heslo']);

        $admin   = isset($_POST['admin']) ? 1 : 0;
        $vyvoj   = isset($_POST['vyvoj']) ? 1 : 0;
        $orders  = isset($_POST['orders']) ? 1 : 0;
        $kvalita = isset($_POST['kvalita']) ? 1 : 0;

        $sqlUpdate = "UPDATE users SET jmeno='$jmeno', login='$login', heslo='$heslo', 
                      admin=$admin, vyvoj=$vyvoj, orders=$orders, kvalita=$kvalita WHERE id=$id";
        mysqli_query($conn, $sqlUpdate);
    }
}

if (isset($_GET['delete_user_id'])) {
    $idDel = intval($_GET['delete_user_id']);
    mysqli_query($conn, "DELETE FROM users WHERE id = $idDel");
    echo "<script>window.location.href='$current_page_url';</script>";
    exit;
}

// --- 2. NAČTENÍ DAT ---
$sqlSelect = "SELECT * FROM users ORDER BY id DESC";
$resUsers = mysqli_query($conn, $sqlSelect);

if (!$resUsers) {
    die("Chyba v SQL dotazu: " . mysqli_error($conn));
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3 text-dark">
        <h2 class="mb-0">Správa uživatelů</h2>
        <button type="button" class="btn btn-primary" onclick="toggleAddRow()">
            <i class="fa fa-user-plus"></i> Nový uživatel
        </button>
    </div>

    <div class="table-responsive shadow-sm">
        <table class="table table-bordered table-striped align-middle bg-white">
            <thead class="table-dark text-nowrap">
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Jméno</th>
                <th>Login</th>
                <th>Heslo</th>
                <th class="text-center" title="Admin">Adm</th>
                <th class="text-center" title="Vývoj">Výv</th>
                <th class="text-center" title="Orders">Ord</th>
                <th class="text-center" title="Kvalita">Kva</th>
                <th class="text-center" style="width: 120px;">Akce</th>
            </tr>
            </thead>
            <tbody>
            <tr id="addRow" style="display: none; background-color: #f0fdf4;">
                <td class="text-center"><span class="badge bg-success">NEW</span></td>
                <form method="post" action="<?= $current_page_url ?>">
                    <td><input type="text" name="jmeno_novy" class="form-control form-control-sm" required></td>
                    <td><input type="text" name="login_novy" class="form-control form-control-sm" required></td>
                    <td><input type="text" name="heslo_novy" class="form-control form-control-sm"></td>
                    <td class="text-center"><input type="checkbox" name="admin_novy"></td>
                    <td class="text-center"><input type="checkbox" name="vyvoj_novy"></td>
                    <td class="text-center"><input type="checkbox" name="orders_novy"></td>
                    <td class="text-center"><input type="checkbox" name="kvalita_novy"></td>
                    <td class="text-center text-nowrap">
                        <button type="submit" name="save_user" class="btn btn-success btn-sm"><i class="fa fa-check"></i></button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAddRow()"><i class="fa fa-times"></i></button>
                    </td>
                </form>
            </tr>

            <?php while ($u = mysqli_fetch_assoc($resUsers)): ?>
                <tr>
                    <td class="text-center text-muted small"><?= $u['id'] ?></td>
                    <form method="post" action="<?= $current_page_url ?>">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <td><input type="text" name="jmeno" class="form-control form-control-sm" value="<?= htmlspecialchars($u['jmeno']) ?>" required></td>
                        <td><input type="text" name="login" class="form-control form-control-sm" value="<?= htmlspecialchars($u['login']) ?>" required></td>
                        <td><input type="text" name="heslo" class="form-control form-control-sm" value="<?= htmlspecialchars($u['heslo']) ?>"></td>
                        <td class="text-center"><input type="checkbox" name="admin" <?= $u['admin'] ? 'checked' : '' ?>></td>
                        <td class="text-center"><input type="checkbox" name="vyvoj" <?= $u['vyvoj'] ? 'checked' : '' ?>></td>
                        <td class="text-center"><input type="checkbox" name="orders" <?= $u['orders'] ? 'checked' : '' ?>></td>
                        <td class="text-center"><input type="checkbox" name="kvalita" <?= $u['kvalita'] ? 'checked' : '' ?>></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <button type="submit" name="update_user" class="btn btn-success btn-sm"><i class="fa fa-save">Uložit</i></button>
                                <a href="<?= $current_page_url ?>&delete_user_id=<?= $u['id'] ?>"
                                   class="btn btn-danger btn-sm" onclick="return confirm('Smazat?')">
                                    <i class="fa fa-trash">X</i>
                                </a>
                            </div>
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
        var r = document.getElementById("addRow");
        r.style.display = (r.style.display === "none") ? "table-row" : "none";
    }
</script>