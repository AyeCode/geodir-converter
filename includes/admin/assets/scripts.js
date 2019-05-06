//Global GD_Converter

(function($) {
    "use strict";

    //Helper function to fetch next step via ajax
    var GD_Converter_fetch = function(data, error_cb, success_cb, custom_cb) {
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
                        if (json.action == 'custom') {
                            custom_cb(json.body)
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

                //Hide progress bar
                parent.find('.geodir-converter-progress').hide()

                //Display the loader
                parent.addClass('geodir-converter-loading')

                //Success cb
                var success_cb = function(str) {
                    parent.find('.geodir-converter-progress').hide()
                    $(parent).find('form').replaceWith(str)
                    var newForm = $(parent).find('form')
                    GD_Converter_attach_handlers(newForm);
                    //Hide the loader
                    parent.removeClass('geodir-converter-loading')
                }

                //Error cb
                var error_cb = function(str) {
                    parent.find('.geodir-converter-progress').hide()
                    $('.geodir-converter-errors').html(str).show()
                    parent.removeClass('geodir-converter-loading')
                }

                //Custom action cb
                var custom_cb = function(obj) {

                    $(parent).removeClass('geodir-converter-loading')

                    if (true == obj.hasprogress) {
                        var w = (obj.offset / obj.count) * 100
                        parent
                            .find('.geodir-converter-progress')
                            .show()
                            .find('.gmw')
                            .css({
                                width: w + '%',
                            })
                    } else {
                        parent
                            .find('.geodir-converter-progress')
                            .hide()
                    }

                    $(parent).find('form').replaceWith(obj.form)
                    var _form = $(parent).find('form')
                    $(_form).on('submit', function(e) {
                        e.preventDefault();

                        var _formData = $(this).serialize();

                        GD_Converter_fetch(
                            _formData,
                            error_cb,
                            success_cb,
                            custom_cb
                        )
                    })
                    $(_form).submit()

                }

                //Fetch the next step from the db
                GD_Converter_fetch(
                    formData,
                    error_cb,
                    success_cb,
                    custom_cb
                )
            })
    }

    //Attach handlers to the initial form
    GD_Converter_attach_handlers('.geodir-converter-form1');
})(jQuery);