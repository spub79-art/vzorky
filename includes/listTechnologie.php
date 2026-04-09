<?php
// Připojení k DB a další potřebné includy by měly být řešeny v nadřazeném souboru (např. index.php)
// Pokud je tento soubor volán samostatně, přidej include_once("db_connect.php");
// if (session_status() === PHP_SESSION_NONE) session_start();

// Načtení aktuálních testů (Status 10 = Dorazilo, 4 = V řešení)
$sql = "SELECT tt.*, pn.sarze, s.nazev as surovina, d.nazev as dodavatel, pn.id as id_nabidka, s.id as id_sur,
               pn.id_status as nabidka_status, s.skupzbo, s.regcis
        FROM pozadavky_nabidky pn
        JOIN pozadavky p ON pn.id_pozadavek = p.id
        JOIN suroviny s ON p.id_surovina = s.id
        JOIN dodavatele d ON pn.id_dodavatel = d.id
        LEFT JOIN technologicke_testy tt ON tt.id_nabidka = pn.id
        WHERE pn.id_status IN (10, 4)
        ORDER BY s.nazev ASC, pn.datum_poptavky DESC";
$res = mysqli_query($conn, $sql);

$current_sur = "";

/**
 * Pomocná funkce pro vykreslení hravého parametru
 */
