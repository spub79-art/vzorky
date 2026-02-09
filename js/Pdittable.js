$(document).ready(function() {
	// Pokud tabulka neexistuje, skript skončí, aby neházel chyby
	if ($('#editableTable').length === 0) return;

	$('#editableTable').SetEditable({
		columnsEd: "1,2,3,4", // Sloupce, které chceš nechat editovat inline
		onEdit: function(columnsEd) {
			// Místo childNodes použijeme jQuery pro bezpečnější sběr dat
			// Předpokládáme, že ID je v prvním sloupci nebo v data-id atributu
			var row = $(columnsEd[0]).closest('tr');
			var id = row.find('td:first').text();

			// Tady posbíráme data - v budoucnu sem přidáme pole podle tabulky
			var formData = {
				id: id,
				table: CURRENT_TABLE,
				action: 'edit_inline'
			};

			$.ajax({
				type: 'POST',
				url: "includes/update_logic.php", // Tvůj nový sjednocený skript
				data: formData,
				success: function(response) {
					console.log("Upraveno v tabulce: " + CURRENT_TABLE);
				}
			});
		},
		onBeforeDelete: function(columnsEd) {
			var row = $(columnsEd[0]).closest('tr');
			// Získáme ID z prvního sloupce (nebo skrytého pole)
			var id = row.find('td:first').text().trim();

			if (confirm('Opravdu chcete smazat záznam z tabulky ' + CURRENT_TABLE + '?')) {
				$.ajax({
					type: 'GET', // delete_logic.php jsme stavěli na GET
					url: "includes/delete_logic.php",
					data: { id: id, table: CURRENT_TABLE },
					success: function(response) {
						// Po smazání v DB řádek zmizí z webu
						row.fadeOut();
					},
					error: function() {
						alert('Chyba při mazání na serveru.');
					}
				});
			}
			return false; // Zabráníme výchozí akci SetEditable, protože už jsme to smazali přes AJAX
		},
	});
});