<?php
include("includes/authLF.php");
include("includes/db_connect.php");

// 1. Role a oprávnění
include_once("includes/permissions.php");
$perms = loadSessionPermissions();
$is_adm           = $perms['is_adm'];
$is_vyvoj         = $perms['is_vyvoj'];
$is_portfolio     = $perms['is_portfolio'];
$is_orders        = $perms['is_orders'];
$is_kvalita       = $perms['is_kvalita'];
$is_nakup_pristup = $perms['is_nakup_pristup'];
$can_nakup        = $perms['can_nakup'];
$can_claim_nakup  = $perms['can_claim_nakup'];
$can_portfolio    = $perms['can_portfolio'];
$current_uid      = $perms['current_uid'];

$portfolio_pages = ['Portfolio'];

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
if (isset($_GET['Portfolio']))     $page = 'Portfolio';
if (isset($_GET['Souhrn']))        $page = 'Souhrn';

function btnActive($current, $targetArray) {
    return in_array($current, $targetArray) ? ' active' : '';
}

if (empty($_SESSION["username"])) exit();

$mapping = [
    'Pozadavek' => 'pozadavky', 'Archiv' => 'pozadavky',
    'Zakaznik' => 'zakaznik', 'Suroviny' => 'suroviny', 'Users' => 'users',
    'Vzorek' => 'vzorky', 'Produkt' => 'produkt', 'Dodavatele' => 'dodavatele',
    'Technologie' => 'technologie', 'Aktuality' => 'aktuality',
    'Portfolio' => 'portfolio',
    'Souhrn' => 'souhrn'
];
$jsTableAction = $mapping[$page] ?? strtolower($page);

include_once("includes/digest_helpers.php");
$digest_channels = digest_channels_for_user($perms);
$digest_count = !empty($digest_channels) ? digest_count_for_user($conn, $perms) : 0;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Lifefood - <?php echo htmlspecialchars($page); ?></title>
    <?php include("./includes/header_assets.php"); ?>
    <link rel="stylesheet" type="text/css" href="styles/vzorky.css?v=<?php echo filemtime('styles/vzorky.css'); ?>">
    <script>var CURRENT_TABLE = "<?php echo $jsTableAction; ?>";</script>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/main.js?v=<?php echo filemtime('js/main.js'); ?>"></script>
    <script src="js/board.js?v=<?php echo filemtime('js/board.js'); ?>"></script>
    <?php if ($page === 'Portfolio'): ?>
    <script src="js/portfolio.js?v=<?php echo filemtime('js/portfolio.js'); ?>"></script>
    <?php endif; ?>
</head>
<body data-current-uid="<?= (int)$current_uid ?>">

