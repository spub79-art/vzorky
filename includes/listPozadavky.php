<?php
// TOTO DOČASNĚ ZAPNE VÝPIS CHYB, ABYCHOM VIDĚLI PŘESNÝ ŘÁDEK
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once("./db_connect.php");

// SQL dotaz s JOINem - zkontroluj, zda se tabulky a sloupce jmenují přesně takto
$sql = "SELECT p.*, z.nazev AS zakaznik_nazev 
        FROM pozadavky p 
        LEFT JOIN zakaznik z ON p.id_zakaznik = z.id 
        ORDER BY p.datumPozadavek DESC";

$result = mysqli_query($conn, $sql);

// Pokud selže SQL, vypíše to přesně proč (např. neznámý sloupec)
if (!$result) {
    die("Chyba v SQL dotazu: " . mysqli_error($conn));
}
?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Přehled požadavků</h3>
        <a href="index.php?addPozadavek=1" class="btn btn-success btn-sm">Nový požadavek</a>
    </div>

    <table id="editableTable" class="table table-bordered table-hover shadow-sm bg-white">
        <thead class="table-light">
        <tr>
            <th>Datum</th>
            <th>Surovina</th>
            <th>Vlastnosti</th>
            <th>Zákazník</th>
            <th>Množství</th>
            <th class="text-end">Akce</th>
        </tr>
        </thead>
        <tbody>
        <?php
        // Použijeme fetch_assoc v cyklu
        while($row = mysqli_fetch_assoc($result)):
            // Ošetření datumu, aby nezpůsobilo chybu, pokud je špatně zapsán
            $datum = (!empty($row['datumPozadavek'])) ? date('d.m.Y', strtotime($row['datumPozadavek'])) : '-';
            ?>
            <tr>
                <td><?= $datum ?></td>
                <td><strong><?= htmlspecialchars($row['nazev'] ?? '') ?></strong></td>
                <td>
                    <div class="d-flex gap-1">
                        <?php if(!empty($row['bio'])): ?><span class="badge bg-success">BIO</span><?php endif; ?>
                        <?php if(!empty($row['bezlepek'])): ?><span class="badge bg-warning text-dark">BL</span><?php endif; ?>
                        <?php if(!empty($row['vegan'])): ?><span class="badge bg-info">V</span><?php endif; ?>
                        <?php if(!empty($row['kosher'])): ?><span class="badge bg-secondary">K</span><?php endif; ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($row['zakaznik_nazev'] ?? 'Neznámý') ?></td>
                <td>
                    <?= number_format((float)($row['Mnozstvi'] ?? 0), 2, ',', ' ') ?>
                    <?= htmlspecialchars($row['mj'] ?? '') ?>
                </td>
                <td class="text-end">
                    <div class="btn-group">
                        <a href="index.php?editPozadavek=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-edit"></i>
                        </a>

                        <?php
                        $today = date('Y-m-d');
                        $rowDate = (!empty($row['datumPozadavek'])) ? date('Y-m-d', strtotime($row['datumPozadavek'])) : '';

                        // Získání role ze session (předpokládáme, že ji tam máš)
                        $userRole = $_SESSION['role'] ?? 'user';

                        // Logika: Smazat jde, pokud je to DNES NEBO pokud jsem ADMIN
                        if ($today === $rowDate || $userRole === 'admin'): ?>
                            <a href="includes/delete_logic.php?id=<?= $row['id'] ?>&table=pozadavky"
                               class="btn btn-outline-danger btn-sm btn-delete-ajax"
                               onclick="return confirm('Opravdu smazat tento požadavek?')">
                                <i class="fa fa-trash"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm" disabled title="Historii může mazat pouze admin">
                                <i class="fa fa-lock"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
