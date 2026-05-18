<?php
// 1. Zjištění počtu nevyřešených požadavků pro badge (Pouze pro Admina/Vývoj)
$unresolved_badge = '';
if (!empty($is_adm) || !empty($is_vyvoj)) {
    // Předpokládá existenci připojení $conn z db_connect.php
    $dev_q = @mysqli_query($conn, "SELECT COUNT(*) as c FROM dev_pozadavky WHERE stav = 0");
    if ($dev_q) {
        $dev_r = mysqli_fetch_assoc($dev_q);
        if ($dev_r['c'] > 0) {
            $unresolved_badge = '<span id="devBadgeCount" class="badge-pulse">'.$dev_r['c'].'</span>';
        }
    }
}
?>

<div class="feedback-btn-wrapper">
    <button id="btnOpenDevTasks" class="btn btn-primary btn-feedback">
        <i class="glyphicon glyphicon-bullhorn"></i> Nápady & Úpravy <?= $unresolved_badge ?>
    </button>
</div>

<div class="modal fade" id="mDevTasks" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content feedback-modal-content">
            <div class="modal-header feedback-modal-header">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title feedback-modal-title"><i class="glyphicon glyphicon-bullhorn"></i> Požadavky na úpravu systému</h4>
            </div>
            <div class="modal-body feedback-modal-body">
                <div class="feedback-form-box">
                    <label class="small text-muted" style="text-transform: uppercase;">Máte nápad na vylepšení nebo jste našli chybu?</label>
                    <textarea id="devTaskText" class="form-control feedback-textarea" rows="3" placeholder="Napište sem, co byste potřebovali přidat nebo opravit..."></textarea>
                    <button id="btnSaveDevTask" class="btn btn-primary btn-block btn-feedback-submit" style="margin-top: 10px;">Odeslat vývojáři</button>
                </div>
                <h5 class="feedback-list-title" style="margin-top: 20px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Seznam požadavků</h5>
                <div id="devTasksList" class="feedback-list-container"></div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // Otevření modálu a načtení dat
        $(document).on('click', '#btnOpenDevTasks', function() {
            loadDevTasks();
            $('#mDevTasks').modal('show');
        });

        // Uložení nového nápadu
        $(document).on('click', '#btnSaveDevTask', function() {
            var text = $('#devTaskText').val().trim();
            if (!text) return;

            var btn = $(this);
            btn.prop('disabled', true).text('Odesílám...');

            $.post('includes/ajax_dev_pozadavky.php', { action: 'add', text: text }, function(r) {
                btn.prop('disabled', false).text('Odeslat vývojáři');
                if (r.trim() === "OK") {
                    $('#devTaskText').val('');
                    loadDevTasks();
                } else {
                    if (typeof sysAlert === "function") sysAlert("Chyba: " + r, "danger"); else alert("Chyba: " + r);
                }
            });
        });

        // Změna stavu ticketu (Vyřešeno / Zamítnuto)
        $(document).on('click', '.btn-update-dev-task', function() {
            var btn = $(this);
            var id = btn.data('id');
            var newStatus = btn.data('status');
            var reakce = "";

            if (newStatus == 2) {
                reakce = prompt("Uveďte prosím důvod zamítnutí (povinné):");
                if (reakce === null) return; // Uživatel dal Storno
                if (reakce.trim() === "") {
                    if (typeof sysAlert === "function") sysAlert("Důvod zamítnutí musí být vyplněn!", "warning"); else alert("Důvod zamítnutí musí být vyplněn!");
                    return;
                }
            } else {
                reakce = prompt("Můžete přidat krátký komentář (nepovinné):");
                if (reakce === null) return; // Uživatel dal Storno
            }

            btn.prop('disabled', true).text('...');

            $.post('includes/ajax_dev_pozadavky.php', {
                action: 'update_status',
                id: id,
                status: newStatus,
                reakce: reakce
            }, function(r) {
                if (r.trim() === "OK") {
                    loadDevTasks();
                } else {
                    if (typeof sysAlert === "function") sysAlert("Chyba: " + r, "danger"); else alert("Chyba: " + r);
                    btn.prop('disabled', false).text(newStatus == 1 ? '✔ Vyřešit' : '✖ Zamítnout');
                }
            });
        });

        // Funkce pro načtení seznamu ticketů
        function loadDevTasks() {
            $('#devTasksList').html('<div class="feedback-msg" style="text-align:center; padding: 20px;"><i class="glyphicon glyphicon-refresh spinning"></i> Načítám historii...</div>');
            $.post('includes/ajax_dev_pozadavky.php', { action: 'load' }, function(html) {
                $('#devTasksList').html(html);
            });
        }
    });
</script>