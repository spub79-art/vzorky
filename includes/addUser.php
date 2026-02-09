<?php
include_once("./db_connect.php");

if (isset($_POST['save_user'])) {
    $jmeno   = mysqli_real_escape_string($conn, $_POST['jmeno']);
    $login   = mysqli_real_escape_string($conn, $_POST['login']);
    $heslo   = mysqli_real_escape_string($conn, $_POST['heslo']);
    $admin   = isset($_POST['admin']) ? 1 : 0;
    $vyvoj   = isset($_POST['vyvoj']) ? 1 : 0;
    $orders  = isset($_POST['orders']) ? 1 : 0;
    $kvalita = isset($_POST['kvalita']) ? 1 : 0;

    // OPRAVA: Tu musí byť tabuľka "users", nie "zaznamy"
    $sql = "INSERT INTO users (jmeno, login, heslo, admin, vyvoj, orders, kvalita) 
            VALUES ('$jmeno', '$login', '$heslo', '$admin', '$vyvoj', '$orders', '$kvalita')";

    if (mysqli_query($conn, $sql)) {
        echo "<div class='alert alert-success'>Uživateľ byl úspešně přidán.</div>";
        // Presmerovanie späť na zoznam
        echo "<script>window.location.href='index.php?Users=1';</script>";
    } else {
        echo "Chyba databáze: " . mysqli_error($conn);
    }
}
?>

<form method="post" action="">
    <div class="form-group">
        <label>Jméno:</label>
        <input type="text" name="jmeno" class="form-control" required>
    </div>
    <div class="form-group">
        <label>Login:</label>
        <input type="text" name="login" class="form-control" required>
    </div>
    <div class="form-group">
        <label>Heslo:</label>
        <input type="password" name="heslo" class="form-control" required>
    </div>
    <div class="checkbox">
        <label><input type="checkbox" name="admin"> Administrátor</label>
    </div>
    <div class="checkbox">
        <label><input type="checkbox" name="vyvoj"> Vývoj</label>
    </div>
    <div class="checkbox">
        <label><input type="checkbox" name="orders"> Objednávky</label>
    </div>
    <div class="checkbox">
        <label><input type="checkbox" name="kvalita"> Kvalita</label>
    </div>
    <button type="submit" name="save_user" class="btn btn-success">Uložiť užívateľa</button>
</form>