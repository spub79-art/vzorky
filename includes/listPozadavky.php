<?php
// Načtení dat přímo v souboru
include_once("includes/db_connect.php");

$sql = "SELECT p.*, 
               z.nazev AS zakaznik_nazev, 
               s.nazev AS surovina_nazev 
        FROM pozadavky p 
        LEFT JOIN zakaznik z ON p.id_zakaznik = z.id 
        LEFT JOIN suroviny s ON p.id_surovina = s.id 
        ORDER BY p.datumPozadavek DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Chyba v SQL dotazu: " . mysqli_error($conn));
}

// 1. ROZDĚLENÍ DAT
$poptavky_ceny = [];
$zadosti_vzorky = [];

while ($row = mysqli_fetch_assoc($result)) {
    if (isset($row['typ']) && $row['typ'] === 'poptavka') {
        $poptavky_ceny[] = $row;
    } else {
        $zadosti_vzorky[] = $row;
    }
}

$is_admin_session = (!empty($_SESSION['adm']) && $_SESSION['adm'] == 1);

// Pomocná funkce pro Badge vlastností
function getBadges($row) {
    $out = '<div class="d-flex gap-1" style="display: flex; gap: 3px;">';
    if(!empty($row['bio'])) $out .= '<span class="badge bg-success" style="background-color:#28a745; font-size:9px;">BIO</span>';
    if(!empty($row['vegan'])) $out .= '<span class="badge bg-info" style="background-color:#17a2b8; font-size:9px;">VGN</span>';
    if(!empty($row['bezlepek'])) $out .= '<span class="badge bg-warning text-dark" style="background-color:#ffc107; color:#000; font-size:9px;">BL</span>';
    $out .= '</div>';
    return $out;
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Správa požadavků</h2>
        <a href="index.php?add_Pozadavek=1" class="btn btn-primary">
            <i class="fa fa-plus"></i> Nový požadavek
        </a>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-info shadow-sm" style="border: 1px solid #bce8f1;">
                <div class="panel-heading" style="background-color: #d9edf7; padding: 10px;">
                    <h3 class="panel-title" style="margin:0; color: #31708f;"><i class="fa fa-money"></i> Zjištění ceny</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-condensed table-sjednocena bg-white mb-0">
                        <thead class="bg-info">
                        <tr>
                            <th style="width: 40px;">ID</th>
                            <th>Surovina</th>
                            <th>Vlastnosti</th>
                            <th style="width: 40px;"></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($poptavky_ceny as $row):
                            $is_today = (date('Y-m-d') === date('Y-m-d', strtotime($row['datumPozadavek'])));
                            ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $row['id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['surovina_nazev'] ?? 'Neznámá') ?></strong></td>
                                <td><?= getBadges($row) ?></td>
                                <td class="text-center">
                                    <?php if ($is_admin_session || $is_today): ?>
                                        <a href="javascript:void(0);" class="btn btn-danger btn-xs btn-delete-ajax" data-id="<?= $row['id'] ?>" data-table="pozadavky">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel panel-success shadow-sm" style="border: 1px solid #d6e9c6;">
                <div class="panel-heading" style="background-color: #dff0d8; padding: 10px;">
                    <h3 class="panel-title" style="margin:0; color: #3c763d;"><i class="fa fa-flask"></i> Žádosti o vzorky</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-condensed table-sjednocena bg-white mb-0">
                        <thead class="bg-success">
                        <tr>
                            <th style="width: 40px;">ID</th>
                            <th>Surovina</th>
                            <th>Zákazník</th>
                            <th style="width: 40px;"></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($zadosti_vzorky as $row):
                            $is_today = (date('Y-m-d') === date('Y-m-d', strtotime($row['datumPozadavek'])));
                            ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $row['id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['surovina_nazev'] ?? 'Neznámá') ?></strong></td>
                                <td><?= htmlspecialchars($row['zakaznik_nazev'] ?? 'Interní / Neznámý') ?></td>
                                <td class="text-center">
                                    <?php if ($is_admin_session || $is_today): ?>
                                        <a href="javascript:void(0);" class="btn btn-danger btn-xs btn-delete-ajax" data-id="<?= $row['id'] ?>" data-table="pozadavky">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>