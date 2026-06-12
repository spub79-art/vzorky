<?php
// 1. Inicializace session a připojení k DB
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once("db_connect.php");

include_once("permissions.php");
if (!userCanNakup()) {
    die("<div class='alert alert-danger'>Nepovolený přístup.</div>");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 3. Načtení aktuálních dat nabídky
$sql_select = "SELECT pn.*, s.nazev AS surovina_nazev, d.nazev AS dodavatel_nazev 
               FROM pozadavky_nabidky pn
               JOIN pozadavky p ON pn.id_pozadavek = p.id
               JOIN suroviny s ON p.id_surovina = s.id
               LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
               WHERE pn.id = $id";

$res = mysqli_query($conn, $sql_select);
$data = mysqli_fetch_assoc($res);

if (!$data) {
    die("<div class='alert alert-danger'>Nabídka nebyla nalezena.</div>");
}

// 4. Zpracování uložení (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_nabidka'])) {

    $cena     = str_replace(',', '.', $_POST['cena_nabidka']);
    $mena     = mysqli_real_escape_string($conn, $_POST['mena']);
    $poznamka = mysqli_real_escape_string($conn, $_POST['poznamka_nakup']);

    // --- OŠETŘENÍ DATA (Klíčové pro stabilitu) ---
    $datum_raw = trim($_POST['vzorek_dorazil']);
    if (empty($datum_raw)) {
        $dorazilo_sql   = "NULL";
        $novy_status_id = 2; // Zůstává ve stavu "Nabídka"
    } else {
        $dorazilo_sql   = "'" . mysqli_real_escape_string($conn, $datum_raw) . "'";
        $novy_status_id = 4; // Mění se na "Vzorek dorazil"
    }

    $sql_update = "UPDATE pozadavky_nabidky SET 
            cena_nabidka   = '$cena', 
            mena           = '$mena', 
            id_status      = $novy_status_id, 
            vzorek_dorazil = $dorazilo_sql, 
            poznamka_nakup = '$poznamka' 
            WHERE id = $id";

    if (mysqli_query($conn, $sql_update)) {
        // Aktualizace statusu i u hlavního požadavku
        mysqli_query($conn, "UPDATE pozadavky SET id_status = $novy_status_id WHERE id = " . (int)$data['id_pozadavek']);

        // Přesměrování zpět na hlavní přehled
        echo "<script>window.location.href='index.php?Pozadavek=1';</script>";
        exit;
    } else {
        echo "<div class='alert alert-danger'>Chyba SQL: " . mysqli_error($conn) . "</div>";
    }
}

// 5. Logika pro výchozí měnu
$selected_mena = (!empty($data['mena'])) ? $data['mena'] : 'CZK';
?>

<form method="POST" action="index.php?edit_Nabidka=1&id=<?= $id ?>">
    <div class="modal-header" style="background-color: #fcf8e3; border-bottom: 2px solid #8a6d3b;">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">
            <i class="glyphicon glyphicon-pencil"></i>
            Úprava nabídky: <strong><?= htmlspecialchars($data['surovina_nazev']) ?></strong>
        </h4>
    </div>

    <div class="modal-body">
        <div class="row">
            <div class="col-md-6 form-group">
                <label>Dodavatel</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($data['dodavatel_nazev'] ?? 'Neuveden') ?>" readonly style="background-color: #f5f5f5;">
            </div>

            <div class="col-md-3 form-group">
                <label>Cena nabídky</label>
                <input type="text" name="cena_nabidka" class="form-control" value="<?= htmlspecialchars($data['cena_nabidka']) ?>" required>
            </div>

            <div class="col-md-3 form-group">
                <label>Měna</label>
                <select name="mena" class="form-control">
                    <option value="EUR" <?= ($selected_mena == 'EUR' ? 'selected' : '') ?>>EUR</option>
                    <option value="CZK" <?= ($selected_mena == 'CZK' ? 'selected' : '') ?>>CZK</option>
                    <option value="USD" <?= ($selected_mena == 'USD' ? 'selected' : '') ?>>USD</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 form-group">
                <label>Vzorek doručen (Datum)</label>
                <input type="date" name="vzorek_dorazil" class="form-control" value="<?= $data['vzorek_dorazil'] ?>">
                <p class="help-block" style="font-size: 0.85em;">Vyplněním data automaticky změníte status na "Vzorek dorazil".</p>
            </div>
        </div>

        <div class="form-group">
            <label>Poznámka nákup (Interní informace)</label>
            <textarea name="poznamka_nakup" class="form-control" rows="4"><?= htmlspecialchars($data['poznamka_nakup']) ?></textarea>
        </div>
    </div>

    <div class="modal-footer" style="background-color: #f9f9f9;">
        <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
        <button type="submit" name="update_nabidka" class="btn btn-warning">
            <i class="glyphicon glyphicon-save"></i> Uložit změny
        </button>
    </div>
</form>