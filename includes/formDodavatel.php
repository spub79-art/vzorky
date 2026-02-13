<?php
include_once("db_connect.php");
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Defaultní prázdná data pro nový záznam
$data = [
    'nazev' => '',
    'kontaktni_osoba' => '',
    'email' => '',
    'telefon' => '',
    'poznamka' => ''
];

if ($id > 0) {
    $res = mysqli_query($conn, "SELECT * FROM dodavatele WHERE id = $id");
    $data = mysqli_fetch_assoc($res);
}

// Zpracování uložení
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dodavatel'])) {
    $nazev    = mysqli_real_escape_string($conn, trim($_POST['nazev']));
    $kontakt  = mysqli_real_escape_string($conn, $_POST['kontaktni_osoba']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $telefon  = mysqli_real_escape_string($conn, $_POST['telefon']);
    $poznamka = mysqli_real_escape_string($conn, $_POST['poznamka']);

    if ($id > 0) {
        $sql = "UPDATE dodavatele SET 
                nazev = '$nazev', 
                kontaktni_osoba = '$kontakt', 
                email = '$email', 
                telefon = '$telefon', 
                poznamka = '$poznamka' 
                WHERE id = $id";
    } else {
        $sql = "INSERT INTO dodavatele (nazev, kontaktni_osoba, email, telefon, poznamka) 
                VALUES ('$nazev', '$kontakt', '$email', '$telefon', '$poznamka')";
    }

    if (mysqli_query($conn, $sql)) {
        // Návrat o složku výš na index
        echo "<script>window.location.href='../index.php?Dodavatele=1';</script>";
        exit;
    }
}
?>

<form method="POST" action="includes/formDodavatel.php<?= ($id > 0 ? '?id='.$id : '') ?>">
    <div class="modal-header" style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">
            <i class="fa fa-truck"></i>
            <?= ($id > 0 ? 'Editace: ' . htmlspecialchars($data['nazev']) : 'Nový dodavatel') ?>
        </h4>
    </div>
    <div class="modal-body">
        <div class="form-group">
            <label>Název firmy / Dodavatele</label>
            <input type="text" name="nazev" class="form-control" value="<?= htmlspecialchars($data['nazev']) ?>" required>
        </div>

        <div class="row">
            <div class="col-md-6 form-group">
                <label>Kontaktní osoba</label>
                <input type="text" name="kontaktni_osoba" class="form-control" value="<?= htmlspecialchars($data['kontaktni_osoba']) ?>">
            </div>
            <div class="col-md-6 form-group">
                <label>Telefon</label>
                <input type="text" name="telefon" class="form-control" value="<?= htmlspecialchars($data['telefon']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data['email']) ?>">
        </div>

        <div class="form-group">
            <label>Interní poznámka</label>
            <textarea name="poznamka" class="form-control" rows="3"><?= htmlspecialchars($data['poznamka']) ?></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Zrušit</button>
        <button type="submit" name="save_dodavatel" class="btn btn-primary">
            <i class="fa fa-save"></i> Uložit dodavatele
        </button>
    </div>
</form>