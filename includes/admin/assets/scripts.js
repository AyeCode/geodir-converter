//Global GD_Converter

(function($) {
    "use strict";

    //Helper function to fetch next step via ajax
    var GD_Converter_fetch = function(data, error_cb, success_cb) {
        return $.post(
                GD_Converter.ajaxurl,
                data,
                function(json) {
                    if ($.isPlainObject(json)) {

                        if (json.action == 'error') {
                            error_cb(json.body)
                        }
                        if (json.action == 'success') {
                            success_cb(json.body)
                        }
                    } else {
                        error_cb(json);
                    }
                })
            .fail(function() {
                error_cb('We could not connect to the server. Please try again.');
            });
    }

    //Helper function to attach event handlers
    var GD_Converter_attach_handlers = function(form) {

        //Submit the form when our custom radio boxes change
        $(form)
            .find(".geodir-converter-select input")
            .on('change click', function(e) {
                $(form).submit();
            })

        //Let's fetch the next step when a form is submitted
        $(form)
            .on('submit', function(e) {
                e.preventDefault();

                var parent = $(this).closest('.geodir-converter-inner');
                var formData = $(this).serialize();

                //Hide errors
                $(this).find(".geodir-converter-errors").html('').hide();

                //Fade the parent
                parent.css({
                    opacity: 0.4,
                })

                //Fetch the next step from the db
                GD_Converter_fetch(
                    formData,
                    function(str) {
                        $('.geodir-converter-errors').html(str).show()
                        parent.css({
                            opacity: 1,
                        })
                    },
                    function(str) {
                        $(parent).html(str)
                        var newForm = $(parent).find('form')
                        GD_Converter_attach_handlers(newForm);
                        parent.css({
                            opacity: 1,
                        })
                    }
                )
            })
    }

    //Attach handlers to the initial form
    GD_Converter_attach_handlers('.geodir-converter-form1');
})(jQuery);