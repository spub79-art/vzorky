// ==============================================================
// HLAVNÍ LOGIKA PRO NÁSTĚNKU (Filtry, Akce, Modály)
// ==============================================================

// 1. STAVOVÝ OBJEKT PRO FILTRY
var filters = {
    search: '',
    rejected: false,
    myTasks: false,
    urgent: false,
    showSysHistory: false
};

var SYS_HIST_KEY = 'vzorky_show_sys_history';

function applySysHistoryVisibility() {
    $('#board-container').toggleClass('board-hide-sys-history', !filters.showSysHistory);
    try { localStorage.setItem(SYS_HIST_KEY, filters.showSysHistory ? '1' : '0'); } catch (e) {}
    $('#btnToggleSysHistory')
        .toggleClass('btn-info', filters.showSysHistory)
        .toggleClass('btn-default', !filters.showSysHistory);
    refreshHistoryPreviews();
}

/** Náhled historie na kartě — bez zbytečného ořezu krátkých diskuzí. */
function refreshHistoryPreviews() {
    var contentCap = 76;
    var footerH = 20;

    $('#board-container .offer-comments-wrapper:not(.is-expanded), #board-container .offer-sys-msg-container:not(.is-expanded)').each(function() {
        var $wrap = $(this);
        $wrap.find('.history-expand-hint').remove();

        var hasVisible = false;
        $wrap.children('.history-row').each(function() {
            if ($(this).css('display') !== 'none') hasVisible = true;
        });

        $wrap.removeClass('history-preview-fit history-preview-overflow history-preview-empty');
        $wrap.css('max-height', '');

        if (!hasVisible) {
            $wrap.addClass('history-preview-empty').hide();
            return;
        }

        $wrap.show();
        var natural = this.scrollHeight;
        if (natural <= contentCap) {
            $wrap.addClass('history-preview-fit').removeAttr('title');
        } else {
            $wrap.addClass('history-preview-overflow')
                .css('max-height', (contentCap + footerH) + 'px')
                .append(
                    '<div class="history-expand-hint">' +
                    '<i class="glyphicon glyphicon-resize-full"></i> Celá diskuze' +
                    '</div>'
                );
        }
    });
}

var cur = { id: 0, st: 0, hasLab: false };

function openRequestDetail(reqId) {
    reqId = parseInt(reqId, 10);
    if (!reqId || !$('#mReqDetail').length) return;
    $('#mReqDetailContent').html(
        '<div style="padding:50px; text-align:center; color:#777;">' +
        '<i class="glyphicon glyphicon-refresh spinning" style="font-size: 30px;"></i><br><br>Načítám detail požadavku...' +
        '</div>'
    );
    $('#mReqDetail').modal('show');
    $.post('includes/ajax_request_detail.php', { id: reqId }, function(data) {
        $('#mReqDetailContent').html(data);
    }).fail(function() {
        $('#mReqDetailContent').html('<div class="alert alert-danger" style="margin:20px;">Chyba při načítání dat.</div>');
    });
}

function digestReturnUrl() {
    return window.location.search.indexOf('Souhrn') >= 0 ? 'index.php?Souhrn=1' : 'index.php?Pozadavek=1';
}

// 2. HLAVNÍ FUNKCE FILTROVÁNÍ
function applyFilters() {
    var term = filters.search.toLowerCase().trim();
    var cleanTerm = term.replace('#', '');

    $('.req-card').each(function() {
        var card = $(this);
        var isRejected = card.hasClass('offer-rejected');

        var id = card.data('req-id') ? card.data('req-id').toString() : '';
        var name = card.data('req-name') ? card.data('req-name').toString() : '';

        var matchesSearch = (term === '' || id.indexOf(cleanTerm) > -1 || name.indexOf(term) > -1);
        var matchesRejected = (!isRejected || filters.rejected);
        var matchesUrgent = (!filters.urgent || card.data('urgent') == 1);

        var matchesMyTasks = true;
        if (filters.myTasks) {
            var cardNeedsAction = card.hasClass('needs-my-action');
            var hasOfferNeedingAction = card.find('.offer-row.needs-my-action').length > 0;
            matchesMyTasks = (cardNeedsAction || hasOfferNeedingAction);
        }

        if (matchesSearch && matchesRejected && matchesMyTasks && matchesUrgent) {
            card.show();

            card.find('.offer-row').each(function() {
                var offerRow = $(this);
                var offerIsRejected = offerRow.hasClass('offer-rejected');

                if (filters.myTasks) {
                    offerRow.toggle(offerRow.hasClass('needs-my-action'));
                } else {
                    if ((offerIsRejected || offerRow.hasClass('offer-frozen')) && !filters.rejected) {
                        offerRow.hide();
                    } else {
                        offerRow.show();
                    }
                }
            });

        } else {
            card.hide();
        }
    });

    $('#btnToggleRejected')
        .html(filters.rejected ? '<i class="glyphicon glyphicon-eye-close"></i> Skrýt' : '<i class="glyphicon glyphicon-eye-open"></i> KO / Led')
        .toggleClass('btn-danger', filters.rejected)
        .toggleClass('btn-default', !filters.rejected);

    $('#btnToggleMyTasks')
        .html(filters.myTasks ? '<i class="glyphicon glyphicon-filter"></i> Vše' : '<i class="glyphicon glyphicon-filter"></i> K řešení')
        .toggleClass('btn-primary', filters.myTasks)
        .toggleClass('btn-default', !filters.myTasks);

    $('#btnToggleUrgent')
        .html(filters.urgent ? '<i class="glyphicon glyphicon-flash text-white"></i> ×Urgent' : '<i class="glyphicon glyphicon-flash text-danger"></i> Urgent')
        .toggleClass('btn-danger', filters.urgent)
        .toggleClass('btn-default', !filters.urgent);

    refreshHistoryPreviews();
}

function reloadRequestDetailIfOpen(callback) {
    if (!$('#mReqDetail').hasClass('in')) {
        if (typeof callback === 'function') callback();
        return;
    }
    var detailId = $('#currentReqDetailId').val();
    if (!detailId) {
        if (typeof callback === 'function') callback();
        return;
    }
    $.post('includes/ajax_request_detail.php', { id: detailId }, function(html) {
        $('#mReqDetailContent').html(html);
        if (typeof callback === 'function') callback();
    });
}

function reloadBoard() {
    if (!$('#board-container').length) return;
    $('#board-container').load(window.location.href + ' #board-container > *', function() {
        applyFilters();
        applySysHistoryVisibility();
        if (typeof $.fn.select2 !== 'undefined') { $('.select2-dod').select2({ dropdownParent: $('#mNN'), tags: true }); }
        initBoardReqSelect2();
    });
}

function safeReload(opts) {
    opts = opts || {};
    if (opts.closeDetail && $('#mReqDetail').hasClass('in')) {
        $('#mReqDetail').modal('hide');
    } else if ($('#mReqDetail').hasClass('in')) {
        reloadRequestDetailIfOpen();
    }
    // Blokovat reload boardu jen při „pracovních“ modalech (ne detail požadavku).
    var blocking = $('.modal.in').filter(function() {
        return this.id !== 'mReqDetail';
    }).length;
    if (blocking > 0 && !opts.forceBoard) return;
    reloadBoard();
}

