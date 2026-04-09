<?php
include_once("db_connect.php");

if (empty($_POST['id'])) exit;
$id_nabidka = (int)$_POST['id'];

// Načtení dat nabídky
$q = mysqli_query($conn, "SELECT pn.*, p.id as id_pozadavek, s.nazev as surovina_nazev, d.nazev as dodavatel_nazev 
                          FROM pozadavky_nabidky pn 
                          JOIN pozadavky p ON pn.id_pozadavek = p.id
                          JOIN suroviny s ON p.id_surovina = s.id
                          LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
                          WHERE pn.id = $id_nabidka");
$off = mysqli_fetch_assoc($q);

if (!$off) {
    echo "<div class='alert alert-danger'>Nabídka nenalezena.</div>";
    exit;
}

// Rozparsování souborů
$files_tds = []; $files_coa = []; $files_other = [];
if (!empty($off['seznam_souboru'])) {
    $pts = explode('^', $off['seznam_souboru']);
    foreach ($pts as $pt) {
        if (empty(trim($pt))) continue;
        $finfo = explode('~', $pt);
        $fname = $finfo[0];
        $ftype = $finfo[1] ?? 'other';

        if ($ftype == 'spec') $files_tds[] = $fname;
        elseif ($ftype == 'lab') $files_coa[] = $fname;
        else $files_other[] = $fname;
    }
}

if (empty($files_tds) && empty($files_coa) && empty($files_other)) {
    echo "<div class='text-muted' style='text-align:center; padding: 20px;'>K této nabídce nejsou připojeny žádné soubory.</div>";
    exit;
}

// Získáme přesnou cestu ke složce z databáze
$link_doc = trim($off['link_dokumentace']);
$dir_path = "";

if (!empty($link_doc) && strpos($link_doc, 'dir=') !== false) {
    $parsed = parse_url($link_doc);
    parse_str($parsed['query'] ?? '', $query_params);
    $dir_path = $query_params['dir'] ?? '';
}

// Nouzový fallback, kdyby odkaz v DB chyběl
if (empty($dir_path)) {
    $base_dir = (defined('IS_DEV') && IS_DEV) ? "DEVEL_DOKUMENTACE_VZORKU" : "DOKUMENTACE_VZORKU";
    $folder_req_name = $off['id_pozadavek'] . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $off['surovina_nazev']);
    $folder_off_name = $off['id'] . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', ($off['dodavatel_nazev'] ?? 'Neznamy_dodavatel'));
    $dir_path = "/$base_dir/" . rawurlencode($folder_req_name) . "/" . rawurlencode($folder_off_name);
}

// Nativní Nextcloud UI odkaz s parametrem scrollto
$safe_dl_base = "https://nextcloud.lifefood.eu/index.php/apps/files/?dir=" . $dir_path . "&scrollto=";

?>

<div style="font-size: 13px;">
    <?php if (!empty($files_tds)): ?>
        <div style="color: #337ab7; margin-bottom: 8px;"><strong>TDS (Specifikace):</strong><br>
            <?php foreach($files_tds as $f): ?>
                <div style="margin-left: 10px; padding: 3px 0;">
                    <a href="<?= $safe_dl_base . rawurlencode($f) ?>" target="_blank" style="color: #444; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                        <i class="glyphicon glyphicon-file" style="color:#337ab7;"></i> <?= htmlspecialchars($f) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($files_coa)): ?>
        <div style="color: #5cb85c; margin-bottom: 8px;"><strong>COA (Laboratoř):</strong><br>
            <?php foreach($files_coa as $f): ?>
                <div style="margin-left: 10px; padding: 3px 0;">
                    <a href="<?= $safe_dl_base . rawurlencode($f) ?>" target="_blank" style="color: #444; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                        <i class="glyphicon glyphicon-file" style="color:#5cb85c;"></i> <?= htmlspecialchars($f) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($files_other)): ?>
        <div style="color: #777; margin-bottom: 8px;"><strong>Ostatní:</strong><br>
            <?php foreach($files_other as $f): ?>
                <div style="margin-left: 10px; padding: 3px 0;">
                    <a href="<?= $safe_dl_base . rawurlencode($f) ?>" target="_blank" style="color: #444; text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                        <i class="glyphicon glyphicon-file" style="color:#777;"></i> <?= htmlspecialchars($f) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>