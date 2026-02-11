<?php
include_once("includes/db_connect.php");

$queryZakaznici = mysqli_query($conn, "SELECT id, nazev FROM zakaznik ORDER BY nazev ASC");
$zakazniciList = mysqli_fetch_all($queryZakaznici, MYSQLI_ASSOC);

$fields = [
    'typ'            => ['label' => 'Typ',           'type' => 'text'],
    'id_surovina'    => ['label' => 'Surovina',       'type' => 'hidden'],
    'id_zakaznik'    => ['label' => 'Zákazník',       'type' => 'radio'],
    'bio'            => ['label' => 'BIO',            'type' => 'checkbox'],
    'bezlepek'       => ['label' => 'Bezlepkové',     'type' => 'checkbox'],
    'vegan'          => ['label' => 'Vegan',          'type' => 'checkbox'],
    'kosher'         => ['label' => 'Kosher',         'type' => 'checkbox'],
    'datumPozadavek' => ['label' => 'Datum',          'type' => 'date'],
    'Mnozstvi'       => ['label' => 'Množství',       'type' => 'number'],
    'mj'             => ['label' => 'Jednotka',       'type' => 'text']
];

if (isset($_POST['save_user'])) {
    $surovina_nazev = trim(mysqli_real_escape_string($conn, $_POST['nazev_text'] ?? ''));
    $typ = $_POST['typ'] ?? 'vyvoj';
    $id_surovina = 0;

    if (!empty($surovina_nazev)) {
        $checkSurovina = mysqli_query($conn, "SELECT id FROM suroviny WHERE TRIM(LOWER(nazev)) = TRIM(LOWER('$surovina_nazev')) LIMIT 1");
        if (mysqli_num_rows($checkSurovina) > 0) {
            $sRow = mysqli_fetch_assoc($checkSurovina);
            $id_surovina = $sRow['id'];
        } else {
            mysqli_query($conn, "INSERT INTO suroviny (nazev) VALUES ('$surovina_nazev')");
            $id_surovina = mysqli_insert_id($conn);
        }
    }

    $data = [];
    foreach ($fields as $col => $info) {
        if ($info['type'] === 'checkbox') {
            $data[$col] = isset($_POST[$col]) ? 1 : 0;
        } elseif ($col === 'id_surovina') {
            $data[$col] = $id_surovina;
        } elseif ($col === 'id_zakaznik') {
            $data[$col] = ($typ === 'poptavka') ? 0 : (int)($_POST['id_zakaznik'] ?? 0);
        } elseif ($col === 'Mnozstvi' && $typ === 'poptavka') {
            $data[$col] = 0;
        } else {
            $val = $_POST[$col] ?? '';
            $data[$col] = mysqli_real_escape_string($conn, $val);
        }
    }

    $cols = implode(", ", array_keys($data));
    $vals = "'" . implode("', '", array_values($data)) . "'";
    $sql = "INSERT INTO pozadavky ($cols) VALUES ($vals)";

    if (mysqli_query($conn, $sql)) {
        echo "<div class='alert alert-success'>Uloženo</div>";
        echo "<script>setTimeout(() => { window.location.href='index.php?Pozadavek=1'; }, 1000);</script>";
    }
}
?>

