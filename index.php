<?php
include_once($_SERVER['DOCUMENT_ROOT'] . "/config/config.php");
include("includes/authLF.php");
include("includes/db_connect.php");

// 1. Role a oprávnění
$is_adm     = !empty($_SESSION['adm']);
$is_vyvoj   = !empty($_SESSION['vyvoj']);
$is_orders  = !empty($_SESSION['orders']);
$is_kvalita = !empty($_SESSION['kvalita']);
$is_cumil   = !empty($_SESSION['cumil']);

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
if (isset($_GET['Technologie']))   $page = 'Technologie';
if (isset($_GET['Aktuality']))     $page = 'Aktuality';

function btnActive($current, $targetArray) {
    return in_array($current, $targetArray) ? ' active' : '';
}

if (empty($_SESSION["username"])) exit();

$mapping = [
    'Pozadavek' => 'pozadavky', 'Archiv' => 'pozadavky',
    'Zakaznik' => 'zakaznik', 'Suroviny' => 'suroviny', 'Users' => 'users',
    'Vzorek' => 'vzorky', 'Produkt' => 'produkt', 'Dodavatele' => 'dodavatele',
    'Technologie' => 'technologie',
    'Aktuality' => 'aktuality'
];
$jsTableAction = $mapping[$page] ?? strtolower($page);

// =======================================================
// NOVINKA: ZJIŠTĚNÍ NEJPŘEČTENĚJŠÍ AKTUALITY PRO TLAČÍTKO
// =======================================================
$unseen_news_badge = '';
$conn_akt_check = mysqli_connect("localhost", "vzorky", "vzorky", "vzorky");

if ($conn_akt_check) {
    $q_max = mysqli_query($conn_akt_check, "SELECT MAX(id) as max_id FROM aktuality");
    if ($q_max) {
        $row_max = mysqli_fetch_assoc($q_max);
        $max_news_id = $row_max['max_id'] ? (int)$row_max['max_id'] : 0;

        if ($page == 'Aktuality' && $max_news_id > 0) {
            setcookie('last_read_news', $max_news_id, time() + (86400 * 30), "/");
            $_COOKIE['last_read_news'] = $max_news_id;
        }

        $last_read_cookie = isset($_COOKIE['last_read_news']) ? (int)$_COOKIE['last_read_news'] : 0;

        if ($max_news_id > 0 && $last_read_cookie < $max_news_id) {
            $unseen_news_badge = '<span class="badge-pulse">Nové</span>';
        }
    }
    mysqli_close($conn_akt_check);
}
// =======================================================
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Lifefood - <?php echo htmlspecialchars($page); ?></title>
    <?php include("./includes/header_assets.php"); ?>
    <link rel="stylesheet" type="text/css" href="styles/vzorky.css?v=<?php echo filemtime('styles/vzorky.css'); ?>">
    <script>var CURRENT_TABLE = "<?php echo $jsTableAction; ?>";</script>
</head>
<body>

