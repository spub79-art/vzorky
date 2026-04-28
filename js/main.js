// ==========================================
// GLOBÁLNÍ SYSTÉMOVÉ HLÁŠKY (Náhrada alert / confirm)
// ==========================================

// Funkce, která zajistí, že modál vždy existuje, ať jsme na jakékoliv stránce
function ensureSystemModal() {
    if ($('#mSystemAlert').length === 0) {
        $('body').append(
            '<div class="modal fade" id="mSystemAlert" tabindex="-1" role="dialog" style="z-index: 100000;">' +
            '<div class="modal-dialog modal-sm" role="document" style="margin-top: 15vh;">' +
            '<div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.3);">' +
            '<div class="modal-header" id="mSystemAlertHeader" style="border-radius: 12px 12px 0 0; padding: 15px 20px; background-color: #337ab7;">' +
            '<button type="button" class="close text-white" data-dismiss="modal" style="opacity: 0.8; color: white;">&times;</button>' +
            '<h4 class="modal-title" id="mSystemAlertTitle" style="font-weight: bold; color: white;"></h4>' +
            '</div>' +
            '<div class="modal-body" id="mSystemAlertBody" style="padding: 25px 20px; font-size: 15px; text-align: center; color: #444;"></div>' +
            '<div class="modal-footer" id="mSystemAlertFooter" style="border-top: 1px solid #f0f0f0; padding: 15px; text-align: center;"></div>' +
            '</div>' +
            '</div>' +
            '</div>'
        );
    }
}

window.sysAlert = function(message, type) {
    if (type === undefined) type = 'danger';
    ensureSystemModal();
    var bg = '#337ab7'; var icon = 'fa-info-circle'; var title = 'Upozornění';
    if (type === 'danger') { bg = '#d9534f'; icon = 'fa-exclamation-triangle'; title = 'Chyba'; }
    else if (type === 'warning') { bg = '#f0ad4e'; icon = 'fa-exclamation-circle'; title = 'Pozor'; }
    else if (type === 'success') { bg = '#5cb85c'; icon = 'fa-check-circle'; title = 'Úspěch'; }

    $('#mSystemAlertHeader').css('background-color', bg);
    $('#mSystemAlertTitle').html('<i class="fa ' + icon + '"></i> ' + title);
    $('#mSystemAlertBody').html(message);
    $('#mSystemAlertFooter').html('<button type="button" class="btn btn-default btn-block" data-dismiss="modal" style="border-radius: 8px; font-weight: bold;">Zavřít</button>');
    $('#mSystemAlert').modal('show');
};

window.sysConfirm = function(message, callback) {
    ensureSystemModal();
    $('#mSystemAlertHeader').css('background-color', '#d9534f');
    $('#mSystemAlertTitle').html('<i class="fa fa-question-circle"></i> Potvrzení akce');
    $('#mSystemAlertBody').html('<strong>' + message + '</strong>');

    var btns = '<div style="display: flex; gap: 10px;">' +
        '<button type="button" class="btn btn-default" data-dismiss="modal" style="flex: 1; border-radius: 8px; font-weight: bold;">Zrušit</button>' +
        '<button type="button" class="btn btn-danger" id="btnSysConfirmYes" style="flex: 1; border-radius: 8px; font-weight: bold;">Ano, smazat</button>' +
        '</div>';
    $('#mSystemAlertFooter').html(btns);
    $('#mSystemAlert').modal('show');

    $('#btnSysConfirmYes').off('click').on('click', function() {
        $('#mSystemAlert').modal('hide');
        if (typeof callback === 'function') callback();
    });
};


