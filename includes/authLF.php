<?php
set_include_path($_SERVER['DOCUMENT_ROOT'] . 'includes/');
include_once("db_connect.php");

// Pojistka pro případ, že konstanta ještě není zapsaná v db_connect.php
if (!defined('DB_TBL_USERS')) define('DB_TBL_USERS', 'users');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error_msg = "";

if(isset($_POST['username']) && isset($_POST['password'])){

    $myusername = mysqli_real_escape_string($conn, $_POST['username']);
    $mypassword = mysqli_real_escape_string($conn, $_POST['password']);

    // ZMĚNA 1: Přidán chybějící sloupec 'cumil' do SELECTu
    // ZMĚNA 2: Tabulka se nyní bere dynamicky z naší konstanty DB_TBL_USERS
    $sql = "SELECT id, jmeno, login, heslo, admin, vyvoj, orders, kvalita, cumil 
            FROM " . DB_TBL_USERS . " 
            WHERE login = '$myusername' AND heslo = '$mypassword' 
            LIMIT 1";

    $result = mysqli_query($conn, $sql);

    if($row = mysqli_fetch_assoc($result)) {
        // Základní údaje
        $_SESSION["uid"] = $row['id'];
        $_SESSION["username"] = $row['jmeno'];
        $_SESSION["loginname"] = $row['login'];

        // Uložení rolí
        $_SESSION["adm"] = (int)$row['admin'];
        $_SESSION["vyvoj"] = (int)$row['vyvoj'];
        $_SESSION["orders"] = (int)$row['orders'];
        $_SESSION["kvalita"] = (int)$row['kvalita'];
        $_SESSION['cumil'] = (int)$row['cumil'];

        header("Location: index.php");
        exit();
    } else {
        $error_msg = "Neplatné přihlašovací jméno nebo heslo!";
    }
}

// ======================================================================
// POKUD UŽIVATEL NENÍ PŘIHLÁŠENÝ: Vykreslíme plnohodnotnou stránku a STOP
// ======================================================================
if (empty($_SESSION["username"])) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="utf-8">
        <title>Přihlášení | Lifefood</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="styles/vzorky.css">
    </head>
    <body style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); height: 100vh; margin: 0; display: flex; flex-direction: column;">

    <?php if (defined('IS_DEV') && IS_DEV): ?>
        <div style="background-color: #d9534f; color: #fff; text-align: center; padding: 10px; font-weight: bold; font-size: 14px; letter-spacing: 2px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); width: 100%;">
            <i class="glyphicon glyphicon-warning-sign"></i> POZOR: VÝVOJOVÉ PROSTŘEDÍ (DEV) — POUZE PRO TESTOVÁNÍ <i class="glyphicon glyphicon-warning-sign"></i>
        </div>
    <?php endif; ?>

    <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center;">
        <div class="lf-login-container"> <div class="lf-login-header">
                <h2><i class="glyphicon glyphicon-leaf"></i> Nákup & Vývoj</h2>
                <p>Přihlášení do systému</p>
            </div>

            <?php if(!empty($error_msg)): ?>
                <div class="alert alert-danger" style="border-radius: 6px; font-size: 13px; text-align: center; padding: 10px;">
                    <i class="glyphicon glyphicon-exclamation-sign"></i> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="login-form">
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                        <input id="username" type="text" name="username" class="form-control" placeholder="Přihlašovací jméno" required autofocus />
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                        <input id="password" type="password" name="password" class="form-control" placeholder="Heslo" required />
                    </div>
                </div>

                <button type="submit" name="submit" class="btn btn-primary btn-block btn-login">Přihlásit se</button>
            </form>
        </div>
    </div>
    </body>
    </html>
    <?php
    // ZÁSADNÍ: Tímto se zastaví vykonávání. index.php už se vůbec nenačte.
    exit();
}
?>