var pendingNewRequest = null;

function initBoardReqSelect2() {
    if (typeof $.fn.select2 === 'undefined') return;

    var $sur = $('#mAddReqSurovina');
    if ($sur.length && !$sur.hasClass('select2-hidden-accessible')) {
        $sur.select2({
            dropdownParent: $('#mAddReq'),
            tags: true,
            placeholder: 'Vyberte nebo napište novou surovinu...',
            allowClear: true,
            createTag: function(params) {
                return { id: params.term, text: params.term, newTag: true };
            }
        });
    }

    $('.select2-req-zak').each(function() {
        var $el = $(this);
        if ($el.hasClass('select2-hidden-accessible')) return;
        $el.select2({
            dropdownParent: $el.closest('.modal'),
            tags: true,
            placeholder: 'Zákazníci (nepovinné)...',
            allowClear: true
        });
    });

    $('.select2-req-prod').each(function() {
        var $el = $(this);
        if ($el.hasClass('select2-hidden-accessible')) return;
        $el.select2({
            dropdownParent: $el.closest('.modal'),
            placeholder: 'Produkty (nepovinné)...',
            allowClear: true
        });
    });
}

function collectAddRequestPayload() {
    return {
        id_surovina: $('#mAddReqSurovina').val(),
        bio: $('#mAddReqBio').is(':checked') ? 1 : 0,
        vegan: $('#mAddReqVegan').is(':checked') ? 1 : 0,
        bezlepek: $('#mAddReqBezlepek').is(':checked') ? 1 : 0,
        kosher: $('#mAddReqKosher').is(':checked') ? 1 : 0,
        halal: $('#mAddReqHalal').is(':checked') ? 1 : 0,
        priorita: $('#mAddReqPrio').val(),
        mnozstvi: $('#mAddReqMnozstvi').val(),
        mj: $('#mAddReqMj').val(),
        poznamka: $('#mAddReqNote').val(),
        zakaznici: $('#mAddReqZakaznici').val() || [],
        produkty: $('#mAddReqProdukty').length ? ($('#mAddReqProdukty').val() || []) : []
    };
}

function resetAddRequestForm() {
    $('#mAddReqSurovina').val(null).trigger('change');
    $('#mAddReqBio, #mAddReqVegan, #mAddReqBezlepek, #mAddReqKosher, #mAddReqHalal').prop('checked', false);
    $('#mAddReqPrio').val('0');
    $('#mAddReqMnozstvi').val('');
    $('#mAddReqMj').val('kg');
    $('#mAddReqNote').val('');
    if ($('#mAddReqZakaznici').length) $('#mAddReqZakaznici').val(null).trigger('change');
    if ($('#mAddReqProdukty').length) $('#mAddReqProdukty').val(null).trigger('change');
}

function handleAddRequestResponse(r, btn, successModal) {
    var msg = (typeof r === 'string') ? r.trim() : '';
    if (msg === 'OK') {
        $(successModal).modal('hide');
        pendingNewRequest = null;
        safeReload();
        return;
    }
    if (msg.indexOf('EXACT_DUP|') === 0) {
        var existId = msg.split('|')[1];
        pendingNewRequest = collectAddRequestPayload();
        $('#dupExistId').text(existId);
        $('#mDuplicateWarning').data('exist-id', existId);
        $(successModal).modal('hide');
        $('#mDuplicateWarning').modal('show');
        if (btn) {
            btn.prop('disabled', false).text('ZALOŽIT POŽADAVEK');
        }
        return;
    }
    if (typeof sysAlert === 'function') sysAlert(msg, 'danger'); else alert(msg);
    if (btn) btn.prop('disabled', false).text('ZALOŽIT POŽADAVEK');
}

function submitAddRequest(extraData, btn) {
    var payload = collectAddRequestPayload();
    if (extraData) {
        $.extend(payload, extraData);
    }
    if (!payload.id_surovina) {
        sysAlert('Musíte vybrat surovinu!', 'warning');
        return;
    }
    if (btn) {
        btn.prop('disabled', true).text('Zakládám...');
    }
    $.post('includes/ajax_add_request.php', payload, function(r) {
        handleAddRequestResponse(r, btn, '#mAddReq');
    });
}