<div id="maincontainer" class="container-fluid">

    <div id="sticky-header">

        <div class="top-header-bar">

            <div class="top-header-left">
                <a class="btn btn-primary<?php echo btnActive($page, ['Pozadavek', 'Archiv', 'Suroviny', 'Dodavatele', 'Zakaznik']); ?>" href="./index.php?Pozadavek=1">
                    <i class="glyphicon glyphicon-tasks"></i> Požadavky & Nákup
                </a>
                <a class="btn btn-primary<?php echo btnActive($page, ['Vzorek']); ?>" href="./index.php?Vzorek=1">
                    <i class="glyphicon glyphicon-compressed"></i> Vzorky k testování
                </a>

                <?php if ($is_adm || $is_vyvoj): ?>
                    <a class="btn btn-primary btn-lab<?php echo btnActive($page, ['Technologie']); ?>" href="./index.php?Technologie=1">
                        <i class="glyphicon glyphicon-flask"></i> Laboratoř (Testy)
                    </a>
                <?php endif; ?>

                <a class="btn btn-primary<?php echo btnActive($page, ['Produkt']); ?>" href="./index.php?Produkt=1">
                    <i class="glyphicon glyphicon-th-list"></i> Produkty (Katalog)
                </a>
            </div>

            <?php if ($page == 'Pozadavek'): ?>
                <div class="top-header-center">
                    <input type="text" id="searchInput" class="form-control input-sm" placeholder="🔍 Hledat ID nebo název...">
                    <button id="btnToggleRejected" class="btn btn-sm btn-default"><i class="glyphicon glyphicon-eye-open"></i> Zamítnuté (KO)</button>
                </div>
                <button id="btnToggleMyTasks" class="btn btn-default">
                    <i class="glyphicon glyphicon-filter"></i> Jen k řešení
                </button>
            <?php endif; ?>

            <div class="top-header-right">
                <?php if (defined('IS_DEV') && IS_DEV): ?>
                    <div class="dev-badge">
                        <i class="glyphicon glyphicon-warning-sign"></i> DEV PROSTŘEDÍ
                    </div>
                <?php endif; ?>

                <a class="btn btn-news<?php echo btnActive($page, ['Aktuality']); ?>" href="./index.php?Aktuality=1">
                    <i class="glyphicon glyphicon-bullhorn"></i> Novinky <?= $unseen_news_badge ?>
                </a>

                <?php if ($is_adm || $is_kvalita): ?>
                    <a class="btn btn-info<?php echo btnActive($page, ['Users']); ?>" href="./index.php?Users=1">Uživatelé</a>
                <?php endif; ?>

                <a class="btn btn-danger" href="includes/logout.php">
                    Odhlásit (<?php echo is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username']; ?>)
                </a>
            </div>
        </div>

        <?php
        $nakup_pages = ['Pozadavek', 'Archiv', 'Suroviny', 'Dodavatele', 'Zakaznik'];
        if (in_array($page, $nakup_pages)): ?>
            <div id="submenu">
                <a href="index.php?Pozadavek=1" class="btn btn-sm btn-success<?php echo btnActive($page, ['Pozadavek']); ?>">
                    <i class="glyphicon glyphicon-list-alt"></i> Správa požadavků
                </a>
                <a href="index.php?Archiv=1" class="btn btn-sm btn-default<?php echo btnActive($page, ['Archiv']); ?>">
                    <i class="glyphicon glyphicon-folder-close"></i> Archiv
                </a>
                <span class="submenu-divider">|</span>

                <button class="btn btn-sm btn-warning btn-new-req"><i class="glyphicon glyphicon-plus"></i> Nový požadavek</button>

                <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Zakaznik']); ?>" href="./index.php?Zakaznik=1">Zákazník</a>
                <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Suroviny']); ?>" href="./index.php?Suroviny=1">Suroviny</a>
                <?php if ($is_adm || $is_orders): ?>
                    <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Dodavatele']); ?>" href="./index.php?Dodavatele=1">Dodavatelé</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
    <div id="main-content">
        <?php
        switch ($page) {
            case 'Pozadavek':     include("includes/listPozadavky.php"); break;
            case 'Archiv':        include("includes/archivPozadavky.php"); break;
            case 'Dodavatele':    if ($is_adm || $is_orders) include("includes/listDodavatele.php"); break;
            case 'Zakaznik':      include("includes/listZakaznici.php"); break;
            case 'Suroviny':      include("includes/listSuroviny.php"); break;
            case 'Vzorek':        include("includes/listVzorky.php"); break;
            case 'Produkt':       include("includes/listProdukty.php"); break;
            case 'Technologie':   if ($is_adm || $is_vyvoj) include("includes/listTechnologie.php"); break;
            case 'Users':         if ($is_adm || $is_kvalita) include("includes/listUsers.php"); break;
            case 'Aktuality':     include("includes/aktuality.php"); break;
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

                <div class="form-group cert-box">
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
        $('.select2-sur').select2({
            dropdownParent: $('#mNR'),
            tags: true,
            createTag: function (params) {
                return { id: params.term, text: params.term, newTag: true }
            }
        });

        $('#mNRSave').on('click', function() {
            var sur = $('#mNRSur').val();
            if(!sur) { alert("Vyberte surovinu!"); return; }

            var btn = $(this);
            btn.prop('disabled', true).text('Zakládám...');

            function odeslatPozadavek(forceValue) {
                $.post('includes/ajax_add_request.php', {
                    id_surovina: sur,
                    poznamka: $('#mNRNote').val(),
                    priorita: $('#mNRPrio').val(),
                    bio: $('#mNRBio').is(':checked') ? 1 : 0,
                    vegan: $('#mNRVegan').is(':checked') ? 1 : 0,
                    bezlepek: $('#mNRBezlepek').is(':checked') ? 1 : 0,
                    kosher: $('#mNRKosher').is(':checked') ? 1 : 0,
                    halal: $('#mNRHalal').is(':checked') ? 1 : 0,
                    force_create: forceValue
                }, function(r) {
                    var odpoved = r.trim();
                    btn.prop('disabled', false).text('Vytvořit požadavek');

                    if (odpoved.startsWith("WARNING|")) {
                        var exist_id = odpoved.split('|')[1];

                        // Zobrazení našeho hezkého modálu
                        $('#dupExistId').text(exist_id);
                        $('#mDuplicateWarning').modal('show');

                        // Odpojíme předchozí akce tlačítek a napojíme nové
                        $('#btnDupForce').off('click').on('click', function() {
                            $('#mDuplicateWarning').modal('hide');
                            btn.prop('disabled', true).text('Vynucuji založení...');
                            odeslatPozadavek(1);
                        });

                        $('#btnDupGoTo').off('click').on('click', function() {
                            $('#mDuplicateWarning').modal('hide');
                            $('#mNR').modal('hide');
                            if ($('#searchInput').length > 0) {
                                $('#searchInput').val(exist_id).trigger('input');
                            }
                        });

                    } else if(odpoved === "OK") {
                        $('#mNR').modal('hide');
                        if (typeof safeReload === "function") safeReload();
                        else window.location.reload();
                    } else {
                        alert(odpoved);
                    }
                }).fail(function() {
                    alert("Kritická chyba serveru.");
                    btn.prop('disabled', false).text('Vytvořit požadavek');
                });
            }

            odeslatPozadavek(0);
        });

        $(document).on('click', '.btn-new-req', function(e) {
            e.preventDefault();
            $('#mNRSur').val('').trigger('change');
            $('#mNRBio').prop('checked', true);
            $('#mNRVegan, #mNRBezlepek, #mNRKosher, #mNRHalal').prop('checked', false);
            $('#mNRPrio').val('0');
            $('#mNRNote').val('');
            $('#mNR').modal('show');
        });
        $(document).on('click', '#btnToggleMyTasks', function() {
            showOnlyMyTasks = !showOnlyMyTasks;
            applyFilters();
        });
    });
