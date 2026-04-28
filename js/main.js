$(document).ready(function() {

    // ==========================================
    // 1. KNIHOVNA SUROVIN
    // ==========================================
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
                alert("Chyba při ukládání: " + response);
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
            alert("Chyba: Soubor s modály nebyl správně načten.");
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
        e.preventDefault(); // Zabráníme klasickému přechodu na odkaz (probliknutí stránky)

        var btn = $(this);
        var href = btn.attr('href');
        var row = btn.closest('tr'); // Najdeme celý řádek v tabulce, kde tlačítko leží

        // Získáme text z atributu onclick (pokud tam je) pro potvrzovací hlášku, nebo použijeme výchozí
        var confirmText = "Opravdu chcete tuto položku smazat?";
        var onclickAttr = btn.attr('onclick');
        if (onclickAttr && onclickAttr.indexOf("confirm('") !== -1) {
            confirmText = onclickAttr.split("confirm('")[1].split("')")[0];
        }

        if (confirm(confirmText)) {
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>'); // Ukážeme točící se kolečko

            // Fígl: Odstraníme z URL parametr "redirect", aby nám delete_logic.php vrátil jen text "OK" místo celého HTML
            var ajaxUrl = href.replace(/&redirect=[a-zA-Z0-9_]+/, '');

            $.get(ajaxUrl, function(response) {
                if (response.trim() === "OK") {
                    // Úspěch! Řádek plynule zmizí (fade out) a pak se vymaže z DOMu
                    row.fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    // Pokud to vrátí nějakou chybovou hlášku z PHP (např. že má aktivní požadavky)
                    alert(response);
                    btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');
                }
            }).fail(function() {
                alert("Chyba při komunikaci se serverem.");
                btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');
            });
        }

        // Vynulujeme starý onclick, aby se nevolal dvakrát
        btn.removeAttr('onclick');
    });
});