<script>
    $(document).ready(function(){
        function toggleMode() {
            let mode = $('input[name="typ"]:checked').val();
            if (mode === 'poptavka') {
                $('.customer-section, .amount-section').hide();
                $('input[name="id_zakaznik"], input[name="Mnozstvi"]').prop('required', false);
            } else {
                $('.customer-section, .amount-section').show();
                $('input[name="id_zakaznik"], input[name="Mnozstvi"]').prop('required', true);
            }
        }

        $(document).on('change', 'input[name="typ"]', toggleMode);
        toggleMode();

        $('.customer-list .list-group-item').on('click', function() {
            $(this).find('input[type="radio"]').prop('checked', true);
            $('.customer-list .list-group-item').removeClass('active-selection');
            $(this).addClass('active-selection');
        });

        $(document).on("keyup input", '.ajax-search', function(){
            let container = $(this).closest(".search-box");
            let query = $(this).val();
            if(query.length >= 2){
                $.ajax({
                    url: "includes/search_backend.php",
                    method: "GET",
                    data: { term: query, table: "suroviny" },
                    dataType: "json",
                    success: function(data){
                        let html = "";
                        $.each(data, function(i, item){
                            html += '<a href="#" class="list-group-item list-group-item-action result-item" data-id="'+item.id+'">' + item.label + '</a>';
                        });
                        container.find(".result-list").html(html).show();
                    }
                });
            } else {
                container.find(".result-list").hide();
            }
        });

        $(document).on("click", '.result-item', function(e){
            e.preventDefault();
            let box = $(this).closest(".search-box");
            box.find(".ajax-search").val($(this).text().trim());
            box.find("input[name='id_surovina']").val($(this).data("id"));
            $(".result-list").hide();
        });
    });
</script>

<div class="container-fluid">
    <form method="post" action="">

        <div class="row mb-4">
            <div class="col-12">
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="typ" id="typ_poptavka" value="poptavka" autocomplete="off">
                    <label class="btn btn-outline-primary py-3" for="typ_poptavka">
                        <i class="fa fa-search-dollar"></i> 1. DOTAZ NA CENU
                    </label>

                    <input type="radio" class="btn-check" name="typ" id="typ_vyvoj" value="vyvoj" autocomplete="off" checked>
                    <label class="btn btn-outline-success py-3" for="typ_vyvoj">
                        <i class="fa fa-flask"></i> 2. VÝVOJ RECEPTURY
                    </label>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card p-3">
                    <div class="search-box mb-3">
                        <label>Název suroviny</label>
                        <input type="text" name="nazev_text" class="form-control ajax-search" autocomplete="off" required>
                        <input type="hidden" name="id_surovina">
                        <div class="result-list list-group" style="position:absolute; width:100%; z-index:100;"></div>
                    </div>

                    <div class="amount-section mb-3">
                        <label>Množství</label>
                        <div class="input-group">
                            <input type="number" name="Mnozstvi" class="form-control" step="any">
                            <select name="mj" class="form-select">
                                <option value="Kg">Kg</option><option value="l">l</option><option value="g">g</option>
                            </select>
                        </div>
                    </div>

                    <label class="fw-bold mb-2">Požadovaná kvalita</label>
                    <div class="d-flex flex-wrap gap-3 p-2 border rounded bg-light">
                        <?php foreach(['bio'=>'BIO','bezlepek'=>'Bezlepkové','vegan'=>'Vegan','kosher'=>'Kosher'] as $n=>$l): ?>
                            <div class="form-check form-switch d-flex align-items-center mb-0">
                                <input class="form-check-input" type="checkbox" name="<?=$n?>" id="<?=$n?>S">
                                <label class="form-check-label ms-2" for="<?=$n?>S"><?=$l?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6 customer-section">
                <div class="card p-3">
                    <label class="fw-bold mb-2">Vyberte zákazníka</label>
                    <div class="customer-list border rounded mb-3" style="max-height: 250px; overflow-y: auto;">
                        <div class="list-group list-group-flush">
                            <?php foreach ($zakazniciList as $z): ?>
                                <div class="list-group-item">
                                    <input class="btn-check" type="radio" name="id_zakaznik" id="z<?=$z['id']?>" value="<?=$z['id']?>">
                                    <label class="form-check-label w-100 cursor-pointer" for="z<?=$z['id']?>">
                                        <?=$z['nazev']?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <label>Datum požadavku</label>
                    <input type="date" name="datumPozadavek" class="form-control" value="<?=date('Y-m-d')?>">
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button type="submit" name="save_user" class="btn btn-success btn-lg">Uložit požadavek</button>
        </div>
    </form>
</div>