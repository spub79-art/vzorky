<?php
include_once("./db_connect.php");
// Načtení seznamu zákazníků pro radio list
$queryZakaznici = mysqli_query($conn, "SELECT id, nazev FROM zakaznik ORDER BY nazev ASC");
$zakazniciList = mysqli_fetch_all($queryZakaznici, MYSQLI_ASSOC);
$fields = [
    'nazev'          => ['label' => 'Název suroviny', 'type' => 'ajax', 'source' => 'suroviny'],
    'id_zakaznik'    => ['label' => 'Zákazník',       'type' => 'ajax', 'source' => 'zakaznik'],
    'bio'            => ['label' => 'BIO',            'type' => 'checkbox'],
    'bezlepek'       => ['label' => 'Bezlepkové',     'type' => 'checkbox'],
    'vegan'          => ['label' => 'Vegan',          'type' => 'checkbox'],
    'kosher'         => ['label' => 'Kosher',         'type' => 'checkbox'],
    'datumPozadavek' => ['label' => 'Datum',          'type' => 'date'],
    'Mnozstvi'       => ['label' => 'Množství',       'type' => 'number'],
    'mj'             => ['label' => 'Jednotka',       'type' => 'text']
];

if (isset($_POST['save_user'])) {
    $data = [];

    // 1. Získání názvu suroviny z TEXTOVÉHO pole
    $surovina_nazev = mysqli_real_escape_string($conn, $_POST['nazev_text'] ?? '');

    if (!empty($surovina_nazev)) {
        mysqli_query($conn, "INSERT IGNORE INTO suroviny (nazev) VALUES ('$surovina_nazev')");
    }

    // 2. Sběr dat pro INSERT
    foreach ($fields as $col => $info) {
        if ($info['type'] === 'checkbox') {
            $data[$col] = isset($_POST[$col]) ? 1 : 0;
        } elseif ($col === 'nazev') {
            // ZDE JE OPRAVA: Místo $_POST['nazev'] bereme $surovina_nazev
            $data[$col] = $surovina_nazev;
        } else {
            $val = $_POST[$col] ?? '';
            $data[$col] = mysqli_real_escape_string($conn, $val);
        }
    }

    $cols = implode(", ", array_keys($data));
    $vals = "'" . implode("', '", array_values($data)) . "'";
    $sql = "INSERT INTO pozadavky ($cols) VALUES ($vals)";

    if (mysqli_query($conn, $sql)) {
        echo "<div class='alert alert-success shadow'>Požadavek úspěšně uložen!</div>";
        echo "<script>setTimeout(() => { window.location.href='index.php?Pozadavek=1'; }, 1000);</script>";
    } else {
        // Pomocník pro debugování - pokud to stále nepíše, tohle ti řekne proč
        echo "<div class='alert alert-danger'>Chyba: " . mysqli_error($conn) . "</div>";
    }
}
?>
<script>
    $(document).ready(function(){
        // Vyhledávání surovin - reaguje pouze na pole s data-source="suroviny"
        $(document).on("keyup input", '.ajax-search[data-source="suroviny"]', function(){
            let inputField = $(this);
            let container = inputField.closest(".search-box");
            let query = inputField.val();
            let sourceTable = inputField.data("source");

            if(query.length >= 2){ // Hledáme až od 2 znaků (např. "Ma")
                $.ajax({
                    url: "includes/search_backend.php",
                    method: "GET",
                    data: { term: query, table: sourceTable },
                    success: function(data){
                        console.log("AJAX data přijata:", data); // Debug v konzoli
                        container.find(".result-list").html(data).show();
                    }
                });
            } else {
                container.find(".result-list").empty().hide();
            }
        });

        // Výběr suroviny ze seznamu
        $(document).on("click", '.result-list .list-group-item-action', function(e){
            e.preventDefault();
            let text = $(this).text().trim();
            let container = $(this).closest(".search-box");

            container.find(".ajax-search").val(text);
            container.find(".result-list").empty().hide();
        });

        // Zavření při kliknutí mimo
        $(document).click(function(e) {
            if (!$(e.target).closest('.search-box').length) {
                $(".result-list").empty().hide();
            }
        });
    });
</script>

<div class="container-fluid">
    <form method="post" action="">
        <div class="row">
            <div class="col-md-6">
                <div class="form-card shadow-sm h-100">
                    <h5 class="form-card-title text-primary">Informace o surovině</h5>

                    <div class="search-box mb-3">
                        <label class="form-label">Název suroviny:</label>
                        <input type="text" name="nazev_text" class="form-control ajax-search"
                               placeholder="Hledat surovinu..." data-source="suroviny" autocomplete="off" required>
                        <input type="hidden" name="nazev" class="target-id">
                        <div class="result-list list-group"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Množství a jednotka:</label>
                        <div class="input-group flex-nowrap">
                            <input type="number" name="Mnozstvi" class="form-control" placeholder="0.00" step="any" required>
                            <select name="mj" class="form-select mj-select">
                                <option value="Kg">Kg</option>
                                <option value="l">l</option>
                                <option value="ml">ml</option>
                                <option value="g">g</option>
                                <option value="ks">ks</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mt-2">
                        <div class="col-6 col-sm-3">
                            <div class="form-check form-switch bio-switch-container">
                                <input class="form-check-input bio-switch" type="checkbox" name="bio" id="bioS">
                                <label class="form-check-label" for="bioS">BIO</label>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="form-check form-switch bio-switch-container">
                                <input class="form-check-input bio-switch" type="checkbox" name="bezlepek" id="bezlepekS">
                                <label class="form-check-label" for="bezlepekS">Bezlepkové</label>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="form-check form-switch bio-switch-container">
                                <input class="form-check-input bio-switch" type="checkbox" name="vegan" id="veganS">
                                <label class="form-check-label" for="veganS">Vegan</label>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="form-check form-switch bio-switch-container">
                                <input class="form-check-input bio-switch" type="checkbox" name="kosher" id="kosherS">
                                <label class="form-check-label" for="kosherS">Kosher</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-card shadow-sm h-100">
                    <h5 class="form-card-title text-success">Zákazník a termín</h5>

                    <label class="form-label">Vyberte zákazníka:</label>
                    <div class="customer-select-container border rounded mb-3" style="max-height: 200px; overflow-y: auto; background: #fff;">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($zakazniciList as $z): ?>
                                <li class="list-group-item">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="id_zakaznik"
                                               id="zak_<?= $z['id'] ?>" value="<?= $z['id'] ?>" required>
                                        <label class="form-check-label d-block cursor-pointer" for="zak_<?= $z['id'] ?>">
                                            <?= htmlspecialchars($z['nazev']) ?>
                                        </label>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <label class="form-label">Datum požadavku:</label>
                            <input type="date" name="datumPozadavek" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button type="submit" name="save_user" class="btn btn-primary btn-lg px-5 shadow">
                <i class="fa fa-save"></i> Uložit požadavek
            </button>
        </div>
    </form>
</div>