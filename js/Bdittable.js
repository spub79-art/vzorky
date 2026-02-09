$( document ).ready(function() {
  $('#editableTable').SetEditable({
	  columnsEd: "1,4,6,10,11,13,15,17,18,19,20,21,22,23,24,25",
	  onEdit: function(columnsEd) {
		var id = columnsEd[0].childNodes[1].innerHTML;
        var datum = columnsEd[0].childNodes[3].innerHTML;
        var ks = columnsEd[0].childNodes[9].innerHTML;
		var idF = columnsEd[0].childNodes[13].innerHTML;
		var delka = columnsEd[0].childNodes[21].innerHTML;
		var idstroj = columnsEd[0].childNodes[23].innerHTML;
		var idbalic = columnsEd[0].childNodes[27].innerHTML;
		var idpredano = columnsEd[0].childNodes[31].innerHTML;
		var poznamka = columnsEd[0].childNodes[35].innerHTML;
		var t1 = columnsEd[0].childNodes[37].innerHTML;
		var t2 = columnsEd[0].childNodes[39].innerHTML;
		var t3 = columnsEd[0].childNodes[41].innerHTML;
		var k1 = columnsEd[0].childNodes[43].innerHTML;
		var k2 = columnsEd[0].childNodes[45].innerHTML;
		var p1 = columnsEd[0].childNodes[47].innerHTML;
		var p2 = columnsEd[0].childNodes[49].innerHTML;
		var p3 = columnsEd[0].childNodes[51].innerHTML;
		
        
		$.ajax({
			type: 'POST',			
			url : "Bction.php",	
			dataType: "json",					
			data: {id:id, datum:datum, ks:ks, idF:idF, delka:delka, idstroj:idstroj, idbalic:idbalic, idpredano:idpredano, poznamka:poznamka, t1:t1, t2:t2, t3:t3, k1:k1, k2:k2, p1:p1, p2:p2, p3:p3,  action:'edit'},			
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
			url : "Bction.php",
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