<?php
include_once("db_connect.php");
$id = (int)$_POST['id_nabidka'];

$res = mysqli_query($conn, "SELECT seznam_souboru FROM pozadavky_nabidky WHERE id = $id");
$row = mysqli_fetch_assoc($res);
$fStr = $row['seznam_souboru'] ?? '';

$h = ['TDS' => '', 'COA' => '', 'Other' => '', 'hasLab' => false];

if (!empty($fStr)) {
    $files = explode('^', $fStr);
    foreach ($files as $f) {
        if (empty($f)) continue;
        $p = explode('~', $f);
        $name = $p[0];
        $type = $p[1] ?? 'other';

        $line = '<div style="display:flex; justify-content:space-between; margin-bottom:2px; font-size:11px; background:#f9f9f9; padding:2px 5px; border-radius:3px;">' .
            '<span><i class="glyphicon glyphicon-file"></i> ' . htmlspecialchars($name) . '</span>' .
            '<i class="glyphicon glyphicon-remove text-danger btn-delete-file" style="cursor:pointer;" data-id="'.$id.'" data-file="'.$f.'"></i></div>';

        if ($type === 'spec') $h['TDS'] .= $line;
        elseif ($type === 'lab') { $h['COA'] .= $line; $h['hasLab'] = true; }
        else $h['Other'] .= $line;
    }
}

echo json_encode($h);