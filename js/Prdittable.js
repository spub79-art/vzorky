$( document ).ready(function() {
  $('#editableTable').SetEditable({
	  columnsEd: "0,1,2",
	  onEdit: function(columnsEd) {
		var id = columnsEd[0].childNodes[0].innerHTML;
        var produkt = columnsEd[0].childNodes[1].innerHTML;
        /*var dodavatel = columnsEd[0].childNodes[2].innerHTML;*/
        var znacka = columnsEd[0].childNodes[2].innerHTML;

		
        
		$.ajax({
			type: 'POST',			
			url : "paction.php",	
			dataType: "json",					
			data: {id:id, produkt:produkt, znacka:znacka, action:'edit'},			
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
			url : "paction.php",
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