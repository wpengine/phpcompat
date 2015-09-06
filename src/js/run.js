jQuery(document).ready(function($) 
{
    $("#runButton").on("click", function()
    {
        //Unselect button so it's not highlighted. 
        $("#runButton").blur();
        $(".spinner").show();
        $("#testResults").text("");
        var data = 
        {
    		'action': 'wpephpcompat_run_test'
    	};
        
    	jQuery.post(ajax_object.ajax_url, data, function(response) 
        {
            $(".spinner").hide();
            $("#testResults").text(response);
    	});
    });

});
