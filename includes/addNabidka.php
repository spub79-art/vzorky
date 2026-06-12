<?php
include_once("includes/db_connect.php");

include_once("permissions.php");
if (!userCanNakup()) {
    die("<div class='alert alert-danger'>Sem nemáte přístup.</div>");
}

$id_pozadavek = isset($_GET['id_pozadavek']) ? (int)$_GET['id_pozadavek'] : 0;

// 2. Zpracování uložení
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_nabidka'])) {
    // CHYTRÉ ZPRACOVÁNÍ DODAVATELE
    $dodavatel_nazev = mysqli_real_escape_string($conn, trim($_POST['dodavatel_nazev']));

    // Zjistíme, zda dodavatel existuje
    $res_check = mysqli_query($conn, "SELECT id FROM dodavatele WHERE nazev = '$dodavatel_nazev' LIMIT 1");
    if (mysqli_num_rows($res_check) > 0) {
        $row_dod = mysqli_fetch_assoc($res_check);
        $id_dodavatel = $row_dod['id'];
    } else {
        // Neexistuje -> Vytvoříme nového
        mysqli_query($conn, "INSERT INTO dodavatele (nazev) VALUES ('$dodavatel_nazev')");
        $id_dodavatel = mysqli_insert_id($conn);
    }

    // PŮVODNÍ POLE
    $cena = str_replace(',', '.', $_POST['cena_nabidka']);
    $mena = mysqli_real_escape_string($conn, $_POST['mena']);
    $dorazilo = !empty($_POST['vzorek_dorazil']) ? "'".$_POST['vzorek_dorazil']."'" : "NULL";
    $poznamka = mysqli_real_escape_string($conn, $_POST['poznamka_nakup']);

    // LOGIKA AUTOMATICKÉHO STATUSU
    $novy_status_id = (!empty($_POST['vzorek_dorazil'])) ? 4 : 2;

    // Vložení nabídky
    $sql_nabidka = "INSERT INTO pozadavky_nabidky (id_pozadavek, id_dodavatel, cena_nabidka, mena, id_status, vzorek_dorazil, poznamka_nakup, datum_poptavky) 
                    VALUES ($id_pozadavek, $id_dodavatel, '$cena', '$mena', $novy_status_id, $dorazilo, '$poznamka', NOW())";

    if (mysqli_query($conn, $sql_nabidka)) {
        mysqli_query($conn, "UPDATE pozadavky SET id_status = $novy_status_id WHERE id = $id_pozadavek");
        echo "<script>window.location.href='index.php?Pozadavek=1';</script>";
        exit;
    } else {
        echo "<div class='alert alert-danger'>Chyba: " . mysqli_error($conn) . "</div>";
    }
}

// Načtení všech dodavatelů pro našeptávač
$res_datalist = mysqli_query($conn, "SELECT nazev FROM dodavatele ORDER BY nazev");
?>

<div class="panel panel-primary shadow">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="glyphicon glyphicon-plus"></i> Nová nabídka k požadavku #<?= $id_pozadavek ?>
        </h3>
    </div>
    <div class="panel-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Dodavatel</label>
                    <input list="dodavatele_list" name="dodavatel_nazev" id="dodavatel_input" class="form-control" placeholder="Začněte psát název nebo zadejte nového..." required autocomplete="off">
                    <datalist id="dodavatele_list">
                        <?php while($d = mysqli_fetch_assoc($res_datalist)): ?>
                        <option value="<?= htmlspecialchars($d['nazev']) ?>">
                            <?php endwhile; ?>
                    </datalist>
                    <small id="new_dod_msg" class="text-warning" style="display:none; margin-top:5px;">
                        <i class="glyphicon glyphicon-info-sign"></i> Tento dodavatel je nový a bude automaticky přidán do databáze.
                    </small>
                </div>

                <div class="col-md-3 form-group">
                    <label>Nabídnutá cena</label>
                    <div class="input-group">
                        <input type="text" name="cena_nabidka" class="form-control" placeholder="0.00" required>
                        <span class="input-group-addon" style="padding: 0;">
                            <select name="mena" style="border:none; height:32px; background:transparent; padding: 0 5px;">
                                <option value="EUR">EUR</option>
                                <option value="CZK" selected="selected">CZK</option>
                                <option value="USD">USD</option>
                            </select>
                        </span>
                    </div>
                </div>

                <div class="col-md-3 form-group">
                    <label>Vzorek dorazil (volitelné)</label>
                    <input type="date" name="vzorek_dorazil" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Poznámka nákup / Specifikace nabídky</label>
                <textarea name="poznamka_nakup" class="form-control" rows="3" placeholder="Např. minimální odběr, doprava není v ceně..."></textarea>
            </div>

            <hr>
            <div class="pull-right">
                <a href="index.php?Pozadavek=1" class="btn btn-default">Zrušit</a>
                <button type="submit" name="save_nabidka" class="btn btn-success">
                    <i class="glyphicon glyphicon-ok"></i> Uložit nabídku
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // JS pro kontrolu, zda dodavatel existuje v datalistu
    document.getElementById('dodavatel_input').addEventListener('input', function() {
        var val = this.value;
        var opts = document.getElementById('dodavatele_list').options;
        var newMsg = document.getElementById('new_dod_msg');
        var found = false;

        for (var i = 0; i < opts.length; i++) {
            if (opts[i].value === val) {
                found = true;
                break;
            }
        }

        if (val.length > 0 && !found) {
            newMsg.style.display = 'block';
        } else {
            newMsg.style.display = 'none';
        }
    });
</script>