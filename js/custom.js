jQuery(document).ready(function($) {
    $("#allaround_verify").click(function() { 
	$("#allaround_verify_result").html('<i>Connecting...please wait !</i>');

	var apiKey = $("#allaround_api_key").val();

	jQuery.ajax({
	    method: "POST",
	    dataType : "json",
	    url : "https://www.allaroundsiena.com/rest/verify",
	    data: {key: apiKey},
	    success: function(response) {
		$("#allaround_verify_result").html(response.message);
            },
	    error: function(request, status, error) {
		$("#allaround_verify_result").html('<b>Error '+error+'</b>');
	    }
        });
    });
});