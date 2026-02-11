<?php
// Načtení surovin s počtem jejich použití (pro zajímavost)
include_once("includes/db_connect.php");

$sql = "SELECT s.*, 
               (SELECT COUNT(p.id) FROM pozadavky p WHERE p.id_surovina = s.id) as pouzito_krat
        FROM suroviny s 
        ORDER BY s.nazev ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Chyba v SQL dotazu: " . mysqli_error($conn));
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3 text-dark">
        <h2 class="mb-0"><i class="fa fa-leaf text-success me-2"></i>Knihovna surovin</h2>
        <span class="badge bg-secondary">Celkem: <?= mysqli_num_rows($result) ?></span>
    </div>

    <div class="table-responsive shadow-sm border rounded">
        <table id="tableSuroviny" class="table table-hover align-middle bg-white table-sjednocena mb-0">
            <thead class="table-dark">
            <tr>
                <th style="width: 80px;" class="text-center">ID</th>
                <th>Název suroviny</th>
                <th class="text-center">Vlastnosti</th>
                <th class="text-center">Počet požadavků</th>
            </tr>
            <tr class="search-row" style="background-color: #f8f9fa;">
                <th><input type="text" class="form-control form-control-sm col-search" placeholder="ID..."></th>
                <th><input type="text" class="form-control form-control-sm col-search" placeholder="Hledat název..."></th>
                <th><input type="text" class="form-control form-control-sm col-search" placeholder="Hledat vlastnost..."></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td class="text-center text-muted"><?= $row['id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['nazev']) ?></strong></td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <?php if(!empty($row['bio'])): ?><span class="badge bg-success">BIO</span><?php endif; ?>
                            <?php if(!empty($row['vegan'])): ?><span class="badge bg-info">VGN</span><?php endif; ?>
                            <?php if(!empty($row['bezlepek'])): ?><span class="badge bg-warning text-dark">BL</span><?php endif; ?>
                            <?php if(empty($row['bio']) && empty($row['vegan']) && empty($row['bezlepek'])): ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border"><?= $row['pouzito_krat'] ?>×</span>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function() {
        var tableID = '#tableSuroviny';

        // Díky retrieve: true v index.php tohle teď projde hladce
        var tableSuroviny = $(tableID).DataTable({
            "retrieve": true, // Získáme instanci vytvořenou v index.php
            "paging": false,
            "autoWidth": false,
            "order": [[1, "asc"]], // Suroviny chceme podle abecedy
            "dom": 't'             // Schováme globální search, máme vlastní
        });

        // Aktivace vyhledávání v hlavičce
        $(tableID + ' .col-search').on('keyup change', function() {
            var index = $(this).closest('th').index();
            tableSuroviny.column(index).search(this.value).draw();
        });

        // Zamezení odeslání při Enteru
        $(tableID + ' .col-search').on('keydown', function(e) {
            if (e.keyCode == 13) { e.preventDefault(); return false; }
        });
    });
</script>