<div id="sticky-header">
    <div class="top-header-bar">

        <div class="top-header-left">
            <a class="btn btn-primary btn-nav<?php echo btnActive($page, ['Pozadavek', 'Archiv', 'Suroviny', 'Dodavatele', 'Zakaznik']); ?>" title="Požadavky a nákup" href="./index.php?Pozadavek=1">
                <i class="glyphicon glyphicon-tasks"></i> Požadavky
            </a>
            <a class="btn btn-primary btn-nav<?php echo btnActive($page, ['Vzorek']); ?>" title="Vzorky k testování" href="./index.php?Vzorek=1">
                <i class="glyphicon glyphicon-compressed"></i> Vzorky
            </a>
            <?php if ($can_portfolio): ?>
                <a class="btn btn-primary btn-nav<?php echo btnActive($page, $portfolio_pages); ?>" href="./index.php?Portfolio=1">
                    <i class="glyphicon glyphicon-briefcase"></i> Portfolio
                </a>
            <?php endif; ?>
            <?php if ($is_adm || $is_vyvoj): ?>
                <a class="btn btn-primary btn-nav btn-lab<?php echo btnActive($page, ['Technologie']); ?>" title="Laboratoř — testy" href="./index.php?Technologie=1">
                    <i class="glyphicon glyphicon-flask"></i> Lab
                </a>
            <?php endif; ?>
            <a class="btn btn-primary btn-nav<?php echo btnActive($page, ['Produkt']); ?>" title="Produkty — katalog LF" href="./index.php?Produkt=1">
                <i class="glyphicon glyphicon-th-list"></i> Katalog
            </a>
        </div>

        <div class="top-header-right">
            <?php
            $is_dev_env = ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['REQUEST_URI'], 'dev-vzorky') !== false);
            if ($is_dev_env): ?>
                <span class="dev-badge" title="DEV prostředí"><i class="glyphicon glyphicon-warning-sign"></i> DEV</span>
            <?php endif; ?>

            <?php
            $neprectene = 0;
            $uid_safe = (isset($_SESSION['uid'])) ? (int)$_SESSION['uid'] : 0;
            $res_count = @mysqli_query($conn, "SELECT COUNT(*) FROM aktuality WHERE id NOT IN (SELECT id_aktualita FROM aktuality_cteni WHERE id_uzivatel = '{$uid_safe}')");

            if ($res_count) {
                $row = mysqli_fetch_row($res_count);
                if ($row) {
                    $neprectene = (int)$row[0];
                }
            }
            $user_display = is_array($_SESSION['username']) ? $_SESSION['username'][0] : $_SESSION['username'];
            ?>

            <button class="btn btn-news btn-nav" id="btnOpenNews">
                <i class="glyphicon glyphicon-bullhorn"></i> Novinky
                <?php if ($neprectene > 0): ?><span class="badge nav-badge"><?= (int)$neprectene ?></span><?php endif; ?>
            </button>

            <?php if (!empty($digest_channels)): ?>
            <a class="btn btn-info btn-nav<?php echo btnActive($page, ['Souhrn']); ?>" href="./index.php?Souhrn=1" title="Přehled k řešení podle vaší role">
                <i class="glyphicon glyphicon-dashboard"></i> Souhrn
                <?php if ($digest_count > 0): ?><span class="badge nav-badge"><?= (int)$digest_count ?></span><?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if ($is_adm || $is_kvalita): ?>
                <a class="btn btn-info btn-nav<?php echo btnActive($page, ['Users']); ?>" href="./index.php?Users=1">Uživatelé</a>
            <?php endif; ?>
            <a class="btn btn-danger btn-nav btn-logout" href="includes/logout.php" title="Odhlásit (<?= htmlspecialchars($user_display, ENT_QUOTES) ?>)">
                <i class="glyphicon glyphicon-log-out"></i><span class="btn-logout-name"><?= htmlspecialchars($user_display) ?></span>
            </a>
        </div>
    </div>

    <?php
    $nakup_pages = ['Pozadavek', 'Archiv', 'Suroviny', 'Dodavatele', 'Zakaznik'];
    if (in_array($page, $nakup_pages)): ?>
        <div id="submenu">
            <a href="index.php?Pozadavek=1" class="btn btn-sm btn-success<?php echo btnActive($page, ['Pozadavek']); ?>" style="background-color: #28a745; border-color: #218838;">
                <i class="glyphicon glyphicon-list-alt"></i> Správa požadavků
            </a>
            <a href="index.php?Archiv=1" class="btn btn-sm btn-default<?php echo btnActive($page, ['Archiv']); ?>">
                <i class="glyphicon glyphicon-folder-close"></i> Archiv
            </a>
            <span class="submenu-divider">|</span>
            <button class="btn btn-sm btn-warning btn-new-req"><i class="glyphicon glyphicon-plus"></i> Nový požadavek</button>
            <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Zakaznik']); ?>" href="./index.php?Zakaznik=1">Zákazník</a>
            <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Suroviny']); ?>" href="./index.php?Suroviny=1">Suroviny</a>
            <?php if ($can_nakup): ?>
                <a class="btn btn-sm btn-warning<?php echo btnActive($page, ['Dodavatele']); ?>" href="./index.php?Dodavatele=1">Dodavatelé</a>
            <?php endif; ?>
            <?php if ($page === 'Pozadavek'): ?>
            <span class="submenu-divider">|</span>
            <div class="submenu-board-filters">
                <div class="input-group input-group-sm submenu-search">
                    <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Hledat…">
                </div>
                <button id="btnToggleRejected" class="btn btn-xs btn-default" title="Zamítnuté / odložené">
                    <i class="glyphicon glyphicon-eye-open"></i> KO
                </button>
                <button id="btnToggleMyTasks" class="btn btn-xs btn-default" title="Jen k řešení">
                    <i class="glyphicon glyphicon-filter"></i> K řešení
                </button>
                <button id="btnToggleUrgent" class="btn btn-xs btn-default" title="Jen urgentní">
                    <i class="glyphicon glyphicon-flash text-danger"></i> Urgent
                </button>
                <button id="btnToggleSysHistory" class="btn btn-xs btn-default" title="Zobrazit systémové záznamy (změny stavů, přiřazení…)">
                    <i class="glyphicon glyphicon-cog"></i> Systém
                </button>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($can_portfolio && $page === 'Portfolio'): ?>
        <div id="submenu" class="pf-submenu-minimal">
            <span class="text-muted" style="font-size:12px; padding: 4px 8px;">
                <i class="glyphicon glyphicon-briefcase"></i> Portfolio — suroviny úzce · detail se roztáhne · sirotčinec v liště
            </span>
        </div>
    <?php endif; ?>
</div>
<div id="maincontainer" class="container-fluid">
    <div id="main-content">
        <?php
        switch ($page) {
            case 'Pozadavek':     include("includes/listPozadavky.php"); break;
            case 'Archiv':        include("includes/archivPozadavky.php"); break;
            case 'Dodavatele':    if ($can_nakup) include("includes/listDodavatele.php"); break;
            case 'Zakaznik':      include("includes/listZakaznici.php"); break;
            case 'Suroviny':      include("includes/listSuroviny.php"); break;
            case 'Vzorek':        include("includes/listVzorky.php"); break;
            case 'Produkt':       include("includes/listProdukty.php"); break;
            case 'Technologie':   if ($is_adm || $is_vyvoj) include("includes/listTechnologie.php"); break;
            case 'Portfolio':     if ($can_portfolio) include("includes/listPortfolioHome.php"); break;
            case 'Souhrn':        include("includes/listSouhrn.php"); break;
            case 'Users':         if ($is_adm || $is_kvalita) include("includes/listUsers.php"); break;
            case 'Aktuality':     include("includes/aktuality.php"); break;
            default:              include("includes/listPozadavky.php"); break;
        }
        ?>
    </div>
</div>

<?php
$pages_with_board_modals = ['Pozadavek', 'Archiv', 'Suroviny', 'Dodavatele', 'Zakaznik', 'Souhrn'];
if (in_array($page, $pages_with_board_modals, true)) {
    include_once("includes/boardModals.php");
}
?>

</body>
</html>