<?php
include("includes/authLF.php");
include("includes/db_connect.php");

// 1. Role a oprávnění
$is_adm     = !empty($_SESSION['adm']);
$is_vyvoj   = !empty($_SESSION['vyvoj']);
$is_orders  = !empty($_SESSION['orders']);
$is_kvalita = !empty($_SESSION['kvalita']);

$has_internal_access = ($is_adm || $is_vyvoj || $is_orders || $is_kvalita);

// 2. Detekce aktuální stránky
$page = 'Pozadavek';
if (isset($_GET['Pozadavek']))     $page = 'Pozadavek';
if (isset($_GET['Archiv']))        $page = 'Archiv';
if (isset($_GET['add_Pozadavek'])) $page = 'add_Pozadavek';
if (isset($_GET['Vzorek']))        $page = 'Vzorek';
if (isset($_GET['add_Vzorek']))    $page = 'add_Vzorek';
if (isset($_GET['Produkt']))       $page = 'Produkt';
if (isset($_GET['add_Produkt']))   $page = 'add_Produkt';
if (isset($_GET['Zakaznik']))      $page = 'Zakaznik';
if (isset($_GET['Users']))         $page = 'Users';
if (isset($_GET['add_User']))      $page = 'add_User';
if (isset($_GET['Suroviny']))      $page = 'Suroviny';
if (isset($_GET['Dodavatele']))    $page = 'Dodavatele';
if (isset($_GET['add_Dodavatel'])) $page = 'add_Dodavatel';
if (isset($_GET['add_Nabidka']))   $page = 'add_Nabidka';
if (isset($_GET['edit_Nabidka']))  $page = 'edit_Nabidka';

// Funkce pro aktivní třídu tlačítek
function btnActive($current, $targetArray) {
    return in_array($current, $targetArray) ? ' active' : '';
}

if (empty($_SESSION["username"])) {
    exit();
}

