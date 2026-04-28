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

});