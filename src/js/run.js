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
        //Show the ajax spinner.
        $(".spinner").show();
        //Empty the results textarea.
        $("#testResults").text("");
        
        var data = 
        {
    		'action': 'wpephpcompat_start_test'
    	};
        
    	jQuery.post(ajax_object.ajax_url, data, function(response) 
        {
            $("#runButton").removeClass("button-primary-disabled");
            $(".spinner").hide();
            $("#testResults").text(response);
    	});
    });

});
