jQuery(document).ready(function($) 
{
    //Handlebars if conditional.
    Handlebars.registerHelper('if', function(conditional, options) 
    {
        if(conditional) 
        {
            return options.fn(this);
        } else 
        {
            return options.inverse(this);
        }
    }); 
    $("#developermode").change(function() 
    {
        if ($(this).is(":checked"))
        {
            $("#developerMode").show();
            $("#standardMode").hide();
        }
        else 
        {
            $("#developerMode").hide();
            $("#standardMode").show();
        }
        
    });
    
    $("#downloadReport").on("click", function()
    {
        download($("#testResults").val(), "report.txt", "text/plain");
    });
    
    $(document).on("click", ".addDetails", function()
    {
        var textarea = $(this).children().first();
        if (textarea.css("display") === "none")
        {
            textarea.css("display", "");
        }
        else 
        {
            textarea.css("display", "none");
        }
    });
    
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
        $("#standardMode").html("");
        var testVersion = $('input[name=phptestversion]:checked').val();
        
        var onlyActive = $('input[name=activeplugins]:checked').val();
        
        
        var data = 
        {
    		'action': 'wpephpcompat_start_test',
            'testVersion': testVersion,
            'onlyActive': onlyActive
    	};
        
    	jQuery.post(ajax_object.ajax_url, data, function(response) 
        {
            var compatible = 1;
            $("#runButton").removeClass("button-primary-disabled");
            $(".spinner").hide();
            $("#testResults").text(response);
            
            $("#footer").show();
            
            $("#runButton").val("Re-run");
            
            var plugins = response.split("Name: ");
            
            for (var x in plugins)
            {
                if (plugins[x] === "")
                {
                    continue;
                }
                
                var name = plugins[x].substring(0, plugins[x].indexOf("\n"));
                var log = plugins[x].substring(plugins[x].indexOf("\n"), plugins[x].length); 
                console.log(name);
                console.log(log);
                var errorsRegex = /(\d*) ERRORS/g;
                var warningRegex = /(\d*) WARNINGS?/g;
                
                var errors = 0;
                var warnings = 0;
                
                var m;
                while ((m = errorsRegex.exec(log)) !== null) {
                    if (m.index === errorsRegex.lastIndex) {
                        errorsRegex.lastIndex++;
                    }
                    if (parseInt(m[1]) > 0)
                    {
                        errors += parseInt(m[1]);
                    }
                }
                
                while ((m = warningRegex.exec(log)) !== null) {
                    if (m.index === warningRegex.lastIndex) {
                        warningRegex.lastIndex++;
                    }
                    if (parseInt(m[1]) > 0)
                    {
                        warnings += parseInt(m[1]);
                    }
                }
                
                //var match = warningRegex.match(log);
                
                //console.log(match)
                
                //alert("pause")
                var passed = 1;
                
                if (parseInt(errors) > 0)
                {
                    compatible = 0;
                    passed = 0;
                }
                
                //Use handlebars to fill our template.
                var source   = $("#result-template").html();
                var template = Handlebars.compile(source);
                var context = {plugin_name: name, warnings: warnings, errors: errors, logs: log, passed: passed, testVersion: testVersion};
                var html    = template(context);
                
                $("#standardMode").append(html);
                
            }
            if (compatible)
            {
                $("#standardMode").prepend("<h3>Your WordPress install is PHP " + testVersion + " compatible.");
            }
            else 
            {
                $("#standardMode").prepend("<h3>Your WordPress install is not PHP " + testVersion + " compatible.");
            }
            
            
    	});
    });

});