$(document).ready(function() {

    // ==========================================
    // 1. KNIHOVNA SUROVIN
    // ==========================================
    // Zobrazení tlačítka pro uložení surovin při jakékoliv změně v inputech
    $(document).on('input change', '.table-suroviny input', function() {
        $('#saveSurovinyContainer').slideDown(300);
    });
    $(document).on('click', '.btn-save-all-translations', function() {
        var data = [];
        $('.table-suroviny tbody tr').each(function() {
            data.push({
                id: $(this).data('id'),
                nazev: $(this).find('.edit-nazev').val(),
                nazev_en: $(this).find('.edit-nazev-en').val(),
                skupzbo: $(this).find('.edit-skupzbo').val(),
                regcis: $(this).find('.edit-regcis').val()
            });
        });

        var btn = $(this);
        btn.html('<i class="fa fa-spinner fa-spin"></i> Ukládám...').prop('disabled', true);

        $.post('includes/ajax_update_surovina_batch.php', { data: JSON.stringify(data) }, function(response) {
            if (response === "OK") {
                location.reload();
            } else {
                sysAlert("Chyba při ukládání:<br>" + response, "danger");
                btn.html('<i class="fa fa-save"></i> Uložit vše naráz').prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.btn-show-historie-sur', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');

        $('#modalSurName').text(name);
        $('#modalSurBody').html('<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Hledám v archivech...</div>');

        if($('#mHistorieSuroviny').length > 0) {
            $('#mHistorieSuroviny').modal('show');
            $.post('includes/ajax_get_surovina_historie.php', { id: id }, function(response) {
                $('#modalSurBody').html(response);
            });
        } else {
            sysAlert("Chyba: Soubor s modály nebyl správně načten.", "danger");
        }
    });

    // ==========================================
    // 2. SPRÁVA ZÁKAZNÍKŮ
    // ==========================================
    $(document).on('click', '.btn-toggle-add-customer', function() {
        var row = $("#addRow");
        if (row.is(":hidden")) {
            row.show();
            setTimeout(function() { row.find('input[name="nazev_novy"]').focus(); }, 100);
        } else {
            row.hide();
        }
    });

    // ==========================================
    // 3. SPRÁVA DODAVATELŮ
    // ==========================================
    $(document).on('click', '.btn-add-dodavatel', function() {
        $('#modal-loader').show();
        $('#modal-dynamic-content').html('');
        $('#modal-dynamic-content').load('includes/formDodavatel.php', function() {
            $('#modal-loader').hide();
            $('#remoteModal').modal('show');
        });
    });

    $(document).on('click', '.btn-edit-dodavatel', function() {
        var id = $(this).data('id');
        $('#modal-loader').show();
        $('#modal-dynamic-content').html('');
        $('#modal-dynamic-content').load('includes/formDodavatel.php?id=' + id, function() {
            $('#modal-loader').hide();
            $('#remoteModal').modal('show');
        });
    });

    $(document).on('click', '.btn-show-vzorky', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');

        $('#modalDodavatelName').text(name);
        $('#modalVzorkyBody').html('<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Načítám data...</div>');
        $('#mVzorkyDodavatele').modal('show');

        $.post('includes/ajax_get_dodavatel_vzorky.php', { id: id }, function(response) {
            $('#modalVzorkyBody').html(response);
        }).fail(function() {
            $('#modalVzorkyBody').html('<div class="alert alert-danger">Chyba při stahování dat ze serveru.</div>');
        });
    });

// ==========================================
    // 4. GLOBÁLNÍ HLADKÉ MAZÁNÍ (AJAX)
    // ==========================================
    $(document).on('click', '.btn-delete-ajax', function(e) {
        e.preventDefault();

        var btn = $(this);
        var row = btn.closest('tr');

        // Priorita: 1. Atribut data-confirm, 2. starý onclick confirm, 3. výchozí text
        var confirmText = btn.data('confirm') || "Opravdu chcete tuto položku smazat?";
        var onclickAttr = btn.attr('onclick');
        if (!btn.data('confirm') && onclickAttr && onclickAttr.indexOf("confirm('") !== -1) {
            confirmText = onclickAttr.split("confirm('")[1].split("')")[0];
        }

        sysConfirm(confirmText, function() {
            var originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            var ajaxUrl = "";
            if (btn.is('a') && btn.attr('href')) {
                ajaxUrl = btn.attr('href').replace(/&redirect=[a-zA-Z0-9_]+/, '');
            } else {
                ajaxUrl = 'includes/delete_logic.php?table=' + btn.data('table') + '&id=' + btn.data('id');
            }

            $.get(ajaxUrl, function(response) {
                if (response.trim() === "OK") {
                    row.fadeOut(400, function() { $(this).remove(); });
                } else {
                    sysAlert("Položku nelze smazat:<br>" + response, "warning");
                    btn.prop('disabled', false).html(originalHtml);
                }
            }).fail(function() {
                sysAlert("Chyba při komunikaci se serverem.", "danger");
                btn.prop('disabled', false).html(originalHtml);
            });
        });

        // Vyčistíme onclick, aby se nepletl do cesty
        btn.removeAttr('onclick');
    });

});