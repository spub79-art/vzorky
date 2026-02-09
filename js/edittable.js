$( document ).ready(function() {
  $('#editableTable').SetEditable({
	  columnsEd: "1,2,3,5",
	  onEdit: function(columnsEd) {
		var id = columnsEd[0].childNodes[1].innerHTML;
        var uzivatel = columnsEd[0].childNodes[3].innerHTML;
        var ip = columnsEd[0].childNodes[5].innerHTML;
        var povolene = columnsEd[0].childNodes[7].innerHTML;
		var datum = columnsEd[0].childNodes[9].innerHTML;
		var poznamka = columnsEd[0].childNodes[11].innerHTML;
        
		$.ajax({
			type: 'POST',			
			url : "action.php",	
			dataType: "json",					
			data: {id:id, uzivatel:uzivatel, ip:ip, povolene:povolene, datum:datum, poznamka:poznamka, action:'edit'},			
			success: function (response) {
				if(response.status) {
				}						
			}
		});
	  },
	  onBeforeDelete: function(columnsEd) {
	  var empId = columnsEd[0].childNodes[1].innerHTML;
	  $.ajax({
			type: 'POST',			
			url : "action.php",
			dataType: "json",					
			data: {id:empId, action:'delete'},			
			success: function (response) {
				if(response.status) {
				}			
			}
		});
	  },
	});
});