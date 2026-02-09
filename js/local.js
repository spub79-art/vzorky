//Respond to user clicking on plus/minus symbols by showing/hiding rows as appropriate
function toggleVisibility(rowid) {
    var makevisible;
	//Check for the existence of the row and its related toggle image
	var togglerow = document.getElementById("row"+rowid);
    var imgsrc = document.getElementById("img"+rowid).src;
	if (togglerow) {
 	    //Using the source of the image clicked on, determine whether we are
	    //showing or hiding rows. Also, toggle the image.
	    if (/plus/.test(imgsrc)) {
            document.getElementById("img"+rowid).src=imgsrc.replace(/plus/, "minus");
		    makevisible = true;
	    } else {
            document.getElementById("img"+rowid).src=imgsrc.replace(/minus/, "plus");
		    makevisible = false;
	    }
	     //Establish the grouping level of the row that the user clicked on
		 //by stripping the word 'level' from the row class.
	     var togglelevel=parseInt(togglerow.className.replace(/level/,""));
		 //Walk backward through the table rows until we either reach the beginning of the table 
		 //or a row with the same/lower grouping level.
		 for (var i=rowid-1; i>0; i--) {
		     var row=document.getElementById("row"+i);
			 if (row) {
			     var level = parseInt(row.className.replace(/level/,""));
				 //Early exit test - if level found to be same or less than toggling row then exit, no more child rows to toggle
				 if (level<=togglelevel) break;
				 
				 if (makevisible) {
				     //We are making rows visible
					 //Only make visible rows with the next highest grouping level
					 if (level==togglelevel+1) {
				       document.getElementById("row"+i).style.display="";
					 }
				 } else {
				     //We are making rows invisible
					 //Hide all rows with a higher grouping level 
				   if (level>togglelevel) {
				       document.getElementById("row"+i).style.display="none";
					   //Check for toggle images that need to be reset to a 'plus' symbol
  	                   if (document.getElementById("img"+i)) {
                           document.getElementById("img"+i).src=imgsrc.replace(/minus/, "plus");
					   }
				    }
				 }
			 }
		 }
	}
    return false;
}