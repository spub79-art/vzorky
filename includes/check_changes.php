<?php
// Soubor: includes/check_changes.php
$file = 'last_change.txt';
if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo time();
}
?>