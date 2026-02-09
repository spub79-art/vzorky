$( document ).ready(function() {
  $('#editableTable').SetEditable({
	  columnsEd: "1",
	  onEdit: function(columnsEd) {
		var id = columnsEd[0].childNodes[0].innerHTML;
        var nazev = columnsEd[0].childNodes[1].innerHTML;
        
		
        
		$.ajax({
			type: 'POST',			
			url : "Zction.php",	
			dataType: "json",					
			data: {id:id, nazev:nazev, action:'edit'},			
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
			url : "Zction.php",
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