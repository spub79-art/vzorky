<?php
// V HEAD sekci
if ($has_internal_access) {
    $scripts = [];
    if ($is_adm || $is_vyvoj)  $scripts[] = "js/Fdittable.js";
    if ($is_adm || $is_vyvoj)  $scripts[] = "js/Pdittable.js";
    if ($is_adm || $is_orders) $scripts[] = "js/Sdittable.js";
    if ($is_adm || $is_kvalita) $scripts[] = "js/Udittable.js";

    // Načteme jen ty, které jsou relevantní pro aktuální stránku
    foreach ($scripts as $script) {
        // Logika pro párování stránky a skriptu (např. Fdittable jen na Folie)
        if (strpos($script, substr($page, 0, 1)) !== false) {
            echo "<script src='$script'></script>";
        }
    }
}
?>