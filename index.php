<?php
include("includes/authLF.php");
include("includes/db_connect.php");

// 1. Role
$is_adm     = !empty($_SESSION['adm']);
$is_vyvoj   = !empty($_SESSION['vyvoj']);
$is_orders  = !empty($_SESSION['orders']);
$is_kvalita = !empty($_SESSION['kvalita']);

$has_internal_access = ($is_adm || $is_vyvoj || $is_orders || $is_kvalita);

// 2. Detekce stránky
$page = 'Pozadavek';
if (isset($_GET['Pozadavek']))     $page = 'Pozadavek';
if (isset($_GET['add_Pozadavek'])) $page = 'add_Pozadavek';
if (isset($_GET['Vzorek']))        $page = 'Vzorek';
if (isset($_GET['add_Vzorek']))    $page = 'add_Vzorek';
if (isset($_GET['Produkt']))       $page = 'Produkt';
if (isset($_GET['add_Produkt']))   $page = 'add_Produkt';
if (isset($_GET['Zakaznik']))      $page = 'Zakaznik';
if (isset($_GET['Users']))         $page = 'Users';
if (isset($_GET['add_User']))      $page = 'add_User';
if (isset($_GET['Suroviny']))      $page = 'Suroviny';

function btnActive($current, $targetArray) {
    return in_array($current, $targetArray) ? ' active' : '';
}

if (empty($_SESSION["username"])) {
    exit();
}

// --- MAPOVÁNÍ PRO DATABÁZI ---
$mapping = [
    'Pozadavek'     => 'pozadavky',
    'add_Pozadavek' => 'pozadavky',
    'Zakaznik'      => 'zakaznik',
    'Suroviny'      => 'suroviny',
    'Users'         => 'users',
    'Vzorek'        => 'vzorky',
    'Produkt'       => 'produkt'
];
$jsTableAction = $mapping[$page] ?? strtolower($page);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Lifefood - <?php echo $page; ?></title>
    <?php include("./includes/header_assets.php"); ?>
    <link rel="stylesheet" type="text/css" href="styles/vzorky.css">

    <script>
        var CURRENT_TABLE = "<?php echo $jsTableAction; ?>";
    </script>
</head>
<body>
<div id="maincontainer" class="container-fluid">

    <div id="buttons" class="row" style="padding: 15px;">
        <a class="btn btn-primary<?php echo btnActive($page, ['Pozadavek', 'add_Pozadavek', 'Zakaznik', 'Suroviny']); ?>" href="./index.php?Pozadavek=1">Požadavek</a>
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
        <?php if (in_array($page, ['Pozadavek', 'add_Pozadavek', 'Zakaznik', 'Suroviny'])): ?>
            <a href="index.php?add_Pozadavek=1" class="btn btn-sm btn-warning">Nový požadavek</a>
            <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Zakaznik']); ?>" href="./index.php?Zakaznik=1">Zákazník</a>
            <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Suroviny']); ?>" href="./index.php?Suroviny=1">Suroviny</a>
        <?php elseif ($page == 'Vzorek'): ?>
            <a href="index.php?add_Vzorek=1" class="btn btn-sm btn-warning">Nový vzorek</a>
        <?php elseif ($page == 'Produkt'): ?>
            <a href="index.php?add_Produkt=1" class="btn btn-sm btn-warning">Nový produkt</a>
        <?php endif; ?>
    </div>

    <hr>

    <div id="main-content">
        <?php
        switch ($page) {
            case 'Pozadavek':     include("includes/listPozadavky.php"); break;
            case 'add_Pozadavek': include("includes/addPozadavek.php"); break;
            case 'Vzorek':        include("includes/listVzorky.php"); break;
            case 'add_Vzorek':    include("includes/addVzorek.php"); break;
            case 'Produkt':       include("includes/listProdukty.php"); break;
            case 'add_Produkt':   include("includes/addProdukt.php"); break;
            case 'Zakaznik':      include("includes/listZakaznici.php"); break;
            case 'Users':         include("includes/listUsers.php"); break;
            case 'add_User':      include("includes/addUser.php"); break;
            case 'Suroviny':      include("includes/listSuroviny.php"); break;
            default:              include("includes/listPozadavky.php"); break;
        }
        ?>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap.min.js"></script>
<script src="./bootstable.min.js"></script>

<script>
    $(document).ready(function() {
        // Přidáme parametr retrieve: true
        var table = $('.table-sjednocena, #editableTable').DataTable({
            "paging": false,
            "autoWidth": false,
            "retrieve": true, // <--- KLÍČOVÁ OPRAVA: Pokud už existuje, prostě ji načti
            "order": [[0, "desc"]],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Czech.json" }
        });

        // 2. Hlavní funkce pro mazání (AJAX)
        function deleteRowAjax(id, tableName, rowElement) {
            if (confirm('Opravdu chcete smazat tento záznam (ID: ' + id + ')?')) {
                $.ajax({
                    type: 'GET',
                    url: "includes/delete_logic.php",
                    data: { id: id, table: tableName },
                    success: function(response) {
                        if (response.trim() === "OK") {
                            // Smazání řádku z DataTables API
                            var dt = $('.table-sjednocena, #editableTable').DataTable();
                            dt.row(rowElement).remove().draw(false);
                        } else {
                            alert("Chyba: " + response);
                        }
                    },
                    error: function(xhr) {
                        alert("Chyba komunikace se serverem: " + xhr.status);
                    }
                });
            }
        }

        // 3. Odchycení kliknutí na smazat (tlačítko v listPozadavky.php)
        $(document).on('click', '.btn-delete-ajax', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var id = $(this).data('id');
            var tableName = $(this).data('table') || CURRENT_TABLE;
            var row = $(this).closest('tr');
            deleteRowAjax(id, tableName, row);
            return false;
        });

        // 4. Inline editace (Bootstable)
        if ($('#editableTable').length > 0) {
            var tableRealName = $('#editableTable').attr('data-table') || CURRENT_TABLE;

            $('#editableTable').SetEditable({
                columnsEd: "1,2,3,4",
                onEdit: function(columnsEd) {
                    var row = $(columnsEd[0]).closest('tr');
                    var id = row.find('td:first').text().trim();
                    $.ajax({
                        type: 'POST',
                        url: "includes/update_logic.php",
                        data: { id: id, table: tableRealName, action: 'edit_inline' },
                        success: function(r) { console.log("Editováno ID: " + id); }
                    });
                },
                onBeforeDelete: function(columnsEd) {
                    var row = $(columnsEd[0]).closest('tr');
                    var id = row.find('td:first').text().trim();
                    deleteRowAjax(id, tableRealName, row);
                    return false; // Bootstable samo řádek nesmaže, udělá to náš AJAX success
                }
            });
        }
    });
</script>
</body>
</html>