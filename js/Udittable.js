$(document).ready(function() {
	// POJISTKA PROTI DUPLICITĚ:
	// Pokud už v tabulce existuje buňka s třídou 'btn-group' (tlačítka editace),
	// ukončíme skript, aby se nevykreslila podruhé.
	if ($('#editableTable td .btn-group').length > 0) {
		console.log("Bootstable už je inicializován, přeskakuji...");
		return;
	}

	$('#editableTable').SetEditable({
		columnsEd: "1,2,3,4,5,6,7", // Jméno, Login, Heslo, Admin, Vyvoj, Orders, Kvalita
		onEdit: function(columnsEd) {
			// Získání hodnot z buněk (indexy odpovídají pořadí v <tr>)
			var id      = columnsEd[0].childNodes[0].innerHTML;
			var jmeno   = columnsEd[0].childNodes[1].innerHTML;
			var login   = columnsEd[0].childNodes[2].innerHTML;
			var heslo   = columnsEd[0].childNodes[3].innerHTML;
			var admin   = columnsEd[0].childNodes[4].innerHTML;
			var vyvoj   = columnsEd[0].childNodes[5].innerHTML;
			var orders  = columnsEd[0].childNodes[6].innerHTML;
			var kvalita = columnsEd[0].childNodes[7].innerHTML;

			$.ajax({
				type: 'POST',
				url : "Uction.php",
				dataType: "json",
				data: {
					id: id,
					jmeno: jmeno,
					login: login,
					heslo: heslo,
					admin: admin,
					vyvoj: vyvoj,
					orders: orders,
					kvalita: kvalita,
					action: 'edit'
				},
				success: function (response) {
					if(response && response.status) {
						console.log("Uživatel ID " + id + " byl úspěšně aktualizován.");
					}
				},
				error: function() {
					console.error("Chyba při komunikaci se serverem (Uction.php).");
				}
			});
		},
		onBeforeDelete: function(columnsEd) {
			var empId = columnsEd[0].childNodes[0].innerHTML;
			if (confirm("Opravdu chcete smazat uživatele ID " + empId + "?")) {
				$.ajax({
					type: 'POST',
					url : "Uction.php",
					dataType: "json",
					data: {id: empId, action: 'delete'},
					success: function (response) {
						if(response && response.status) {
							console.log("Uživatel smazán.");
						}
					}
				});
			} else {
				return false; // Zruší smazání řádku v UI
			}
		},
	});
});