</script>

<?php
// --- ZJIŠTĚNÍ POČTU NEVYŘEŠENÝCH POŽADAVKŮ (Pouze pro Admina/Vývoj) ---
$unresolved_badge = '';
if ($is_adm || $is_vyvoj) {
    $dev_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM dev_pozadavky WHERE stav = 0");
    if ($dev_q) {
        $dev_r = mysqli_fetch_assoc($dev_q);
        if ($dev_r['c'] > 0) {
            $unresolved_badge = '<span id="devBadgeCount" class="badge-pulse">'.$dev_r['c'].'</span>';
        }
    }
}
?>

<div class="feedback-btn-wrapper">
    <button id="btnOpenDevTasks" class="btn btn-primary btn-feedback">
        <i class="glyphicon glyphicon-bullhorn"></i> Nápady & Úpravy
        <?= $unresolved_badge ?>
    </button>
</div>

<div class="modal fade" id="mDevTasks" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content feedback-modal-content">
            <div class="modal-header feedback-modal-header">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title feedback-modal-title"><i class="glyphicon glyphicon-bullhorn"></i> Požadavky na úpravu systému</h4>
            </div>
            <div class="modal-body feedback-modal-body">

                <div class="feedback-form-box">
                    <label class="small text-muted" style="text-transform: uppercase;">Máte nápad na vylepšení nebo jste našli chybu?</label>
                    <textarea id="devTaskText" class="form-control feedback-textarea" rows="3" placeholder="Napište sem, co byste potřebovali přidat nebo opravit..."></textarea>
                    <button id="btnSaveDevTask" class="btn btn-primary btn-block btn-feedback-submit">Odeslat vývojáři</button>
                </div>

                <h5 class="feedback-list-title">Seznam požadavků</h5>
                <div id="devTasksList" class="feedback-list-container">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#btnOpenDevTasks').click(function() {
            loadDevTasks();
            $('#mDevTasks').modal('show');
        });

        $('#btnSaveDevTask').click(function() {
            var text = $('#devTaskText').val().trim();
            if (!text) return;
            var btn = $(this);
            btn.prop('disabled', true).text('Odesílám...');

            $.post('includes/ajax_dev_pozadavky.php', { action: 'add', text: text }, function(r) {
                btn.prop('disabled', false).text('Odeslat vývojáři');
                if (r.trim() === "OK") {
                    $('#devTaskText').val('');
                    loadDevTasks();
                } else {
                    alert("Chyba: " + r);
                }
            });
        });

        $(document).on('click', '.btn-close-dev-task', function() {
            var id = $(this).data('id');
            var btn = $(this);
            btn.prop('disabled', true).text('...');

            $.post('includes/ajax_dev_pozadavky.php', { action: 'close', id: id }, function(r) {
                if (r.trim() === "OK") {
                    loadDevTasks();
                    var badge = $('#devBadgeCount');
                    if (badge.length) {
                        var count = parseInt(badge.text()) - 1;
                        if (count > 0) badge.text(count);
                        else badge.remove();
                    }
                } else {
                    alert("Chyba: " + r);
                    btn.prop('disabled', false).text('✔ Vyřešit');
                }
            });
        });

        function loadDevTasks() {
            $('#devTasksList').html('<div class="feedback-msg"><i class="glyphicon glyphicon-refresh spinning"></i> Načítám...</div>');
            $.post('includes/ajax_dev_pozadavky.php', { action: 'load' }, function(html) {
                $('#devTasksList').html(html);
            });
        }

        // SMRSKÁVÁNÍ HLAVIČKY PŘI SCROLLOVÁNÍ
        $(window).scroll(function() {
            if ($(window).scrollTop() > 40) {
                $('#sticky-header').addClass('is-scrolled');
            } else {
                $('#sticky-header').removeClass('is-scrolled');
            }
        });
    });
</script>
</body>
</html>