// --- MAPOVÁNÍ PRO DATABÁZI (pro AJAX akce) ---
$mapping = [
    'Pozadavek'     => 'pozadavky',
    'Archiv'        => 'pozadavky',
    'add_Pozadavek' => 'pozadavky',
    'Zakaznik'      => 'zakaznik',
    'Suroviny'      => 'suroviny',
    'Users'         => 'users',
    'Vzorek'        => 'vzorky',
    'Produkt'       => 'produkt',
    'Dodavatele'    => 'dodavatele',
    'add_Dodavatel' => 'dodavatele',
    'add_Nabidka'   => 'pozadavky_nabidky',
    'edit_Nabidka'  => 'pozadavky_nabidky'
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
</head>
<body>
<div id="maincontainer" class="container-fluid">

    <div id="buttons" class="row" style="padding: 15px;">
        <a class="btn btn-primary<?php echo btnActive($page, ['Pozadavek', 'Archiv', 'add_Pozadavek', 'Zakaznik', 'Suroviny', 'Dodavatele', 'add_Dodavatel', 'add_Nabidka', 'edit_Nabidka']); ?>" href="./index.php?Pozadavek=1">Požadavek</a>
        <a class="btn btn-primary<?php echo btnActive($page, ['Vzorek', 'add_Vzorek']); ?>" href="./index.php?Vzorek=1">Vzorek</a>
        <a class="btn btn-primary<?php echo btnActive($page, ['Produkt', 'add_Produkt']); ?>" href="./index.php?Produkt=1">Produkt</a>

        <div class="pull-right">
            <?php if ($is_adm || $is_kvalita): ?>
                <a class="btn btn-info<?php echo btnActive($page, ['Users', 'add_User']); ?>" href="./index.php?Users=1">Uživatelé</a>
            <?php endif; ?>
            <a class="btn btn-danger" href="includes/logout.php">
                Odhlásit (<?php echo is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username']; ?>)
            </a>
        </div>
    </div>

    <div id="submenu" style="margin: 10px 0;">
        <?php
        $nakup_pages = ['Pozadavek', 'Archiv', 'add_Pozadavek', 'Zakaznik', 'Suroviny', 'Dodavatele', 'add_Dodavatel', 'add_Nabidka', 'edit_Nabidka'];
        if (in_array($page, $nakup_pages)): ?>
            <a href="index.php?Pozadavek=1" class="btn btn-sm btn-success<?php echo btnActive($page, ['Pozadavek']); ?>" style="background-color: #28a745; border-color: #218838;">
                <i class="glyphicon glyphicon-list-alt"></i> Správa požadavků
            </a>
            <a href="index.php?Archiv=1" class="btn btn-sm btn-default<?php echo btnActive($page, ['Archiv']); ?>" style="margin-left:5px;">
                <i class="glyphicon glyphicon-folder-close"></i> Archiv
            </a>
            <span style="margin: 0 10px; color: #ccc;">|</span>
            <a href="index.php?add_Pozadavek=1" class="btn btn-sm btn-warning">Nový požadavek</a>
            <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Zakaznik']); ?>" href="./index.php?Zakaznik=1">Zákazník</a>
            <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Suroviny']); ?>" href="./index.php?Suroviny=1">Suroviny</a>
            <?php if ($is_adm || $is_orders): ?>
                <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Dodavatele', 'add_Dodavatel']); ?>" href="./index.php?Dodavatele=1">Dodavatelé</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <hr>

    <div id="main-content">
        <?php
        switch ($page) {
            case 'Pozadavek':     include("includes/listPozadavky.php"); break;
            case 'Archiv':        include("includes/archivPozadavky.php"); break;
            case 'add_Pozadavek': include("includes/addPozadavek.php"); break;
            case 'add_Nabidka':
                if ($is_adm || $is_orders) include("includes/addNabidka.php");
                break;
            case 'edit_Nabidka':
                if ($is_adm || $is_orders) include("includes/editNabidka.php");
                break;
            case 'Dodavatele':
                if ($is_adm || $is_orders) include("includes/listDodavatele.php");
                break;
            case 'add_Dodavatel':
                if ($is_adm || $is_orders) include("includes/addDodavatel.php");
                break;
            case 'Zakaznik':      include("includes/listZakaznici.php"); break;
            case 'Suroviny':      include("includes/listSuroviny.php"); break;
            case 'Vzorek':        include("includes/listVzorky.php"); break;
            case 'add_Vzorek':    include("includes/addVzorek.php"); break;
            case 'Produkt':       include("includes/listProdukty.php"); break;
            case 'add_Produkt':   include("includes/addProdukt.php"); break;
            case 'Users':         if ($is_adm || $is_kvalita) include("includes/listUsers.php"); break;
            case 'add_User':      if ($is_adm || $is_kvalita) include("includes/addUser.php"); break;
            default:              include("includes/listPozadavky.php"); break;
        }
        ?>
    </div>
</div>

<div class="modal fade" id="remoteModal" tabindex="-1" role="dialog" aria-labelledby="remoteModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div id="modal-loader" class="text-center" style="padding: 30px; display: none;">
                <i class="glyphicon glyphicon-refresh spinning" style="font-size: 2em;"></i><br>Načítám...
            </div>
            <div id="modal-dynamic-content">
            </div>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script src="./bootstable.min.js"></script>

<script>
    $(document).ready(function() {
        // 1. DataTables inicializace
        var initDataTable = function() {
            if ($('.table-sjednocena').length > 0) {
                $('.table-sjednocena').DataTable({
                    "paging": false,
                    "retrieve": true,
                    "order": [[0, "desc"]],
                    "language": { "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Czech.json" }
                });
            }
        };
        initDataTable();

        // 2. MODÁL: Editace nabídky (Delegovaný event pro funkčnost po AJAXu)
        $(document).off('click', '.btn-edit-offer').on('click', '.btn-edit-offer', function(e) {
            e.preventDefault();
            var id = $(this).data('id');

            $('#modal-dynamic-content').html('');
            $('#modal-loader').show();
            $('#remoteModal').modal('show');

            $('#modal-dynamic-content').load('includes/editNabidka.php?id=' + id, function(response, status, xhr) {
                $('#modal-loader').hide();
                if (status == "error") {
                    alert("Chyba při načítání formuláře: " + xhr.status + " " + xhr.statusText);
                }
            });
        });

        // 3. SMAZÁNÍ: AJAX smazání (Univerzální)
        $(document).off('click', '.btn-delete-ajax').on('click', '.btn-delete-ajax', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var tableName = $(this).data('table');
            var $btn = $(this);

            if (confirm('Opravdu smazat záznam ID ' + id + '?')) {
                $.get("includes/delete_logic.php", { id: id, table: tableName }, function(response) {
                    if (response.trim() === "OK") {
                        if (tableName === 'pozadavky_nabidky') {
                            $btn.closest('div[style*="border-bottom"]').fadeOut();
                        } else {
                            $btn.closest('tr').fadeOut();
                        }
                    } else {
                        alert("Chyba: " + response);
                    }
                });
            }
        });
    });
</script>
</body>
</html>