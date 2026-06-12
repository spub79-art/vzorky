// ==========================================
// GLOBÁLNÍ SYSTÉMOVÉ HLÁŠKY (Náhrada alert / confirm)
// ==========================================

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

window.sysConfirm = function(message, callback, btnText = 'Ano, smazat', btnClass = 'btn-danger') {
    ensureSystemModal();

    var headerBg = '#d9534f';
    if (btnClass.indexOf('success') !== -1) headerBg = '#5cb85c';
    if (btnClass.indexOf('warning') !== -1) headerBg = '#f0ad4e';
    if (btnClass.indexOf('info') !== -1 || btnClass.indexOf('primary') !== -1) headerBg = '#337ab7';

    $('#mSystemAlertHeader').css('background-color', headerBg);
    $('#mSystemAlertTitle').html('<i class="fa fa-question-circle"></i> Potvrzení akce');
    $('#mSystemAlertBody').html('<strong>' + message + '</strong>');

    var btns = '<div style="display: flex; gap: 10px;">' +
        '<button type="button" class="btn btn-default" data-dismiss="modal" style="flex: 1; border-radius: 8px; font-weight: bold;">Zrušit</button>' +
        '<button type="button" class="btn ' + btnClass + '" id="btnSysConfirmYes" style="flex: 1; border-radius: 8px; font-weight: bold;">' + btnText + '</button>' +
        '</div>';
    $('#mSystemAlertFooter').html(btns);
    $('#mSystemAlert').modal('show');

    $('#btnSysConfirmYes').off('click').on('click', function() {
        $('#mSystemAlert').modal('hide');
        setTimeout(function() {
            if (typeof callback === 'function') callback();
        }, 400);
    });
};

window.sysPrompt = function(message, callback, btnText = 'Uložit', btnClass = 'btn-primary') {
    ensureSystemModal();

    var headerBg = '#337ab7';
    if (btnClass.indexOf('success') !== -1) headerBg = '#5cb85c';
    if (btnClass.indexOf('warning') !== -1) headerBg = '#f0ad4e';
    if (btnClass.indexOf('danger') !== -1) headerBg = '#d9534f';

    $('#mSystemAlertHeader').css('background-color', headerBg);
    $('#mSystemAlertTitle').html('<i class="fa fa-pencil"></i> Vyžadováno upřesnění');

    var bodyHtml = '<div style="text-align: left; font-size: 13px;"><strong>' + message + '</strong><br><br>' +
        '<textarea id="sysPromptInput" class="form-control" rows="3" placeholder="Zadejte vysvětlení pro kolegy..."></textarea></div>';
    $('#mSystemAlertBody').html(bodyHtml);

    var btns = '<div style="display: flex; gap: 10px;">' +
        '<button type="button" class="btn btn-default" data-dismiss="modal" style="flex: 1; border-radius: 8px; font-weight: bold;">Zrušit</button>' +
        '<button type="button" class="btn ' + btnClass + '" id="btnSysPromptYes" style="flex: 1; border-radius: 8px; font-weight: bold;">' + btnText + '</button>' +
        '</div>';
    $('#mSystemAlertFooter').html(btns);
    $('#mSystemAlert').modal('show');

    setTimeout(function(){ $('#sysPromptInput').focus(); }, 400);

    $('#btnSysPromptYes').off('click').on('click', function() {
        var val = $('#sysPromptInput').val().trim();
        if(!val) {
            $('#sysPromptInput').css('border', '2px solid #d9534f');
            return;
        }
        $('#mSystemAlert').modal('hide');
        setTimeout(function() {
            if (typeof callback === 'function') callback(val);
        }, 400);
    });
};

function refreshAfterHistoryChange() {
    if (($('#mReqDetail').hasClass('in') || $('#mReqDetail').is(':visible')) && $('#currentReqDetailId').length > 0) {
        var openReqId = $('#currentReqDetailId').val();
        if (openReqId) {
            $.post('includes/ajax_request_detail.php', { id: openReqId }, function(data) {
                $('#mReqDetailContent').html(data);
            });
        }
    } else {
        $('#board-container').load(window.location.href + ' #board-container > *', function() {
            if (typeof applyFilters === "function") applyFilters();
        });
    }
}

