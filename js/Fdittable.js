$( document ).ready(function() {
  $('#editableTable').SetEditable({
	  columnsEd: "1,3,4",
	  onEdit: function(columnsEd) {
		var id = columnsEd[0].childNodes[0].innerHTML;
        var typ = columnsEd[0].childNodes[1].innerHTML;
        /*var dodavatel = columnsEd[0].childNodes[2].innerHTML;*/
        var tloustka = columnsEd[0].childNodes[3].innerHTML;
		var sire = columnsEd[0].childNodes[4].innerHTML;
		
        
		$.ajax({
			type: 'POST',			
			url : "faction.php",	
			dataType: "json",					
			data: {id:id, typ:typ, tloustka:tloustka, sire:sire, action:'edit'},			
			success: function (response) {
				if(response.status) {
				}						
			}
		});
	  },
	  onBeforeDelete: function(columnsEd) {
	  var empId = columnsEd[0].childNodes[0].innerHTML;
	  $.ajax({
			type: 'POST',			
			url : "faction.php",
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