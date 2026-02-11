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
        // Zápis proměnné z PHP do JS
        var CURRENT_TABLE = "<?php echo $jsTableAction; ?>";

        // POJISTKA: Pokud PHP selže, zkusíme detekci z URL
        if (!CURRENT_TABLE || CURRENT_TABLE === "") {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('Suroviny')) CURRENT_TABLE = 'suroviny';
            else if (urlParams.has('Zakaznik')) CURRENT_TABLE = 'zakaznik';
            else if (urlParams.has('Pozadavek')) CURRENT_TABLE = 'pozadavky';
        }
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
// Inicializace DataTable
        $('.table-sjednocena, #editableTable').DataTable({
            "paging": false,
            "autoWidth": false, // VYPNUTÍ AUTOMATICKÉ ŠÍŘKY
            "order": [],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Czech.json" }
        });

// Pokud používáš i ID #editableTable (pro editovatelné tabulky)
        if ($.fn.DataTable.isDataTable('#editableTable')) {
            $('#editableTable').DataTable().destroy(); // Zničíme předchozí instanci, pokud existuje
        }

        $('#editableTable').DataTable({
            "paging": false,    // VYPNE STRÁNKOVÁNÍ
            "order": [[0, "desc"]], // Seřadí od nejnovějšího
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Czech.json" }
        });
// Oživení tlačítek se třídou .btn-delete-ajax
        $(document).on('click', '.btn-delete-ajax', function(e) {
            e.preventDefault();

            var btn = $(this);
            var id = btn.data('id');
            var table = btn.data('table');
            var row = btn.closest('tr');

            if (confirm('Opravdu chcete smazat tento záznam (ID: ' + id + ')?')) {
                $.ajax({
                    type: 'GET',
                    url: "includes/delete_logic.php", // Zkus přidat / na začátek, pokud je index v rootu: "/includes/delete_logic.php"
                    data: { id: id, table: table },
                    success: function(response) {
                        if (response.trim() === "OK") {
                            // Pokud používáš DataTables, smažeme to přes API, aby fungovalo vyhledávání
                            var tableApi = $('#editableTable').DataTable();
                            tableApi.row(row).remove().draw(false);
                        } else {
                            alert("Chyba: " + response);
                        }
                    },
                    error: function() {
                        alert("Chyba komunikace se serverem.");
                    }
                });
            }
        });
        if ($('#editableTable').length > 0) {
            // Zjistíme název tabulky přímo z HTML atributu data-table
            var tableRealName = $('#editableTable').attr('data-table');

            // Pokud atribut chybí, zkusíme zálohu z PHP proměnné, kterou jsme si definovali dříve
            if (!tableRealName) {
                tableRealName = typeof CURRENT_TABLE !== 'undefined' ? CURRENT_TABLE : 'pozadavky';
            }

            $('#editableTable').SetEditable({
                columnsEd: "1,2,3,4",
                onEdit: function(columnsEd) {
                    var row = $(columnsEd[0]).closest('tr');
                    var id = row.find('td:first').text().trim();
                    $.ajax({
                        type: 'POST',
                        url: "includes/update_logic.php",
                        data: { id: id, table: tableRealName, action: 'edit_inline' },
                        success: function(r) { console.log("Edit: " + tableRealName); }
                    });
                },
                onBeforeDelete: function(columnsEd) {
                    var row = $(columnsEd[0]).closest('tr');
                    var id = row.find('td:first').text().trim();

                    if (confirm('Opravdu smazat ID ' + id + ' z tabulky ' + tableRealName + '?')) {
                        $.ajax({
                            type: 'GET',
                            url: "includes/delete_logic.php",
                            data: { id: id, table: tableRealName },
                            success: function(response) {
                                if (response.trim() === "OK") {
                                    if ($.fn.DataTable.isDataTable('#editableTable')) {
                                        $('#editableTable').DataTable().row(row).remove().draw(false);
                                    } else {
                                        row.fadeOut();
                                    }
                                } else {
                                    // Tady už UVIDÍŠ název tabulky, pokud to znovu selže
                                    alert("Server vrátil chybu: " + response);
                                }
                            },
                            error: function(xhr) {
                                alert('Chyba serveru. Status: ' + xhr.status);
                            }
                        });
                    }
                    return false;
                }
            });
        }
    });
</script>
</body>
</html>