$(document).ready(function() {

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

    $(document).on('click', '.btn-toggle-add-customer', function() {
        var row = $("#addRow");
        if (row.is(":hidden")) {
            row.show();
            setTimeout(function() { row.find('input[name="nazev_novy"]').focus(); }, 100);
        } else {
            row.hide();
        }
    });

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

    $(document).on('click', '.btn-delete-ajax', function(e) {
        e.preventDefault();
        var btn = $(this);
        var row = btn.closest('tr');
        var confirmText = btn.data('confirm') || "Opravdu chcete tuto položku smazat?";

        sysConfirm(confirmText, function() {
            var originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            var ajaxUrl = (btn.is('a') && btn.attr('href'))
                ? btn.attr('href').replace(/&redirect=[a-zA-Z0-9_]+/, '')
                : 'includes/delete_logic.php?table=' + btn.data('table') + '&id=' + btn.data('id');

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
        btn.removeAttr('onclick');
    });

    // MAZÁNÍ OMYLEM VLOŽENÉ NABÍDKY
    $(document).on('click', '.btn-delete-offer-ajax', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id');
        sysConfirm("Opravdu chcete smazat tuto chybně vloženou nabídku?", function() {
            $.post('includes/delete_logic.php?table=pozadavky_nabidky&id=' + id, function(r) {
                if(r.trim() === "OK") safeReload(); else sysAlert(r, "warning");
            });
        });
    });

    // OPRAVA PINGNUTÍ NÁKUPU (OKOK modál -> sysPrompt)
    $(document).on('click', '.btn-ping-purchasing', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var reqId = $(this).data('id');
        var sur = $(this).data('sur');

        sysPrompt("Vyžádat další nabídku k surovině <b>" + sur + "</b>.<br>Napište Nákupu, co přesně potřebujete (jiné balení, lepší certifikát...):", function(note) {
            var originalBtn = $('.btn-ping-purchasing[data-id="'+reqId+'"]');
            originalBtn.removeClass('glyphicon-bell text-info').addClass('glyphicon-refresh spinning text-muted');

            $.post('includes/ajax_ping_purchasing.php', { id: reqId, surovina: sur, poznamka: note }, function(r) {
                if (r.trim() === "OK") {
                    originalBtn.removeClass('glyphicon-refresh spinning text-muted').addClass('glyphicon-ok text-success');
                    setTimeout(function() { safeReload(); }, 1500);
                } else {
                    sysAlert(r, "danger");
                    originalBtn.removeClass('glyphicon-refresh spinning text-muted').addClass('glyphicon-bell text-info');
                }
            });
        }, "Odeslat požadavek", "btn-info");
    });

    $('#mNNSave').on('click', function() {
        var mode = $('#mNN').attr('data-mode');
        var targetScript = (mode === 'edit') ? 'includes/ajax_update_offer.php' : 'includes/ajax_add_offer.php';
        var d = $('#mNNDod').val(), c = $('#mNNCena').val(), id = $('#mNNId').val();

        if(!d) { sysAlert("Vyplňte prosím dodavatele.", "warning"); return; }

        var bezCeny = (mode === 'add') && $('#mNNBezCeny').is(':checked');
        if (mode === 'add' && !bezCeny && !c) { sysAlert("Zadejte cenu, nebo zaškrtněte „Zatím bez ceny“.", "warning"); return; }

        var finalNote = $('#mNNPozn').val().trim();
        var dopravaVal = $('#mNNDoprava').val();

        if (dopravaVal) {
            var dCena = (dopravaVal === 'custom') ? $('#mNNDopravaCustom').val() : dopravaVal;
            if (dCena) { finalNote = "[Dopravné: " + dCena + " Kč/MJ]\n" + finalNote; }
        }

        $.post(targetScript, {
            id_nabidka: (mode === 'edit' ? id : 0),
            id_pozadavek: (mode === 'add' ? id : 0),
            dodavatel_raw: d,
            cena: c,
            bez_ceny: bezCeny ? 1 : 0,
            mena: $('#mNNMena').val() || 'CZK',
            moq_qty: $('#mNNMoqQty').val(),
            moq_mj: $('#mNNMoqMj').val(),
            poznamka_nakup: finalNote
        }, function(r) {
            if(r.trim() == "OK") { $('#mNN').modal('hide'); safeReload(); }
            else { sysAlert(r, "danger"); }
        });
    });

    $(document).on('click', '.btn-edit-history', function(e) {
        e.stopPropagation();
        $('#mEditHistoryId').val($(this).data('id'));
        var txt = $('<textarea />').html($(this).data('text')).text();
        $('#mEditHistoryText').val(txt);
        $('#mEditHistoryModal').modal('show');
    });

    $('#mEditHistorySave').off('click').on('click', function() {
        var id = $('#mEditHistoryId').val();
        var text = $('#mEditHistoryText').val().trim();
        if (!text) return sysAlert("Text nesmí být prázdný.", "warning");

        var btn = $(this);
        btn.prop('disabled', true).text('Ukládám...');

        $.post('includes/ajax_edit_comment.php', { id: id, text: text }, function(r) {
            if (r.trim() === "OK") {
                $('#mEditHistoryModal').modal('hide');
                btn.prop('disabled', false).text('Uložit');
                setTimeout(refreshAfterHistoryChange, 400);
            } else {
                sysAlert(r, "danger");
                btn.prop('disabled', false).text('Uložit');
            }
        });
    });

    $(document).on('click', '.btn-delete-history', function(e) {
        e.stopPropagation();
        var id = $(this).data('id');
        sysConfirm("Opravdu chcete skrýt tento záznam pro ostatní uživatele?", function() {
            $.post('includes/ajax_delete_comment.php', { id: id }, function(r) {
                if (r.trim() === "OK") {
                    refreshAfterHistoryChange();
                } else {
                    sysAlert(r, "danger");
                }
            });
        }, "Ano, skrýt", "btn-danger");
    });
});