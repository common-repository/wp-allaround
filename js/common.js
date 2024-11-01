jQuery(document).ready(function($) {
    $("#allaround_connect").click(function() { 
	var apiKey = $("#allaround_api_key").val();
	$("#allaround_connect_result").html('<i>Connecting...please wait !</i>');

        $.post(ajax_object.ajax_url, {
		action: 'connect',
		apiKey: apiKey
	    },function(data) {
    		if (data) {
        	    $("#allaround_connect_result").html(data);
		}
    	    });
    });
});