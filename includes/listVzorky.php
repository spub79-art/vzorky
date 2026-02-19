<?php
// index.php už db_connect a session má, tady jen ověříme přístup
if (empty($_SESSION['username'])) exit;
?>

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="glyphicon glyphicon-compressed"></i> Přehled doručených vzorků k testování</h3>
    </div>
    <div class="panel-body">
        <div class="alert alert-info small">Zde vidíš položky, u kterých fyzicky dorazil vzorek a Vývoj je má aktuálně "na stole".</div>
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>Surovina</th>
                <th>Dodavatel</th>
                <th>Šarže</th>
                <th>Množství</th>
                <th>Status</th>
                <th>Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT pn.*, s.nazev as surovina_nazev, d.nazev as dodavatel_nazev, cs.nazev as status_text
                        FROM pozadavky_nabidky pn
                        JOIN pozadavky p ON pn.id_pozadavek = p.id
                        JOIN suroviny s ON p.id_surovina = s.id
                        LEFT JOIN dodavatele d ON pn.id_dodavatel = d.id
                        JOIN ciselnik_statusu cs ON pn.id_status = cs.id
                        WHERE pn.id_status IN (10, 4)
                        ORDER BY pn.updated_at DESC";
            $res = mysqli_query($conn, $sql);
            if (mysqli_num_rows($res) == 0) echo "<tr><td colspan='7' class='text-center text-muted'>Aktuálně nejsou žádné vzorky k testování.</td></tr>";
            while($row = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><?= $row['id_pozadavek'] ?></td>
                    <td><strong><?= htmlspecialchars($row['surovina_nazev']) ?></strong></td>
                    <td><?= htmlspecialchars($row['dodavatel_nazev']) ?></td>
                    <td><span class="label label-default"><?= htmlspecialchars($row['sarze']) ?></span></td>
                    <td><?= htmlspecialchars($row['pozadovane_mnozstvi']) ?></td>
                    <td><span class="label label-info"><?= htmlspecialchars($row['status_text']) ?></span></td>
                    <td>
                        <a href="index.php?Pozadavek=1" class="btn btn-xs btn-primary">Přejít na nástěnku</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>