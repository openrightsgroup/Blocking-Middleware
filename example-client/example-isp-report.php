<html>
<head>
<title>Web Client Test</title>
</head>
<body>
<form id="report">

<div>URL: <input type="text" name="url" /></div>
<div>
Networks:<br />
  <input type="checkbox" name="network" value="FakeISP" />FakeISP <br />
  <input type="checkbox" name="network" value="FakeISP2" />FakeISP2 <br />
</div>

<div>Name: <input type="text" name="name" /></div>
<div>Email: <input type="text" name="email" /></div>

<div>
Message:<br />
<textarea name="message" rows="5" cols="30"></textarea>
</div>


<input type="submit" />
</form>

<div id="results" />

<script src=https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js></script>
<script type="application/javascript">
$(document).ready(function(){
    $('#report').submit(function(){
        var networks = [];
        $('input[name=network]:checked').each(function(){
            networks.push($(this).val());
        });
        var data = {
            'url': $('input[name=url]').val(),
            'networks': networks,

            'reporter': {
                'name': $('input[name=name]').val(),
                'email': $('input[name=email]').val(),
            },
            'message': $('textarea[name=message]').val()
        };
        $.ajax({
            'url': '/1.2/ispreport/submit', 
            'data': JSON.stringify(data), 
            'type': 'POST',
            'contentType': 'application/json',
            'success': function(ret, textStatus, xhr) {
                var s = '';
                $.each(ret.report_ids, function(idx, obj) {
                    s += "Submitted successfully to: " + idx + "<br />";
                });
                $.each(ret.rejected, function(idx, obj) {
                    s += "Rejected for " + idx + ": " + obj + "<br />";
                })
                $('#results').html(s)
            },
            'error': function(xhr, textStatus, errText) {
                if (errText == "Not Found") {
                    $('#results').text("Unknown URL or ISP");
                }
            }
        });

        return false;
    });
                
            

});
</script>
</body>
</html>

