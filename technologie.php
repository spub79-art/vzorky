<?php
include_once("includes/db_connect.php");
session_start();

if (empty($_SESSION['vyvoj']) && empty($_SESSION['adm'])) {
    die("Přístup povolen pouze pro oddělení Vývoje.");
}

include_once("header.php"); // Předpokládám, že máte nějaký header s Bootstrapem
?>

    <div class="container" style="margin-top: 20px;">
        <?php include_once("includes/listTechnologie.php"); ?>
    </div>

<?php include_once("footer.php"); ?>