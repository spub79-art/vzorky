<?php
include_once("includes/db_connect.php");

// PŘIPOJENÍ NATVRDO K OSTRÉ DATABÁZI
$conn_aktuality = mysqli_connect("localhost", "vzorky", "vzorky", "vzorky");
if (!$conn_aktuality) {
    die("Nepodařilo se připojit k centrální databázi aktualit: " . mysqli_connect_error());
}
mysqli_set_charset($conn_aktuality, "utf8mb4");

$is_adm = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);

// 1. PŘIDÁNÍ AKTUALITY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pridat_aktualitu']) && $is_adm) {
    $nadpis = mysqli_real_escape_string($conn_aktuality, trim($_POST['nadpis']));
    $obsah  = mysqli_real_escape_string($conn_aktuality, trim($_POST['obsah']));
    $ikona  = mysqli_real_escape_string($conn_aktuality, $_POST['ikona']);

    if (!empty($nadpis) && !empty($obsah)) {
        mysqli_query($conn_aktuality, "INSERT INTO aktuality (nadpis, obsah, ikona) VALUES ('$nadpis', '$obsah', '$ikona')");
        echo "<script>window.location.href='index.php?Aktuality=1';</script>";
        exit;
    }
}

// 2. EDITACE AKTUALITY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upravit_aktualitu']) && $is_adm) {
    $id     = (int)$_POST['edit_id'];
    $nadpis = mysqli_real_escape_string($conn_aktuality, trim($_POST['edit_nadpis']));
    $obsah  = mysqli_real_escape_string($conn_aktuality, trim($_POST['edit_obsah']));
    $ikona  = mysqli_real_escape_string($conn_aktuality, $_POST['edit_ikona']);

    mysqli_query($conn_aktuality, "UPDATE aktuality SET nadpis='$nadpis', obsah='$obsah', ikona='$ikona' WHERE id=$id");
    echo "<script>window.location.href='index.php?Aktuality=1';</script>";
    exit;
}

// 3. SKRYTÍ / ODKRYTÍ AKTUALITY
if (isset($_GET['toggle_hide']) && $is_adm) {
    $id = (int)$_GET['toggle_hide'];
    // Přepne hodnotu 0 na 1 a naopak
    mysqli_query($conn_aktuality, "UPDATE aktuality SET skryto = NOT skryto WHERE id=$id");
    echo "<script>window.location.href='index.php?Aktuality=1';</script>";
    exit;
}

