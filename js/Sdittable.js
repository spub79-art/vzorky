$( document ).ready(function() {
  $('#editableTable').SetEditable({
	  columnsEd: "1",
	  onEdit: function(columnsEd) {
		var id = columnsEd[0].childNodes[0].innerHTML;
        var stroj = columnsEd[0].childNodes[1].innerHTML;
        
		$.ajax({
			type: 'POST',			
			url : "saction.php",	
			dataType: "json",					
			data: {id:id, stroj:stroj, action:'edit'},			
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
			url : "saction.php",
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