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
                    error_cb("The server returned an unknown error. Please try again.");
                }
            },
            'json');
    }

    //Submit the form when our custom radio boxes change
    $(".geodir-converter-select input")
        .on('change', function(e) {
            $(this).closest('form').submit();
        })

    //Let's fetch the next step when a form is submitted
    $(".geodir-converter-form")
        .on('submit', function(e) {
            e.preventDefault();

            var parent = $(this).closest('.geodir-converter-inner');
            var formData = $(this).serialize();

            //Hide errors
            $(this).find(".geodir-converter-errors").html('').hide();

            //Fetch the next step from the db
            GD_Converter_fetch(
                formData,
                function(str) {
                    $('.geodir-converter-errors').html(str).show()
                },
                function(str) {
                    $(parent).html(str)
                }
            )
        })
})(jQuery);