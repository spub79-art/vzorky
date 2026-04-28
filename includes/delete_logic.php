<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once("db_connect.php");
header('Content-Type: text/html; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$table_raw = isset($_GET['table']) ? $_GET['table'] : '';
$table = strtolower(trim($table_raw));
$redirect_param = isset($_GET['redirect']) ? $_GET['redirect'] : '';

if (!$conn) { die("Chyba: Databáze není připojena."); }
$table = mysqli_real_escape_string($conn, $table);
$is_adm = (isset($_SESSION['adm']) && $_SESSION['adm'] == 1);

// --- LOGIKA PRO POŽADAVKY ---
if ($table === 'pozadavky') {
    $res = mysqli_query($conn, "SELECT id_surovina, datumPozadavek FROM pozadavky WHERE id = $id");
    $row = mysqli_fetch_assoc($res);

    if ($row) {
        $id_surovin_ke_kontrole = (int)$row['id_surovina'];

        // Ochrana historie
        $isToday = (date('Y-m-d') === date('Y-m-d', strtotime($row['datumPozadavek'])));
        if (!$is_adm && !$isToday) { die("Historické záznamy smí mazat pouze administrátor."); }

        if (mysqli_query($conn, "DELETE FROM pozadavky WHERE id = $id")) {
            if ($id_surovin_ke_kontrole > 0) {
                $checkNext = mysqli_query($conn, "SELECT id FROM pozadavky WHERE id_surovina = $id_surovin_ke_kontrole LIMIT 1");
                if (mysqli_num_rows($checkNext) === 0) {
                    mysqli_query($conn, "DELETE FROM suroviny WHERE id = $id_surovin_ke_kontrole");
                }
            }
            echo "OK";
            exit;
        }
    }
}
// --- LOGIKA PRO OSTATNÍ TABULKY (Zákazníci atd.) ---
else if (!empty($table) && $id > 0) {
    // Bezpečnostní pojistka: zákazníka smažeme jen když nemá požadavky
    if ($table === 'zakaznici') {
        $check = mysqli_query($conn, "SELECT id_pozadavek FROM pozadavky_zakaznici WHERE id_zakaznik = $id LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            die("Nelze smazat zákazníka s aktivními požadavky.");
        }
    }

    // BEZPEČNOSTNÍ POJISTKA: dodavatele smažeme jen když nedodal žádné vzorky/nabídky
    if ($table === 'dodavatele') {
        $check = mysqli_query($conn, "SELECT id FROM pozadavky_nabidky WHERE id_dodavatel = $id LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            die("Nelze smazat dodavatele, který je vázán k existujícím nabídkám či vzorkům.");
        }
    }

    if (mysqli_query($conn, "DELETE FROM `$table` WHERE id = $id")) {
        // Pokud máme parametr pro přesměrování, vrátíme se na index
        if (!empty($redirect_param)) {
            header("Location: ../index.php?$redirect_param=1");
            exit;
        }
        echo "OK";
        exit;
    }
}
echo "Chyba při zpracování požadavku.";
?>