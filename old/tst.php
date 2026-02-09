<%@page contentType="text/html" pageEncoding="UTF-8"%>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd">

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Test Form</title>
        <script type="text/javascript">

            var counter = 0;

            function moreFields() {
                counter++;
                var newFields = document.getElementById("readroot").cloneNode(true);
                newFields.id = '';
                newFields.style.display = 'block';
                var newField = newFields.childNodes;
                for (var i=0;i<newField.length;i++) {
                    var theName = newField[i].name;
                    if (theName)
                        newField[i].name = theName + counter;
                }
                var insertHere = document.getElementById("writeroot");
                insertHere.parentNode.insertBefore(newFields,insertHere);
            }

            window.onload = moreFields;


        </script>
    </head>
    <body>
        <div id="readroot" style="display: none">

            <input type="button" value="Remove review"
                   onclick="this.parentNode.parentNode.removeChild(this.parentNode);" /><br /><br />

            <input name="cd" value="title" />

            <select name="rankingsel">
                <option>Rating</option>
                <option value="excellent">Excellent</option>
                <option value="good">Good</option>
                <option value="ok">OK</option>
                <option value="poor">Poor</option>
                <option value="bad">Bad</option>
            </select><br /><br />

            <textarea rows="5" cols="20" name="review">Short review</textarea>


        </div>

        <form method="post" action="/cgi-bin/show_params.cgi">

            <span id="writeroot"></span>

            <input type="button" onclick="moreFields()" value="Give me more fields!" />
            <input type="submit" value="Send form" />

        </form>
    </body>
</html>