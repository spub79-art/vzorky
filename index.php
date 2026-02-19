<?php
include("includes/authLF.php");
include("includes/db_connect.php");

// 1. Role a oprávnění
$is_adm     = !empty($_SESSION['adm']);
$is_vyvoj   = !empty($_SESSION['vyvoj']);
$is_orders  = !empty($_SESSION['orders']);
$is_kvalita = !empty($_SESSION['kvalita']);

// 2. Detekce aktuální stránky
$page = 'Pozadavek';
if (isset($_GET['Pozadavek']))     $page = 'Pozadavek';
if (isset($_GET['Archiv']))        $page = 'Archiv';
if (isset($_GET['Suroviny']))      $page = 'Suroviny';
if (isset($_GET['Dodavatele']))    $page = 'Dodavatele';
if (isset($_GET['Zakaznik']))      $page = 'Zakaznik';
if (isset($_GET['Users']))         $page = 'Users';
if (isset($_GET['Vzorek']))        $page = 'Vzorek';
if (isset($_GET['Produkt']))       $page = 'Produkt';

function btnActive($current, $targetArray) {
    return in_array($current, $targetArray) ? ' active' : '';
}

if (empty($_SESSION["username"])) exit();

$mapping = [
    'Pozadavek' => 'pozadavky', 'Archiv' => 'pozadavky',
    'Zakaznik' => 'zakaznik', 'Suroviny' => 'suroviny', 'Users' => 'users',
    'Vzorek' => 'vzorky', 'Produkt' => 'produkt', 'Dodavatele' => 'dodavatele'
];
$jsTableAction = $mapping[$page] ?? strtolower($page);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Lifefood - <?php echo htmlspecialchars($page); ?></title>
    <?php include("./includes/header_assets.php"); ?>
    <link rel="stylesheet" type="text/css" href="styles/vzorky.css">
    <script>var CURRENT_TABLE = "<?php echo $jsTableAction; ?>";</script>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        .spinning { animation: spin 1s infinite linear; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .select2-container { z-index: 99999; }
        .checkbox-inline { margin-right: 15px; font-weight: bold; cursor: pointer; }
        body { background-color: #fcfcfc; }
        #buttons .btn-primary { margin-right: 5px; font-weight: bold; }
    </style>
</head>
<body>
<div id="maincontainer" class="container-fluid">

    <div id="buttons" class="row" style="padding: 15px; background: #fff; border-bottom: 1px solid #ddd; margin-bottom: 10px;">
        <div class="col-md-12">
            <a class="btn btn-primary<?php echo btnActive($page, ['Pozadavek', 'Archiv', 'Suroviny', 'Dodavatele', 'Zakaznik']); ?>" href="./index.php?Pozadavek=1">
                <i class="glyphicon glyphicon-tasks"></i> Požadavky & Nákup
            </a>
            <a class="btn btn-primary<?php echo btnActive($page, ['Vzorek']); ?>" href="./index.php?Vzorek=1">
                <i class="glyphicon glyphicon-compressed"></i> Vzorky k testování
            </a>
            <a class="btn btn-primary<?php echo btnActive($page, ['Produkt']); ?>" href="./index.php?Produkt=1">
                <i class="glyphicon glyphicon-th-list"></i> Produkty (Katalog)
            </a>

            <div class="pull-right">
                <?php if ($is_adm || $is_kvalita): ?>
                    <a class="btn btn-info<?php echo btnActive($page, ['Users']); ?>" href="./index.php?Users=1">Uživatelé</a>
                <?php endif; ?>
                <a class="btn btn-danger" href="includes/logout.php">
                    Odhlásit (<?php echo is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username']; ?>)
                </a>
            </div>
        </div>
    </div>

    <?php
    $nakup_pages = ['Pozadavek', 'Archiv', 'Suroviny', 'Dodavatele', 'Zakaznik'];
    if (in_array($page, $nakup_pages)): ?>
        <div id="submenu" style="margin: 10px 0; padding-left: 15px;">
            <a href="index.php?Pozadavek=1" class="btn btn-sm btn-success<?php echo btnActive($page, ['Pozadavek']); ?>" style="background-color: #28a745; border-color: #218838;">
                <i class="glyphicon glyphicon-list-alt"></i> Správa požadavků
            </a>
            <a href="index.php?Archiv=1" class="btn btn-sm btn-default<?php echo btnActive($page, ['Archiv']); ?>" style="margin-left:5px;">
                <i class="glyphicon glyphicon-folder-close"></i> Archiv
            </a>
            <span style="margin: 0 10px; color: #ccc;">|</span>

            <button class="btn btn-sm btn-warning btn-new-req"><i class="glyphicon glyphicon-plus"></i> Nový požadavek</button>

            <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Zakaznik']); ?>" href="./index.php?Zakaznik=1">Zákazník</a>
            <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Suroviny']); ?>" href="./index.php?Suroviny=1">Suroviny</a>
            <?php if ($is_adm || $is_orders): ?>
                <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Dodavatele']); ?>" href="./index.php?Dodavatele=1">Dodavatelé</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div id="main-content" style="padding: 15px;">
        <?php
        switch ($page) {
            case 'Pozadavek':     include("includes/listPozadavky.php"); break;
            case 'Archiv':        include("includes/archivPozadavky.php"); break;
            case 'Dodavatele':    if ($is_adm || $is_orders) include("includes/listDodavatele.php"); break;
            case 'Zakaznik':      include("includes/listZakaznici.php"); break;
            case 'Suroviny':      include("includes/listSuroviny.php"); break;
            case 'Vzorek':        include("includes/listVzorky.php"); break;
            case 'Produkt':       include("includes/listProdukty.php"); break;
            case 'Users':         if ($is_adm || $is_kvalita) include("includes/listUsers.php"); break;
            default:              include("includes/listPozadavky.php"); break;
        }
        ?>
    </div>
</div>

<div class="modal fade" id="mNR" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#f0ad4e; color:#fff;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Nový požadavek na surovinu</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Surovina:</label>
                    <select id="mNRSur" class="form-control select2-sur" style="width:100%;">
                        <option value="">-- Vyberte surovinu --</option>
                        <?php
                        $s_res = mysqli_query($conn, "SELECT id, nazev FROM suroviny ORDER BY nazev ASC");
                        while($s = mysqli_fetch_assoc($s_res)) echo "<option value='".$s['id']."'>".htmlspecialchars($s['nazev'])."</option>";
                        ?>
                    </select>
                </div>

                <div class="form-group" style="background:#f9f9f9; padding:10px; border:1px solid #eee; border-radius:4px;">
                    <label style="display:block; margin-bottom:5px;">Požadované certifikáty:</label>
                    <label class="checkbox-inline"><input type="checkbox" id="mNRBio" checked> BIO</label>
                    <label class="checkbox-inline"><input type="checkbox" id="mNRVegan"> Vegan</label>
                    <label class="checkbox-inline"><input type="checkbox" id="mNRBezlepek"> Bezlepek</label>
                    <label class="checkbox-inline"><input type="checkbox" id="mNRKosher"> Kosher</label>
                    <label class="checkbox-inline"><input type="checkbox" id="mNRHalal"> Halal</label>
                </div>

                <div class="form-group">
                    <label>Priorita:</label>
                    <select id="mNRPrio" class="form-control">
                        <option value="0">Normální</option>
                        <option value="1">Urgentní</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Poznámka (např. očekávané množství, specifikace):</label>
                    <textarea id="mNRNote" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="mNRSave">Vytvořit požadavek</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Inicializace Select2 s podporou tagů (pro nové suroviny)
        $('.select2-sur').select2({
            dropdownParent: $('#mNR'),
            tags: true,
            createTag: function (params) {
                return {
                    id: params.term,
                    text: params.term,
                    newTag: true
                }
            }
        });

        // Obsluha uložení nového požadavku
        $('#mNRSave').on('click', function() {
            var sur = $('#mNRSur').val();
            if(!sur) { alert("Vyberte surovinu!"); return; }

            $.post('includes/ajax_add_request.php', {
                id_surovina: sur,
                poznamka: $('#mNRNote').val(),
                priorita: $('#mNRPrio').val(),
                bio: $('#mNRBio').is(':checked') ? 1 : 0,
                vegan: $('#mNRVegan').is(':checked') ? 1 : 0,
                bezlepek: $('#mNRBezlepek').is(':checked') ? 1 : 0,
                kosher: $('#mNRKosher').is(':checked') ? 1 : 0,
                halal: $('#mNRHalal').is(':checked') ? 1 : 0
            }, function(r) {
                if(r.trim() == "OK") {
                    $('#mNR').modal('hide');
                    if (typeof safeReload === "function") safeReload();
                    else window.location.reload();
                } else {
                    alert(r);
                }
            });
        });

        $(document).on('click', '.btn-new-req', function(e) {
            e.preventDefault();
            $('#mNR').modal('show');
        });
    });
</script>
</body>
</html>