<?php
session_start();
session_destroy();
// Musíme vyskočit ze složky includes/ zpět do rootu
header('Location: ../index.php');
exit;
?>