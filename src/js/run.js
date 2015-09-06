jQuery(document).ready(function($) 
{
    $("#runButton").on("click", function()
    {
        //Unselect button so it's not highlighted. 
        $("#runButton").blur();
        
        //If run button is disabled, don't run test.
        if ($("#runButton").hasClass("button-primary-disabled"))
        {
            alert("Scan is already running!");
            return;
        }
        
        //Disable run button.
        $("#runButton").addClass("button-primary-disabled");
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
