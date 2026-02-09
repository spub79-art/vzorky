<?php
include_once("./db_connect.php");
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if(isset($_POST['username']) && isset($_POST['password'])){

    $myusername = mysqli_real_escape_string($conn, $_POST['username']);
    $mypassword = mysqli_real_escape_string($conn, $_POST['password']);

    // Přidány nové sloupce do SELECTu
    $sql = "SELECT id, jmeno, login, heslo, admin, vyvoj, orders, kvalita 
            FROM users 
            WHERE login = '$myusername' AND heslo = '$mypassword' 
            LIMIT 1";

    $result = mysqli_query($conn, $sql);

    if($row = mysqli_fetch_assoc($result)) {
        // Základní údaje
        $_SESSION["uid"] = $row['id'];
        $_SESSION["username"] = $row['jmeno'];
        $_SESSION["loginname"] = $row['login'];

        // Uložení rolí (přetypování na boolean/int pro snazší práci)
        $_SESSION["adm"] = (int)$row['admin'];
        $_SESSION["vyvoj"] = (int)$row['vyvoj'];
        $_SESSION["orders"] = (int)$row['orders'];
        $_SESSION["kvalita"] = (int)$row['kvalita'];

        header("Location: index.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'>Neplatné jméno nebo heslo!</div>";
    }

} elseif (!empty($_SESSION["username"])){
    // Zobrazení stavu přihlášení
    echo "<p>Přihlášený uživatel: <strong> " . $_SESSION["username"] ."</strong></p>";

    $roles = array();
    if(!empty($_SESSION["adm"])) $roles[] = "Admin";
    if(!empty($_SESSION["vyvoj"])) $roles[] = "Vývoj";
    if(!empty($_SESSION["orders"])) $roles[] = "Orders";
    if(!empty($_SESSION["kvalita"])) $roles[] = "Kvalita";

    if(!empty($roles)){
        echo "Role: <strong style='color:red;'>" . implode(", ", $roles) . "</strong>";
    }
} else {
    ?>
    <form action="#" method="POST" class="login-form">
        <div class="form-group">
            <label for="username">Přihlašovací jméno:</label>
            <input id="username" type="text" name="username" class="form-control" />
        </div>
        <div class="form-group">
            <label for="password">Heslo:</label>
            <input id="password" type="password" name="password" class="form-control" />
        </div>
        <input type="submit" name="submit" value="Přihlásit se" class="btn btn-primary" />
    </form>
<?php } ?>