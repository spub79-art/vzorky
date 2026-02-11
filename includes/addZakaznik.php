<?php
include_once("includes/db_connect.php");

// Konfigurace pro tabulku zakaznici
$fields = [
    'nazev' => ['label' => 'Název zákazníka', 'type' => 'text', 'req' => true]
];

if (isset($_POST['save_customer'])) {
    // Příprava dat (očištění od SQL Injection)
    $nazev = mysqli_real_escape_string($conn, $_POST['nazev'] ?? '');

    if (!empty($nazev)) {
        $sql = "INSERT INTO zakaznik (nazev) VALUES ('$nazev')";

        if (mysqli_query($conn, $sql)) {
            echo "<div class='alert alert-success'>Zákazník '$nazev' byl úspěšně přidán.</div>";
            // Přesměrování zpět (např. na seznam zákazníků nebo na vytvoření požadavku)
            echo "<script>setTimeout(() => { window.location.href='index.php?Customers=1'; }, 1500);</script>";
        } else {
            echo "<div class='alert alert-danger'>Chyba při ukládání: " . mysqli_error($conn) . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Název zákazníka nesmí být prázdný.</div>";
    }
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Nový zákazník</h3>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <?php foreach ($fields as $name => $info): ?>
                    <div class="form-group mb-3">
                        <label class="form-label">**<?= $info['label'] ?>**:</label>
                        <input type="<?= $info['type'] ?>"
                               name="<?= $name ?>"
                               class="form-control form-control-lg"
                               placeholder="Zadejte název firmy nebo jméno..."
                               <?= ($info['req'] ?? false) ? 'required' : '' ?>>
                    </div>
                <?php endforeach; ?>

                <div class="mt-4">
                    <button type="submit" name="save_customer" class="btn btn-success">
                        <i class="fa fa-save"></i> Uložit zákazníka
                    </button>
                    <a href="index.php" class="btn btn-secondary">Zpět</a>
                </div>
            </form>
        </div>
    </div>
</div>