// ==============================================================
// 3. DELEGOVANÉ UDÁLOSTI A EVENT LISTENERY
// ==============================================================
$(document).ready(function() {

    // --- Filtry v horní liště ---
    $(document).on('click', '#btnToggleMyTasks', function(e) { e.preventDefault(); filters.myTasks = !filters.myTasks; applyFilters(); });
    $(document).on('click', '#btnToggleRejected', function(e) { e.preventDefault(); filters.rejected = !filters.rejected; applyFilters(); });
    $(document).on('click', '#btnToggleUrgent', function(e) { e.preventDefault(); filters.urgent = !filters.urgent; applyFilters(); });
    $(document).on('click', '#btnToggleSysHistory', function(e) {
        e.preventDefault();
        filters.showSysHistory = !filters.showSysHistory;
        applySysHistoryVisibility();
    });
    $(document).on('input', '#searchInput', function() { filters.search = $(this).val(); applyFilters(); });

    try {
        filters.showSysHistory = localStorage.getItem(SYS_HIST_KEY) === '1';
    } catch (e) {}
    applySysHistoryVisibility();
    $(window).on('load', refreshHistoryPreviews);

    // --- Inicializace Select2 ---
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2-dod').select2({ dropdownParent: $('#mNN'), tags: true, placeholder: "Napište nebo vyberte...", allowClear: true });
    }
    initBoardReqSelect2();

    $(document).on('click', '.btn-new-req', function(e) {
        e.preventDefault();
        if (!$('#mAddReq').length) {
            if (typeof sysAlert === 'function') sysAlert('Modal není k dispozici na této stránce.', 'warning');
            return;
        }
        resetAddRequestForm();
        $('#mAddReq').modal('show');
        initBoardReqSelect2();
    });

    // --- Export ---
    $(document).on('click', '#btnOpenExportModal', function() {
        $('#mExportModal').modal('show');
        $('#mExportModalBody').html('<div class="text-center text-muted" style="padding: 40px;"><i class="glyphicon glyphicon-refresh spinning" style="font-size: 30px;"></i><br><br>Sestavuji seznam...</div>');
        $.ajax({
            url: 'includes/ajax_export_wanted.php', type: 'GET',
            success: function(data) { $('#mExportModalBody').html(data); },
            error: function() { $('#mExportModalBody').html('<div class="alert alert-danger">Chyba spojení.</div>'); }
        });
    });

    $(document).on('click', '#btnCopyExport', function() {
        var textToCopy = $('#exportTextarea').val();
        if (!textToCopy) return;
        navigator.clipboard.writeText(textToCopy).then(function() {
            var btn = $('#btnCopyExport'); var originalText = btn.html();
            btn.removeClass('btn-success').addClass('btn-info').html('<i class="glyphicon glyphicon-ok"></i> Zkopírováno!');
            setTimeout(function() { btn.removeClass('btn-info').addClass('btn-success').html(originalText); }, 2000);
        });
    });

    // --- Komentáře a Urgence ---
    $(document).on('click', '.btn-urge-task', function(e) {
        e.preventDefault(); e.stopImmediatePropagation();
        $('#mUrgeReqId').val($(this).data('id'));
        $('#mUrgeSurRaw').val($(this).data('sur'));
        $('#mUrgeTaskModal').modal('show');
    });

    $('#btnConfirmUrge').on('click', function() {
        var reqId = $('#mUrgeReqId').val(), sur = $('#mUrgeSurRaw').val();
        var modalBtn = $(this), originalBtn = $('.btn-urge-task[data-id="'+reqId+'"]');
        modalBtn.prop('disabled', true).text('Odesílám...');
        originalBtn.removeClass('glyphicon-flash text-warning').addClass('glyphicon-refresh spinning text-muted');

        $.post('includes/ajax_urge_task.php', { id: reqId, surovina: sur }, function(r) {
            modalBtn.prop('disabled', false).text('Ano, urgovat');
            if (r.trim() === "OK") {
                $('#mUrgeTaskModal').modal('hide');
                originalBtn.removeClass('glyphicon-refresh spinning text-muted').addClass('glyphicon-ok text-success');
                setTimeout(function() { safeReload(); }, 2000);
            } else {
                if (typeof sysAlert === "function") sysAlert(r, "danger"); else alert(r);
                originalBtn.removeClass('glyphicon-refresh spinning text-muted').addClass('glyphicon-flash text-warning');
            }
        });
    });

    $(document).on('click', '.btn-digest-snooze', function(e) {
        e.preventDefault(); e.stopImmediatePropagation();
        var b = $(this);
        $('#mDigestSnoozeId').val(b.data('id'));
        $('#mDigestSnoozeNote').val(b.data('snooze-note') || '');
        $('#mDigestSnoozeDays').val('7');
        $('#mDigestSnooze').modal('show');
    });

    $('#mDigestSnoozeSave').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Ukládám…');
        $.post('includes/ajax_digest_snooze.php', {
            id: $('#mDigestSnoozeId').val(),
            days: $('#mDigestSnoozeDays').val(),
            poznamka: $('#mDigestSnoozeNote').val(),
            action: 'set'
        }, function(r) {
            btn.prop('disabled', false).text('Uložit');
            if ((r || '').trim() === 'OK') {
                $('#mDigestSnooze').modal('hide');
                safeReload();
            } else {
                if (typeof sysAlert === 'function') sysAlert(r, 'danger'); else alert(r);
            }
        });
    });

    $('#mDigestSnoozeClear').on('click', function() {
        var btn = $('#mDigestSnoozeSave');
        btn.prop('disabled', true);
        $.post('includes/ajax_digest_snooze.php', {
            id: $('#mDigestSnoozeId').val(),
            action: 'clear'
        }, function(r) {
            btn.prop('disabled', false);
            if ((r || '').trim() === 'OK') {
                $('#mDigestSnooze').modal('hide');
                safeReload();
            } else {
                if (typeof sysAlert === 'function') sysAlert(r, 'danger'); else alert(r);
            }
        });
    });

    // --- Stavy a priority požadavků ---
    $(document).on('click', '.btn-toggle-priority', function(e) {
        e.preventDefault(); e.stopImmediatePropagation();
        var icon = $(this), reqId = icon.data('id'), newPrio = (icon.data('prio') == 1) ? 0 : 1;
        icon.removeClass('glyphicon-exclamation-sign glyphicon-unchecked text-danger text-muted').addClass('glyphicon-refresh spinning');
        $.post('includes/ajax_update_priority.php', { id: reqId, priorita: newPrio }, function(r) {
            if (r.trim() === "OK") safeReload(); else { if (typeof sysAlert === "function") sysAlert("Chyba: " + r, "danger"); safeReload(); }
        });
    });

    $(document).on('click', '.btn-postpone-req', function(e) {
        e.stopPropagation(); var reqId = $(this).data('id');
        sysPrompt("Opravdu chcete tento požadavek ODLOŽIT k ledu?", function(reason) {
            $.post('includes/ajax_set_req_status.php', { id: reqId, status: 8, poznamka: reason }, function(r) {
                if(r.trim() === "OK") safeReload(); else sysAlert(r, "danger");
            });
        }, "Ano, odložit", "btn-warning");
    });

    $(document).on('click', '.btn-revive-req', function(e) {
        e.stopPropagation(); var reqId = $(this).data('id');
        sysPrompt("OŽIVENÍ POŽADAVKU<br>Napište kolegům krátký důvod:", function(reason) {
            $.post('includes/ajax_set_req_status.php', { id: reqId, status: 1, poznamka: reason }, function(r) {
                if(r.trim() === "OK") { filters.rejected = false; safeReload(); } else sysAlert(r, "danger");
            });
        }, "Ano, oživit", "btn-success");
    });

    $(document).on('click', '.btn-postpone-offer', function(e) {
        e.preventDefault(); e.stopPropagation();
        var offerId = $(this).data('id');
        sysPrompt("Odložit tuto nabídku K LEDU?<br><small class=\"text-muted\">Zmizí z fronty (není to KO).</small>", function(reason) {
            $.post('includes/ajax_postpone_offer.php', { id: offerId, action: 'set', poznamka: reason }, function(r) {
                r = (r || '').trim();
                if (r === 'OK') safeReload();
                else sysAlert(r || 'Chyba', 'danger');
            });
        }, "Odložit", "btn-warning");
    });

    $(document).on('click', '.btn-winning-offer', function(e) {
        e.preventDefault(); e.stopPropagation();
        var btn = $(this), offerId = btn.data('id'), cnt = btn.data('count') || '?';
        sysPrompt("Vítězná nabídka — poslat ostatní k ledu?<br><small class=\"text-muted\">" + cnt + " alternativ bude schováno (lze odledovat).</small>", function(reason) {
            btn.prop('disabled', true);
            $.post('includes/ajax_postpone_offer.php', { id: offerId, action: 'win', poznamka: reason }, function(r) {
                r = (r || '').trim();
                if (r.indexOf('OK') === 0) safeReload();
                else sysAlert(r || 'Chyba', 'danger');
                btn.prop('disabled', false);
            });
        }, "Ano, ostatní k ledu", "btn-success");
    });

    $(document).on('click', '.btn-revive-offer', function(e) {
        e.preventDefault(); e.stopPropagation();
        var offerId = $(this).data('id');
        sysPrompt("Odledovat tuto nabídku?<br><small class=\"text-muted\">Vrátí se do původního stavu ve workflow.</small>", function(reason) {
            $.post('includes/ajax_postpone_offer.php', { id: offerId, action: 'revive', poznamka: reason }, function(r) {
                r = (r || '').trim();
                if (r === 'OK') { filters.rejected = false; safeReload(); }
                else sysAlert(r || 'Chyba', 'danger');
            });
        }, "Odledovat", "btn-success");
    });

    $(document).on('click', '.btn-revive-all-offers', function(e) {
        e.preventDefault(); e.stopPropagation();
        var reqId = $(this).data('req-id');
        sysPrompt("Odledovat všechny nabídky k ledu u tohoto požadavku?", function(reason) {
            $.post('includes/ajax_postpone_offer.php', { id: 0, req_id: reqId, action: 'revive_all', poznamka: reason }, function(r) {
                r = (r || '').trim();
                if (r.indexOf('OK') === 0) { filters.rejected = false; safeReload(); }
                else sysAlert(r || 'Chyba', 'danger');
            });
        }, "Odledovat vše", "btn-success");
    });

    $(document).on('click', '.btn-show-led-offers', function(e) {
        e.preventDefault(); e.stopPropagation();
        var reqId = $(this).data('req-id');
        filters.rejected = true;
        filters.search = '#' + reqId;
        applyFilters();
        $('#searchInput').val('#' + reqId);
    });

    $(document).on('click', '.btn-delete-req', function(e) {
        e.stopPropagation();
        $('#mCancelReqId').val($(this).data('id'));
        $('#mCancelReqReason').val('');
        $('#mCancelReqModal').modal('show');
    });

    // --- Inline komentáře ---
    $(document).on('click', '.btn-inline-comment', function() {
        var btn = $(this), id = btn.data('id'), type = btn.data('type'), urgent = btn.data('urgent') || 0;
        var text = $('.inline-comment-text[data-id="'+id+'"][data-type="'+type+'"]').val().trim();
        if (!text) return;
        btn.prop('disabled', true);
        $.post('includes/ajax_add_comment.php', { id_entity: id, typ_entity: type, text: text, is_urgent: urgent }, function(r) {
            if (r.trim() === "OK") safeReload();
            else { if (typeof sysAlert === "function") sysAlert(r, "danger"); else alert(r); btn.prop('disabled', false); }
        });
    });

    $(document).on('keypress', '.inline-comment-text', function(e) {
        if(e.which == 13) $('.btn-inline-comment[data-id="'+$(this).data('id')+'"][data-type="'+$(this).data('type')+'"][data-urgent="0"]').click();
    });

    // --- Přiřazení nákupčího k požadavku ---
    function getCurrentUid() {
        return parseInt($('body').data('current-uid') || 0, 10);
    }

    $(document).on('click', '.btn-claim-request', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var b = $(this);
        var reqId = b.data('id');
        var resitelId = parseInt(b.data('resitel-id') || 0, 10);
        var resitelJmeno = (b.data('resitel-jmeno') || '').toString().trim();
        var currentUid = getCurrentUid();
        var msg;

        if (resitelId > 0 && resitelId !== currentUid) {
            msg = 'Požadavek aktuálně řeší ' + (resitelJmeno || 'jiný nákupčí') + '.\n\nOpravdu ho chcete převzít?';
        } else {
            msg = 'Převzít tento požadavek k řešení?';
        }
        if (!confirm(msg)) return;

        b.prop('disabled', true);
        $.post('includes/ajax_claim_request.php', { id: reqId }, function(r) {
            if (r.trim() === 'OK') {
                reloadRequestDetailIfOpen();
                safeReload();
            } else {
                if (typeof sysAlert === 'function') sysAlert(r, 'danger'); else alert(r);
                b.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.btn-release-request', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var b = $(this);
        if (!confirm('Opravdu chcete uvolnit tento požadavek? (Vzdávám to)')) return;

        b.prop('disabled', true);
        $.post('includes/ajax_release_request.php', { id: b.data('id') }, function(r) {
            if (r.trim() === 'OK') {
                reloadRequestDetailIfOpen();
                safeReload();
            } else {
                if (typeof sysAlert === 'function') sysAlert(r, 'danger'); else alert(r);
                b.prop('disabled', false);
            }
        });
    });

    // --- Práce s nabídkou (Cenotvorba, měny) ---
    $(document).on('click', '.btn-edit-offer', function() {
        var b = $(this);
        $('#mNN').modal('show').attr('data-mode', 'edit');
        $('#mNN .modal-title').html('<i class="glyphicon glyphicon-pencil"></i> Upravit nabídku');
        $('#mNNSave').text('ULOŽIT ZMĚNY').addClass('btn-warning').removeClass('btn-primary');
        $('#mNNId').val(b.data('id'));
        $('#mNNDod').val(b.data('dodavatel')).trigger('change');
        $('#mNNCena').val(b.data('cena'));
        $('#mNNMena').val(b.data('mena') || 'CZK').trigger('change');
        $('#mNNMoqQty').val(b.data('moq-qty'));
        $('#mNNMoqMj').val(b.data('moq-mj'));
        $('#mNNPozn').val('');
        if (b.data('moq-qty') !== "") $('#mNNMoqMjWrapper').show(); else $('#mNNMoqMjWrapper').hide();
        $('#mNNBezCenyWrapper').hide();
        $('#mNNCena').prop('disabled', false);
    });

    $(document).on('click', '.btn-add-offer', function() {
        $('#mNN').attr('data-mode', 'add').modal('show');
        $('#mNN .modal-title').html('<i class="glyphicon glyphicon-plus"></i> Nová nabídka');
        $('#mNNSave').text('Uložit nabídku').addClass('btn-primary').removeClass('btn-warning');
        $('#mNNId').val($(this).data('id'));
        $('#mNNCena, #mNNMoqQty, #mNNPozn').val('');
        $('#mNNDod').val('').trigger('change');
        $('#mNNMena').val('CZK').trigger('change');
        $('#mNNMoqMjWrapper').hide();
        $('#mNNBezCeny').prop('checked', false);
        $('#mNNBezCenyWrapper').show();
        toggleMNNBezCeny();
    });

    function toggleMNNBezCeny() {
        var bez = $('#mNNBezCeny').is(':checked');
        if (bez) {
            $('#mNNCena').val('').prop('disabled', true);
            $('#mNNDopravaWrapper').hide();
        } else {
            $('#mNNCena').prop('disabled', false);
        }
    }

    $('#mNNBezCeny').on('change', toggleMNNBezCeny);

    $('#mNNCena, #mNNMena').on('input change', function() {
        if ($('#mNNBezCeny').is(':checked')) return;
        var cena = parseFloat($('#mNNCena').val()) || 0, mena = $('#mNNMena').val();
        if (cena > 0) $('#mNNDopravaWrapper').slideDown(200); else $('#mNNDopravaWrapper').slideUp(200);
        if ((mena === 'EUR' || mena === 'USD') && cena > 0) {
            var czk = cena * ((mena === 'EUR') ? CNB_EUR_RATE : CNB_USD_RATE);
            $('#mNNCzkCalc').text(czk.toFixed(2));
            $('#mNNKurzInfo').slideDown(200);
        } else $('#mNNKurzInfo').slideUp(200);
    });

    $('#mNNDoprava').on('change', function() {
        if ($(this).val() === 'custom') $('#mNNDopravaCustomWrapper').slideDown(200); else $('#mNNDopravaCustomWrapper').slideUp(200);
    });

    $('#mNN').on('show.bs.modal', function () {
        $('#mNNDoprava, #mNNDopravaCustom').val('');
        $('#mNNDopravaWrapper, #mNNDopravaCustomWrapper, #mNNKurzInfo').hide();
        if ($(this).attr('data-mode') === 'add') $('#mNNMena').val('CZK');
    });

    $(document).on('input', '#mNNMoqQty', function() {
        if ($(this).val() !== "") $('#mNNMoqMjWrapper').fadeIn(200); else $('#mNNMoqMjWrapper').fadeOut(200);
    });

    // --- Práce s Požadavkem (Úprava, rušení) ---
    $(document).on('click', '.btn-edit-req', function() {
        var b = $(this);
        $('#mEditReqId').val(b.data('id')); $('#mEditReqTitle').text(b.data('sur'));
        $('#mEditReqBio').prop('checked', b.data('bio') == 1); $('#mEditReqVegan').prop('checked', b.data('vegan') == 1);
        $('#mEditReqBezlepek').prop('checked', b.data('bezlepek') == 1); $('#mEditReqKosher').prop('checked', b.data('kosher') == 1);
        $('#mEditReqHalal').prop('checked', b.data('halal') == 1);         $('#mEditReqPrio').val(b.data('prio'));
        $('#mEditReqMnozstvi').val(b.data('mnozstvi') || '');
        $('#mEditReqMj').val(b.data('mj') || 'kg');
        $('#mEditReqNote').val((b.data('note') === null || b.data('note') === 'null') ? '' : b.data('note'));
        var zakIdsRaw = b.data('zakaznici-ids'), selectedIds = (zakIdsRaw && zakIdsRaw.toString().trim() !== "") ? zakIdsRaw.toString().split(',') : [];
        $('#mEditReqZakaznici').val(selectedIds).trigger('change');
        var prodIdsRaw = b.data('produkty-ids'), selectedProdIds = (prodIdsRaw && prodIdsRaw.toString().trim() !== "") ? prodIdsRaw.toString().split(',') : [];
        if ($('#mEditReqProdukty').length) {
            $('#mEditReqProdukty').val(selectedProdIds).trigger('change');
        }
        $('#mEditReq').modal('show');
    });

    $('#mEditReqSave').on('click', function() {
        var btn = $(this); btn.prop('disabled', true).text('Ukládám...');
        $.post('includes/ajax_update_request.php', {
            id: $('#mEditReqId').val(), bio: $('#mEditReqBio').is(':checked') ? 1 : 0, vegan: $('#mEditReqVegan').is(':checked') ? 1 : 0,
            bezlepek: $('#mEditReqBezlepek').is(':checked') ? 1 : 0, kosher: $('#mEditReqKosher').is(':checked') ? 1 : 0, halal: $('#mEditReqHalal').is(':checked') ? 1 : 0,
            priorita: $('#mEditReqPrio').val(), mnozstvi: $('#mEditReqMnozstvi').val(), mj: $('#mEditReqMj').val(),
            poznamka: $('#mEditReqNote').val(), zakaznici: $('#mEditReqZakaznici').val(),
            produkty: $('#mEditReqProdukty').length ? ($('#mEditReqProdukty').val() || []) : []
        }, function(r) {
            if(r.trim() == "OK") { $('#mEditReq').modal('hide'); safeReload(); }
            else { if (typeof sysAlert === "function") sysAlert(r, "danger"); else alert(r); }
            btn.prop('disabled', false).text('ULOŽIT ZMĚNY');
        });
    });

    $('#btnOpenCancelReq').click(function() {
        $('#mCancelReqId').val($('#mEditReqId').val()); $('#mCancelReqReason').val('');
        $('#mEditReq').modal('hide');
        setTimeout(function() { $('#mCancelReqModal').modal('show'); }, 400);
    });

    $('#mCancelReqSave').click(function() {
        var reason = $('#mCancelReqReason').val().trim();
        if(!reason) { sysAlert("Vyplňte prosím důvod zrušení.", "warning"); return; }
        $.post('includes/ajax_cancel_request.php', { id: $('#mCancelReqId').val(), poznamka: reason }, function(r) {
            if(r.trim() === "OK") { $('#mCancelReqModal').modal('hide'); safeReload(); }
            else { if (typeof sysAlert === "function") sysAlert(r, "danger"); else alert(r); }
        });
    });

    $('#mAddReqSave').on('click', function() {
        submitAddRequest(null, $(this));
    });

    $('#btnDupAppend').on('click', function() {
        if (!pendingNewRequest) return;
        var existId = $('#mDuplicateWarning').data('exist-id');
        var btn = $(this).prop('disabled', true);
        $.post('includes/ajax_append_request_links.php', {
            id: existId,
            zakaznici: pendingNewRequest.zakaznici || [],
            produkty: pendingNewRequest.produkty || []
        }, function(r) {
            btn.prop('disabled', false);
            if ((r || '').trim() === 'OK') {
                $('#mDuplicateWarning').modal('hide');
                pendingNewRequest = null;
                safeReload();
            } else {
                if (typeof sysAlert === 'function') sysAlert(r, 'danger'); else alert(r);
            }
        });
    });

    $('#btnDupForce').on('click', function() {
        if (!pendingNewRequest) return;
        $('#mDuplicateWarning').modal('hide');
        var payload = pendingNewRequest;
        payload.force_create = 1;
        var btn = $('#mAddReqSave');
        btn.prop('disabled', true).text('Zakládám...');
        $.post('includes/ajax_add_request.php', payload, function(r) {
            handleAddRequestResponse(r, btn, '#mAddReq');
        });
    });

    $('#btnDupGoTo').on('click', function() {
        var existId = $('#mDuplicateWarning').data('exist-id');
        $('#mDuplicateWarning').modal('hide');
        pendingNewRequest = null;
        if (existId) {
            filters.search = '#' + existId;
            $('#searchInput').val('#' + existId);
            applyFilters();
            var $card = $('.req-card[data-req-id="' + existId + '"]');
            if ($card.length) {
                $('html, body').animate({ scrollTop: $card.offset().top - 120 }, 300);
            }
        }
    });

    // --- Změny stavů a workflow ---
    $(document).on('click', '.btn-prompt-qty-note', function() {
        $('#mQNId').val($(this).data('id')); $('#mQNStatus').val($(this).data('status')); $('#mQNQty, #mQNNote').val(''); $('#mQtyNote').modal('show');
    });

    $('#mQNSave').on('click', function() {
        var qty = $('#mQNQty').val().trim(); if(!qty) { sysAlert("Zadejte požadované množství.", "warning"); return; }
        var btn = $(this); btn.prop('disabled', true).text('Ukládám...');
        $.post('includes/update_status_nabidka.php', { id: $('#mQNId').val(), status: $('#mQNStatus').val(), qty: qty, poznamka: $('#mQNNote').val() }, function(r) {
            var msg = (typeof r === 'string') ? r.trim() : '';
            if (msg && msg.indexOf('Nelze') === 0) {
                if (typeof sysAlert === 'function') sysAlert(msg, 'danger'); else alert(msg);
                btn.prop('disabled', false).text('Potvrdit schválení');
                return;
            }
            $('#mQtyNote').modal('hide'); btn.prop('disabled', false).text('Potvrdit schválení'); safeReload();
        });
    });

    $(document).on('click', '.btn-wf-direct, .btn-wf-check', function(e) {
        e.preventDefault(); var btn = $(this); btn.prop('disabled', true).html('<i class="glyphicon glyphicon-refresh spinning"></i>');
        var newStatus = btn.data('status');
        $.post('includes/update_status_nabidka.php', { id: btn.data('id'), status: newStatus, poznamka: 'Systémová akce: ' + btn.text().trim() }, function(r) {
            var msg = (typeof r === 'string') ? r.trim() : '';
            if (msg && (msg.indexOf('Nelze') === 0 || msg.indexOf('Chyba') === 0)) {
                if (typeof sysAlert === 'function') sysAlert(msg, 'danger'); else alert(msg);
                safeReload();
                return;
            }
            if (parseInt(newStatus, 10) === 6) {
                var winDone = false;
                var afterTestOk = function() {
                    safeReload({ closeDetail: true, forceBoard: true });
                };
                sysConfirm('Poslat ostatní nabídky tohoto požadavku k ledu?', function() {
                    winDone = true;
                    $.post('includes/ajax_postpone_offer.php', { id: btn.data('id'), action: 'win', poznamka: '' }, afterTestOk);
                }, 'Ano, ostatní k ledu', 'btn-success');
                $('#mSystemAlert').one('hidden.bs.modal', function() {
                    if (!winDone) afterTestOk();
                });
            } else {
                safeReload();
            }
        });
    });

    $(document).on('click', '.btn-wf-nutri-deferred', function(e) {
        e.preventDefault();
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="glyphicon glyphicon-refresh spinning"></i>');
        $.post('includes/update_status_nabidka.php', {
            id: btn.data('id'),
            status: 'no_change',
            poznamka: 'Nutriční hodnoty schváleny — čeká se na doplnění ceny od Nákupu před objednávkou vzorku'
        }, function() { safeReload(); });
    });

    $(document).on('click', '.btn-prompt-reason', function() {
        var b = $(this); $('#mReasonId').val(b.data('id')); $('#mReasonStatus').val(b.data('status')); $('#mReasonText').val('');
        $('#mReason .modal-header').css('background', b.data('status') == 9 ? '#f0ad4e' : '#d9534f'); $('#mReason').modal('show');
    });

    $('#mReasonSave').on('click', function() {
        var txt = $('#mReasonText').val().trim(); if(!txt) { sysAlert("Zadejte prosím důvod.", "warning"); return; }
        var btn = $(this); btn.prop('disabled', true).text('Ukládám...');
        $.post('includes/update_status_nabidka.php', { id: $('#mReasonId').val(), status: $('#mReasonStatus').val(), poznamka: txt }, function() {
            $('#mReason').modal('hide'); btn.prop('disabled', false).text('Potvrdit akci'); safeReload();
        });
    });

    $(document).on('click', '.btn-wf-delegace', function(e) {
        e.preventDefault();
        var b = $(this);
        $('#mWfDelegaceId').val(b.data('id'));
        $('#mWfDelegaceKomu').val(b.data('komu') || 'nakup');
        $('#mWfDelegaceDuvod').val('');
        $('#mWorkflowDelegace').modal('show');
    });

    $('#mWfDelegaceSave').on('click', function() {
        var duvod = $('#mWfDelegaceDuvod').val().trim();
        if (!duvod) { sysAlert('Popište, co brání nebo co je potřeba vyřešit.', 'warning'); return; }
        var btn = $(this); btn.prop('disabled', true).text('Ukládám…');
        $.post('includes/ajax_workflow_delegace.php', {
            id_nabidka: $('#mWfDelegaceId').val(),
            komu: $('#mWfDelegaceKomu').val(),
            duvod: duvod,
            action: 'set'
        }, function(r) {
            r = (r || '').trim();
            if (r === 'OK') {
                $('#mWorkflowDelegace').modal('hide');
                safeReload();
            } else {
                sysAlert(r || 'Chyba uložení', 'danger');
            }
            btn.prop('disabled', false).text('Předat úkol');
        }).fail(function() {
            sysAlert('Chyba komunikace se serverem.', 'danger');
            btn.prop('disabled', false).text('Předat úkol');
        });
    });

    $(document).on('click', '.btn-wf-delegace-clear', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        sysConfirm('Delegace vyřešena — vrátit workflow původnímu oddělení?', function() {
            $.post('includes/ajax_workflow_delegace.php', { id_nabidka: id, action: 'clear' }, function(r) {
                r = (r || '').trim();
                if (r === 'OK') safeReload();
                else sysAlert(r || 'Chyba', 'danger');
            });
        });
    });

    $(document).on('click', '.btn-quality-approve', function() {
        var b = $(this); $('#mQualId').val(b.data('id')); $('#mQualStatus').val(b.data('status')); $('#mQualSarze').val(b.data('sarze') || ''); $('#mQualityModal').modal('show');
    });

    $('#mQualSave').on('click', function() {
        var sarze = $('#mQualSarze').val().trim(); if(!sarze) { sysAlert("Vyplňte prosím číslo šarže z COA dokumentu.", "warning"); $('#mQualSarze').focus(); return; }
        var btn = $(this); btn.prop('disabled', true).text('Ukládám...');
        $.post('includes/update_status_nabidka.php', { id: $('#mQualId').val(), status: $('#mQualStatus').val(), sarze: sarze, poznamka: 'Systémová akce: KVALITA OK (Šarže: ' + sarze + ')' }, function() {
            $('#mQualityModal').modal('hide'); btn.prop('disabled', false).text('Potvrdit schválení'); safeReload();
        });
    });

    // --- Nahrávání souborů a zobrazení ---
    $(document).on('click', '.btn-open-files-modal', function(e) {
        e.preventDefault();
        $('#mOfferFilesContent').html('<div style="text-align:center; color:#999; padding: 20px;"><i class="glyphicon glyphicon-refresh spinning"></i> Načítám seznam...</div>');
        $('#mOfferFiles').modal('show');
        $.post('includes/ajax_offer_files_modal.php', { id: $(this).data('id') }, function(data) { $('#mOfferFilesContent').html(data); }).fail(function() { $('#mOfferFilesContent').html('<div class="alert alert-danger">Chyba při načítání souborů ze serveru.</div>'); });
    });

    $(document).on('click', '.btn-toggle-upload', function(e) { e.preventDefault(); $('#upload' + $(this).data('target')).slideToggle(); $('#btnDoUpload').slideDown(); });
    $(document).on('change', '#mWFFileLab', function() { if (this.files.length > 0) $('#mWFSarzeBox').slideDown(); else if (!cur.hasLab) $('#mWFSarzeBox').slideUp(); });

    $(document).on('click', '.btn-wf', function() {
        var b = $(this); cur.id = b.data('id'); cur.st = b.data('status');
        $('#mWFNote').val(''); $('#mWFSarze').val(b.data('sarze') || '');
        $('#mWFQty, #mWFFileSpec, #mWFFileLab, #mWFFileOther').val(''); $('#mWFFileStatus').html('');
        $('#btnDoUpload, #uploadTDS, #uploadCOA, #uploadOther').hide();
        $('#mWFQtyBox').toggle(b.data('need-qty') == 1); $('#mWFItemCodeBox').toggle(b.data('final') == 1);
        var fStr = b.data('files'); cur.hasLab = (fStr && fStr.toString().indexOf('~lab') !== -1);
        var h = { TDS: '', COA: '', Other: '' };
        if (fStr && fStr.toString().length > 0) {
            fStr.toString().split('^').forEach(function(f) {
                if(!f) return; var p = f.split('~');
                var html = '<div style="display:flex; justify-content:space-between; margin-bottom:2px; font-size:11px; background:#f9f9f9; padding:2px 5px; border-radius:3px;"><span><i class="glyphicon glyphicon-file"></i> ' + p[0] + '</span><i class="glyphicon glyphicon-remove text-danger btn-delete-file" style="cursor:pointer;" data-id="'+cur.id+'" data-file="'+f+'"></i></div>';
                if (p[1] === 'spec') h.TDS += html; else if (p[1] === 'lab') h.COA += html; else h.Other += html;
            });
        }
        $('#listTDS').html(h.TDS || '<em class="text-muted small">Žádný</em>'); $('#listCOA').html(h.COA || '<em class="text-muted small">Žádný</em>'); $('#listOther').html(h.Other || '<em class="text-muted small">Žádný</em>');
        if (cur.hasLab || b.data('sarze')) $('#mWFSarzeBox').show(); else $('#mWFSarzeBox').hide(); $('#mWF').modal('show');
    });

    function executeUpload(callback) {
        var fd = new FormData(); fd.append('id_nabidka', cur.id); var hasFiles = false;
        var iS = $('#mWFFileSpec')[0]; for(var i=0; i<iS.files.length; i++) { fd.append('files_spec[]', iS.files[i]); hasFiles=true; }
        var iL = $('#mWFFileLab')[0]; for(var i=0; i<iL.files.length; i++) { fd.append('files_lab[]', iL.files[i]); hasFiles=true; }
        var iO = $('#mWFFileOther')[0]; for(var i=0; i<iO.files.length; i++) { fd.append('files_other[]', iO.files[i]); hasFiles=true; }

        if(!hasFiles) { if(callback) callback(); return; }
        $('#mWFFileStatus').html('<span class="text-info"><i class="glyphicon glyphicon-refresh spinning"></i> Nahrávám...</span>');

        $.ajax({
            url: 'includes/upload_to_nextcloud.php', type: 'POST', data: fd, contentType: false, processData: false,
            success: function(response) {
                if (response.trim() === "OK") {
                    $('#mWFFileStatus').html('<b class="text-success">Nahráno.</b>');
                    $.post('includes/ajax_get_offer_files.php', { id_nabidka: cur.id }, function(data) {
                        try {
                            var json = JSON.parse(data);
                            $('#listTDS').html(json.TDS || '<em class="text-muted small">Žádný</em>'); $('#listCOA').html(json.COA || '<em class="text-muted small">Žádný</em>'); $('#listOther').html(json.Other || '<em class="text-muted small">Žádný</em>');
                            if (json.hasLab) { cur.hasLab = true; $('#mWFSarzeBox').slideDown(); }
                        } catch(e) {}
                    });
                    $('#mWFFileSpec, #mWFFileLab, #mWFFileOther').val(''); $('#btnDoUpload, #uploadTDS, #uploadCOA, #uploadOther').hide();
                    setTimeout(function() { $('#mWFFileStatus').empty(); }, 3000);
                    safeReload(); if(callback) callback();
                } else { $('#mWFFileStatus').html('<b class="text-danger">' + response + '</b>'); }
            }
        });
    }

    $('#btnDoUpload').on('click', function() { executeUpload(); });
    $('#mWFSave').on('click', function() {
        var btn = $(this); var sarzeVal = $('#mWFSarze').val(); btn.prop('disabled', true).text('Ukládám...');
        executeUpload(function() {
            $.post('includes/update_status_nabidka.php', { id: cur.id, status: cur.st, poznamka: $('#mWFNote').val(), qty: $('#mWFQty').val(), sarze: sarzeVal }, function() { $('#mWF').modal('hide'); btn.prop('disabled', false).text('Potvrdit'); safeReload(); });
        });
    });
    $(document).on('click', '.btn-delete-file', function() {
        var fileId = $(this).data('id'), fileName = $(this).data('file');
        sysConfirm("Opravdu chcete smazat tento soubor?", function() { $.post('includes/delete_file.php', { id: fileId, file: fileName }, function() { $('#mWF').modal('hide'); safeReload(); }); });
    });

    // --- Otevření detailu požadavku ---
    $(document).on('click', '.btn-open-detail', function(e) {
        if ($(e.target).closest('.btn-edit-req, .btn-delete-req, .btn-ping-purchasing, .btn-urge-task, .btn-toggle-priority, .btn-postpone-req, .btn-revive-req, .btn-digest-snooze, .btn-postpone-offer, .btn-revive-offer, .btn-winning-offer, .btn-show-led-offers, .btn-revive-all-offers').length > 0) return;
        openRequestDetail($(this).data('id'));
    });

    // Souhrn — celý řádek otevře detail (e-mail náhled stále používá odkaz v buňce)
    $(document).on('click', '.digest-row', function(e) {
        if ($(e.target).closest('a, button, input, .btn-edit-offer, .btn-delete-ajax').length) return;
        var reqId = parseInt($(this).data('req-id'), 10);
        if (!reqId || !$('#mReqDetail').length) return;
        e.preventDefault();
        openRequestDetail(reqId);
        window.history.replaceState(null, null, 'index.php?Souhrn=1&req_id=' + reqId);
    });
    $(document).on('keydown', '.digest-row', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    // Archiv — klikací řádky + filtry
    $(document).on('click', '.archiv-row', function(e) {
        if ($(e.target).closest('.no-archiv-detail, a, button, input, .btn-edit-offer, .btn-delete-ajax').length) return;
        var reqId = parseInt($(this).data('req-id'), 10);
        if (!reqId || !$('#mReqDetail').length) return;
        e.preventDefault();
        openRequestDetail(reqId);
        window.history.replaceState(null, null, 'index.php?Archiv=1&req_id=' + reqId);
    });
    $(document).on('keydown', '.archiv-row', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    function applyArchivFilters() {
        if (!$('.archiv-page').length) return;
        var q = ($('#archivSearch').val() || '').toString().toLowerCase().trim();
        var st = $('#archivStatusFilter .archiv-status-btn.active').data('status') || 'bez_ko';
        var showKo = $('#archivShowKo').attr('data-on') === '1';
        var visible = 0;
        var koHidden = 0;
        $('.archiv-row').each(function() {
            var $r = $(this);
            var rowSt = parseInt($r.data('status'), 10);
            var isKo = (rowSt === 5 || rowSt === 7);
            var blob = ($r.data('search') || '').toString();
            var okStatus;
            if (st === 'bez_ko' || st === 'all') {
                if (isKo) {
                    okStatus = showKo;
                    if (!showKo) koHidden++;
                } else {
                    okStatus = true;
                }
            } else {
                okStatus = String(st) === String(rowSt);
            }
            var okSearch = !q || blob.indexOf(q) !== -1;
            var show = okStatus && okSearch;
            $r.toggleClass('is-filtered-out', !show);
            if (show) visible++;
        });
        var countTxt = 'Zobrazeno: ' + visible;
        if (!showKo && koHidden > 0 && (st === 'bez_ko' || st === 'all')) {
            countTxt += ' · skryto KO: ' + koHidden;
        }
        $('#archivFilterCount').text(countTxt);
        $('.archiv-panel').each(function() {
            var n = $(this).find('.archiv-row:not(.is-filtered-out)').length;
            $(this).find('.archiv-panel-count').text(n);
        });
    }

    $(document).on('input', '#archivSearch', applyArchivFilters);
    $(document).on('click', '.archiv-status-btn', function() {
        $('.archiv-status-btn').removeClass('btn-primary active').addClass('btn-default');
        $(this).removeClass('btn-default').addClass('btn-primary active');
        applyArchivFilters();
    });
    $(document).on('click', '#archivShowKo', function() {
        var on = $(this).attr('data-on') !== '1';
        var badge = $(this).find('.badge').prop('outerHTML') || '';
        $(this).attr('data-on', on ? '1' : '0')
            .toggleClass('btn-danger', on)
            .toggleClass('btn-default', !on)
            .html((on
                ? '<i class="glyphicon glyphicon-ban-circle"></i> Skrýt KO'
                : '<i class="glyphicon glyphicon-ban-circle"></i> Zobrazit KO') + (badge ? ' ' + badge : ''));
        applyArchivFilters();
    });
    if ($('.archiv-page').length) applyArchivFilters();

    // Auto-open detail z URL (Archiv i Souhrn)
    $(document).on('click', '.digest-page a[href*="req_id="]', function(e) {
        var match = (this.getAttribute('href') || '').match(/[?&]req_id=(\d+)/);
        if (!match || !$('#mReqDetail').length) return;
        e.preventDefault();
        openRequestDetail(match[1]);
        window.history.replaceState(null, null, 'index.php?Souhrn=1&req_id=' + match[1]);
    });

    $('#mReqDetail').on('hidden.bs.modal', function() {
        if (/[?&]req_id=/.test(window.location.search)) {
            window.history.replaceState(null, null, digestReturnUrl());
        }
    });

    var urlParams = new URLSearchParams(window.location.search);
    var autoOpenId = urlParams.get('req_id');
    if (autoOpenId && $('#mReqDetail').length) {
        var delay = urlParams.has('Souhrn') ? 80 : 500;
        setTimeout(function() {
            openRequestDetail(autoOpenId);
            window.history.replaceState(null, null, digestReturnUrl());
        }, delay);
    }

    // --- Dev Feedback Modál (Původní plovoucí tlačítko na nápady dole) ---
    function loadDevTasks() {
        $('#feedbackList').html('<div class="feedback-msg" style="text-align:center; padding:15px;"><i class="glyphicon glyphicon-refresh spinning"></i> Načítám historii úprav...</div>');
        $.post('includes/ajax_dev_pozadavky.php', { action: 'load' }, function(html) { $('#feedbackList').html(html); });
    }

    // Toto patří původnímu plovoucímu tlačítku dole:
    $(document).on('click', '#btnOpenFeedback', function() { $('#mFeedback').modal('show'); loadDevTasks(); });

    // Toto je náš nový handler pro Novinky nahoře:
    $(document).on('click', '#btnOpenNews', function(e) {
        e.preventDefault();
        $.post('includes/ajax_mark_as_read.php', {}, function() {
            $('#btnOpenNews .badge').text('');
            window.location.href = 'index.php?Aktuality=1';
        });
    });

    $(document).on('click', '#btnSaveFeedback', function() {
        var text = $('#feedbackText').val().trim(); if (!text) return;
        var btn = $(this); btn.prop('disabled', true).text('Odesílám...');
        $.post('includes/ajax_dev_pozadavky.php', { action: 'add', text: text }, function(r) {
            btn.prop('disabled', false).text('Odeslat nápad');
            if (r.trim() === "OK") { $('#feedbackText').val(''); loadDevTasks(); }
            else { if (typeof sysAlert === "function") sysAlert("Chyba: " + r, "danger"); else alert("Chyba: " + r); }
        });
    });

    $(document).on('click', '.btn-update-dev-task', function() {
        var id = $(this).data('id'), newStatus = $(this).data('status'), btn = $(this), reakce = "";
        if (newStatus == 2) {
            reakce = prompt("Uveďte prosím důvod zamítnutí (povinné):");
            if (reakce === null) return;
            if (reakce.trim() === "") { if (typeof sysAlert === "function") sysAlert("Důvod zamítnutí musí být vyplněn!", "warning"); else alert("Důvod zamítnutí musí být vyplněn!"); return; }
        } else {
            reakce = prompt("Můžete přidat krátký komentář (nepovinné):"); if (reakce === null) return;
        }
        btn.prop('disabled', true).text('...');
        $.post('includes/ajax_dev_pozadavky.php', { action: 'update_status', id: id, status: newStatus, reakce: reakce }, function(r) {
            if (r.trim() === "OK") loadDevTasks();
            else { if (typeof sysAlert === "function") sysAlert("Chyba: " + r, "danger"); else alert("Chyba: " + r); btn.prop('disabled', false).text(newStatus == 1 ? '✔ Vyřešit' : '✖ Zamítnout'); }
        });
    });

    // Prvotní aplikace filtrů
    applyFilters();

    // Timer pro automatický reload změn na pozadí
    setInterval(function() {
        if ($('.modal.in').length === 0) {
            $.get('includes/check_changes.php', function(s) { if (s > localLastChange) { localLastChange = s; safeReload(); } });
        }
    }, 2000);

    // =========================================================================
    // AKORDEON ZOOM PRO HISTORII CHATU U NABÍDEK A POŽADAVKŮ
    // =========================================================================
    // Obsluha kliknutí na box historie (NABÍDKY i POŽADAVKY)
    $(document).on('click', '.offer-comments-wrapper, .offer-sys-msg-container', function(e) {

        // 1. Ochrana kopírování
        if (window.getSelection().toString().length > 0) {
            return;
        }

        // 2. Ochrana tlačítek
        if ($(e.target).closest('a, button, i, .btn, .chat-del-btn, .history-action-btn').length > 0) {
            return;
        }

        var $this = $(this);
        var isExpanded = $this.hasClass('is-expanded');

        // 3. Zavřít ostatní
        $('.offer-comments-wrapper.is-expanded, .offer-sys-msg-container.is-expanded').removeClass('is-expanded');

        // 4. Rozbalit aktuální
        if (!isExpanded) {
            $this.addClass('is-expanded').css('max-height', '').find('.history-expand-hint').remove();
        } else {
            refreshHistoryPreviews();
        }
    });

    // 5. Automatické zavření klikem mimo
    $(document).on('click', function(e) {
        if ($(e.target).closest('.offer-comments-wrapper, .offer-sys-msg-container').length === 0) {
            var hadExpanded = $('.offer-comments-wrapper.is-expanded, .offer-sys-msg-container.is-expanded').length > 0;
            $('.offer-comments-wrapper.is-expanded, .offer-sys-msg-container.is-expanded').removeClass('is-expanded');
            if (hadExpanded) refreshHistoryPreviews();
        }
    });

}); // KONEC $(document).ready()