function renderParamHrave($rowId, $fieldName, $currentVal) {
    $icons = [
        'vzhled_barva' => 'glyphicon-eye-open',
        'chut_vune' => 'glyphicon-cutlery',
        'konzistence' => 'glyphicon-hand-up'
    ];
    $labels = [
        'vzhled_barva' => 'VZHLED',
        'chut_vune' => 'CHUŤ',
        'konzistence' => 'KONZ.'
    ];

    $icon = $icons[$fieldName] ?? 'glyphicon-star';
    $label = $labels[$fieldName] ?? '';
    $isSetClass = ($currentVal > 0) ? 'is-set' : '';

    ob_start(); ?>
    <div class="param-selector <?= $isSetClass ?>" title="<?= $label ?>">
        <i class="glyphicon <?= $icon ?> param-icon status-<?= $currentVal ?>"></i>
        <div class="seg-control">
            <button class="seg-btn <?= ($currentVal == 3) ? 'active-ko' : '' ?>"
                    onclick="updateParam(<?= $rowId ?>, '<?= $fieldName ?>', 3)">KO</button>
            <button class="seg-btn <?= ($currentVal == 2) ? 'active-warn' : '' ?>"
                    onclick="updateParam(<?= $rowId ?>, '<?= $fieldName ?>', 2)">VADY</button>
            <button class="seg-btn <?= ($currentVal == 1) ? 'active-ok' : '' ?>"
                    onclick="updateParam(<?= $rowId ?>, '<?= $fieldName ?>', 1)"><?= $label ?> OK</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<style>
    /* Styly pro historické bubliny */
    .history-bubbles {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
        margin-bottom: 15px;
        padding-left: 5px;
    }
    .hist-bubble {
        font-size: 10px;
        padding: 4px 8px;
        border-radius: 12px;
        color: #fff;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        cursor: help;
    }
    .hist-ok { background: #28a745; border: 1px solid #218838; }
    .hist-ko { background: #d9534f; border: 1px solid #c9302c; }
    .hist-date { opacity: 0.8; font-size: 8px; font-weight: normal; }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="page-header" style="margin-top:0;">
                <i class="glyphicon glyphicon-flask"></i> Technologická Laboratoř
            </h2>
        </div>
    </div>

    <?php
    if ($res):
    while($row = mysqli_fetch_assoc($res)):
    if ($current_sur != $row['surovina']):
    if ($current_sur != "") echo '</div>'; // Zavřít předchozí surovinu

    $current_sur = $row['surovina'];
    $id_sur_current = (int)$row['id_sur'];
    ?>
    <div class="sur-wrapper">
        <div class="sur-title-bar" style="border-bottom: 2px solid #ecf0f1; padding-bottom: 5px; margin-bottom: 10px;">
            <h3 style="margin:0; font-weight:bold; color:#2c3e50; text-transform: uppercase;">
                <?= htmlspecialchars($current_sur) ?>
            </h3>

            <div class="history-bubbles">
                <?php
                // Poddotaz pro nalezení historie této suroviny (schválené=6 nebo zamítnuté=7)
                $sql_hist = "SELECT pn.sarze, pn.id_status, DATE_FORMAT(pn.updated_at, '%d.%m.%y') as datum, 
                                                tt.vysledek_text
                                         FROM pozadavky_nabidky pn
                                         JOIN pozadavky p ON pn.id_pozadavek = p.id
                                         LEFT JOIN technologicke_testy tt ON tt.id_nabidka = pn.id
                                         WHERE p.id_surovina = $id_sur_current 
                                           AND pn.id_status IN (6, 7)
                                         ORDER BY pn.updated_at DESC LIMIT 5";

                $res_hist = mysqli_query($conn, $sql_hist);
                $has_history = false;

                if ($res_hist && mysqli_num_rows($res_hist) > 0) {
                    while ($h = mysqli_fetch_assoc($res_hist)) {
                        $has_history = true;
                        $is_ok = ($h['id_status'] == 6);
                        $b_class = $is_ok ? 'hist-ok' : 'hist-ko';
                        $b_icon = $is_ok ? 'glyphicon-ok' : 'glyphicon-remove';
                        $sarze_txt = !empty($h['sarze']) ? $h['sarze'] : 'Neznámá šarže';
                        $tooltip = htmlspecialchars($h['vysledek_text'] ?? ($is_ok ? 'Bez poznámky' : 'Zamítnuto'));

                        echo "<div class='hist-bubble $b_class' title='Poznámka z labu: $tooltip'>";
                        echo "<i class='glyphicon $b_icon'></i> $sarze_txt <span class='hist-date'>(".$h['datum'].")</span>";
                        echo "</div>";
                    }
                }

                if (!$has_history) {
                    echo "<span style='font-size:10px; color:#95a5a6; font-style:italic;'><i class='glyphicon glyphicon-time'></i> Žádné předchozí záznamy o testování.</span>";
                }
                ?>
            </div>
        </div>
        <?php endif; // Konec nového bloku suroviny

        $faze = (int)($row['faze'] ?? 1);
        $dot_color = ["", "#3498db", "#f1c40f", "#e67e22"][$faze];
        $faze_text = ["", "NOVÝ VZOREK", "KUCHYŇSKÝ TEST", "VÝROBNÍ TEST"][$faze];
        ?>

        <div class="test-row" id="test_<?= $row['id'] ?>" style="background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; padding: 10px; display: flex; gap: 15px; align-items: stretch; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">

            <div class="info-column" style="width: 150px; flex-shrink: 0; border-right: 1px solid #f0f0f0; padding-right: 10px;">
                <div class="phase-indicator" style="margin-bottom: 8px; display: flex; align-items: center; gap: 5px;">
                    <div class="dot" style="width: 10px; height: 10px; border-radius: 50%; background: <?= $dot_color ?>; box-shadow: 0 0 6px <?= $dot_color ?>;"></div>
                    <strong style="color: <?= $dot_color ?>; font-size: 10px; letter-spacing: 0.5px;"><?= $faze_text ?></strong>
                </div>
                <div class="small text-muted" style="font-size: 9px; text-transform: uppercase;">Dodavatel:</div>
                <div style="font-weight: bold; font-size: 12px; margin-bottom: 8px; color: #333; line-height: 1.2;"><?= htmlspecialchars($row['dodavatel']) ?></div>
                <div class="label label-default" style="font-size: 10px; display: inline-block; padding: 4px 6px;">ŠARŽE: <?= htmlspecialchars($row['sarze'] ?: '---') ?></div>
            </div>

            <div class="param-column" style="flex: 1; display: flex; flex-direction: column; gap: 8px; justify-content: center;">
                <?= renderParamHrave($row['id'], 'vzhled_barva', $row['vzhled_barva']) ?>
                <?= renderParamHrave($row['id'], 'chut_vune', $row['chut_vune']) ?>
                <?= renderParamHrave($row['id'], 'konzistence', $row['konzistence']) ?>
            </div>

            <div class="content-column" style="flex: 2; display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-xs btn-symptom <?= $row['stroj_lepivost'] ? 'btn-danger' : 'btn-default' ?>" onclick="toggleSymptom(<?= $row['id'] ?>, 'stroj_lepivost')" style="font-weight: bold;">
                        <i class="glyphicon glyphicon-warning-sign"></i> LEPÍ
                    </button>
                    <button class="btn btn-xs btn-symptom <?= $row['stroj_olej'] ? 'btn-danger' : 'btn-default' ?>" onclick="toggleSymptom(<?= $row['id'] ?>, 'stroj_olej')" style="font-weight: bold;">
                        <i class="glyphicon glyphicon-tint"></i> POUŠTÍ OLEJ
                    </button>
                </div>

                <textarea class="form-control" rows="3" style="border-radius: 6px; font-size: 12px; resize: none; background: #fafafa; border: 1px dashed #ccc;"
                          onchange="saveNote(<?= $row['id'] ?>, this.value)"
                          placeholder="Poznámky k šarži (např. 'V kuchyni OK, uvidíme ve výrobě')..."><?= htmlspecialchars($row['vysledek_text'] ?? '') ?></textarea>
            </div>

            <div class="action-column" style="width: 130px; flex-shrink: 0; border-left: 1px solid #f0f0f0; padding-left: 10px; display: flex; flex-direction: column; gap: 5px; justify-content: center;">
                <?php if ($faze == 1): ?>
                    <button class="btn btn-sm btn-block btn-info" onclick="setFaze(<?= $row['id'] ?>, 2)" style="font-weight: bold;">KUCHYŇ</button>
                <?php elseif ($faze == 2): ?>
                    <button class="btn btn-sm btn-block btn-warning" onclick="setFaze(<?= $row['id'] ?>, 3)" style="font-weight: bold; font-size: 10px;">DO VÝROBY</button>
                    <button class="btn btn-sm btn-block btn-success" onclick="finishTest(<?= $row['id_nabidka'] ?>, 6)" style="font-weight: bold; font-size: 10px;">SCHVÁLIT</button>
                <?php else: ?>
                    <div style="background: #f8fff8; padding: 6px; border-radius: 6px; border: 1px solid #c3e6cb; margin-bottom: 5px;">
                        <label class="small text-success" style="font-size: 9px; display: block; margin-bottom: 2px;">Kódy pro IS</label>
                        <input type="text" id="skup_<?= $row['id'] ?>" class="form-control input-sm" placeholder="skupzbo" style="margin-bottom:4px; font-size: 10px; padding: 2px 5px; height: 24px;" value="<?= htmlspecialchars($row['skupzbo'] ?? '') ?>">
                        <input type="text" id="reg_<?= $row['id'] ?>" class="form-control input-sm" placeholder="regcis" style="font-size: 10px; padding: 2px 5px; height: 24px;" value="<?= htmlspecialchars($row['regcis'] ?? '') ?>">
                    </div>
                    <button class="btn btn-sm btn-block btn-success" style="font-weight:bold;" onclick="finishTest(<?= $row['id_nabidka'] ?>, 6, <?= $row['id'] ?>)">ZAPSAT DO IS</button>
                <?php endif; ?>

                <button class="btn btn-xs btn-link text-danger" onclick="finishTest(<?= $row['id_nabidka'] ?>, 7)" style="margin-top: auto; text-decoration: none; font-size: 10px; font-weight: bold;">
                    <i class="glyphicon glyphicon-remove"></i> ZAMÍTNOUT
                </button>
            </div>
        </div>
        <?php endwhile; echo '</div>'; // Uzavření poslední suroviny ?>
        <?php else: ?>
            <div class="alert alert-info">Momentálně nejsou žádné vzorky čekající na technologický test.</div>
        <?php endif; ?>

    </div>

    <script>
        // Funkce z původního kódu zachovány
        function updateParam(testId, field, value) {
            $.post('includes/ajax_update_test_param.php', { id: testId, field: field, val: value }, function() { location.reload(); });
        }

        function toggleSymptom(testId, field) {
            $.post('includes/ajax_update_test_param.php', { id: testId, field: field, toggle: 1 }, function() { location.reload(); });
        }

        function setFaze(testId, faze) {
            $.post('includes/ajax_update_test_param.php', { id: testId, field: 'faze', val: faze }, function() { location.reload(); });
        }

        function saveNote(testId, text) {
            $.post('includes/ajax_save_test.php', { id: testId, text: text });
        }

        function finishTest(nabidkaId, status, testId = null) {
            var skup = testId ? $('#skup_' + testId).val() : '';
            var reg = testId ? $('#reg_' + testId).val() : '';

            if (status == 6 && testId && (!skup || !reg)) {
                alert("Před zápisem do IS musíte vyplnit kódy 'skupzbo' a 'regcis'!");
                return;
            }

            if(!confirm("Opravdu chcete tento verdikt odeslat?")) return;

            $.post('includes/update_status_nabidka.php', {
                id: nabidkaId, status: status, skupzbo: skup, regcis: reg
            }, function() { location.reload(); });
        }
    </script>
</div>