// NAČTENÍ AKTUALIT (Běžný uživatel vidí jen neskryté)
$sql_where = $is_adm ? "" : "WHERE skryto = 0";
$resAktuality = mysqli_query($conn_aktuality, "SELECT * FROM aktuality $sql_where ORDER BY vytvoreno DESC");
?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4"><i class="glyphicon glyphicon-bullhorn"></i> Co je nového v systému</h2>

        <?php if ($is_adm): ?>
            <div class="panel panel-default" style="border-left: 4px solid #337ab7;">
                <div class="panel-heading" style="background-color: #f8f9fa;">
                    <b class="text-primary"><i class="glyphicon glyphicon-pencil"></i> Napsat novou aktualitu (Vidíš jen ty)</b>
                </div>
                <div class="panel-body">
                    <form method="post" action="">
                        <div class="row">
                            <div class="col-md-9">
                                <input type="text" name="nadpis" class="form-control mb-2" placeholder="Stručný nadpis..." required style="margin-bottom: 10px;">
                            </div>
                            <div class="col-md-3">
                                <select name="ikona" class="form-control" style="margin-bottom: 10px;">
                                    <option value="glyphicon-info-sign">ℹ️ Informace (Info)</option>
                                    <option value="glyphicon-star">⭐ Nová funkce (Hvězda)</option>
                                    <option value="glyphicon-wrench">🔧 Oprava chyby (Klíč)</option>
                                    <option value="glyphicon-warning-sign">⚠️ Důležité upozornění</option>
                                </select>
                            </div>
                        </div>
                        <textarea name="obsah" class="form-control" rows="4" placeholder="Zde popiš, co se změnilo..." required style="margin-bottom: 10px;"></textarea>
                        <button type="submit" name="pridat_aktualitu" class="btn btn-primary"><i class="glyphicon glyphicon-send"></i> Publikovat všem</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <?php if ($resAktuality && mysqli_num_rows($resAktuality) > 0): ?>
                    <?php while ($akt = mysqli_fetch_assoc($resAktuality)):
                        $icon_color = '#337ab7';
                        if ($akt['ikona'] == 'glyphicon-star') $icon_color = '#f0ad4e';
                        if ($akt['ikona'] == 'glyphicon-wrench') $icon_color = '#5cb85c';
                        if ($akt['ikona'] == 'glyphicon-warning-sign') $icon_color = '#d9534f';

                        $je_skryto = (isset($akt['skryto']) && $akt['skryto'] == 1);
                        $panel_style = $je_skryto ? "opacity: 0.6; background-color: #f9f9f9;" : "";
                        ?>
                        <div class="panel panel-default" style="<?= $panel_style ?>">
                            <div class="panel-body" style="padding-bottom: 10px; position: relative;">

                                <?php if ($is_adm): ?>
                                    <div class="pull-right" style="position: absolute; top: 15px; right: 15px; z-index: 10;">
                                        <button type="button" class="btn btn-xs btn-default btn-edit-news"
                                                data-id="<?= $akt['id'] ?>"
                                                data-nadpis="<?= htmlspecialchars($akt['nadpis']) ?>"
                                                data-obsah="<?= htmlspecialchars($akt['obsah']) ?>"
                                                data-ikona="<?= $akt['ikona'] ?>">
                                            <i class="glyphicon glyphicon-pencil"></i> Editovat
                                        </button>
                                        <a href="index.php?Aktuality=1&toggle_hide=<?= $akt['id'] ?>" class="btn btn-xs <?= $je_skryto ? 'btn-success' : 'btn-warning' ?>">
                                            <i class="glyphicon <?= $je_skryto ? 'glyphicon-eye-open' : 'glyphicon-eye-close' ?>"></i> <?= $je_skryto ? 'Zobrazit' : 'Skrýt' ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; align-items: flex-start;">
                                    <div style="font-size: 24px; color: <?= $icon_color ?>; margin-right: 15px; margin-top: 5px;">
                                        <i class="glyphicon <?= htmlspecialchars($akt['ikona']) ?>"></i>
                                    </div>
                                    <div style="width: 100%; padding-right: 120px;">
                                        <h4 style="margin-top: 0; margin-bottom: 5px;">
                                            <b><?= htmlspecialchars($akt['nadpis']) ?></b>
                                            <?php if ($je_skryto): ?><span class="label label-warning" style="margin-left: 10px;">SKRYTO</span><?php endif; ?>
                                        </h4>
                                        <p class="text-muted" style="font-size: 0.85em; margin-bottom: 10px;">
                                            <i class="glyphicon glyphicon-time"></i> Publikováno: <?= date('j. n. Y (H:i)', strtotime($akt['vytvoreno'])) ?>
                                        </p>
                                        <div style="font-size: 14px; line-height: 1.5;">
                                            <?= nl2br($akt['obsah']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">Zatím tu nejsou žádné novinky.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php if ($is_adm): ?>
    <div class="modal fade" id="modalEditNews" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="">
                    <div class="modal-header" style="background:#337ab7; color:#fff;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity: 1;">&times;</button>
                        <h4 class="modal-title"><i class="glyphicon glyphicon-pencil"></i> Úprava aktuality</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="form-group">
                            <label>Nadpis:</label>
                            <input type="text" name="edit_nadpis" id="edit_nadpis" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Ikona:</label>
                            <select name="edit_ikona" id="edit_ikona" class="form-control">
                                <option value="glyphicon-info-sign">ℹ️ Informace (Info)</option>
                                <option value="glyphicon-star">⭐ Nová funkce (Hvězda)</option>
                                <option value="glyphicon-wrench">🔧 Oprava chyby (Klíč)</option>
                                <option value="glyphicon-warning-sign">⚠️ Důležité upozornění</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Obsah:</label>
                            <textarea name="edit_obsah" id="edit_obsah" class="form-control" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
                        <button type="submit" name="upravit_aktualitu" class="btn btn-primary">Uložit změny</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.btn-edit-news').click(function() {
                // Natáhneme si data z atributů tlačítka
                $('#edit_id').val($(this).data('id'));
                $('#edit_nadpis').val($(this).data('nadpis'));
                $('#edit_ikona').val($(this).data('ikona'));

                // jQuery u textarea si občas postaví hlavu, lepší je .val()
                $('#edit_obsah').val($(this).data('obsah'));

                // Zobrazíme okno
                $('#modalEditNews').modal('show');
            });
        });
    </script>
<?php endif; ?>