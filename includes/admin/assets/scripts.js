//Global GD_Converter

(function($) {
    "use strict";

    //Helper function to fetch next step via ajax
    var GD_Converter_fetch = function(data, error_cb, success_cb, progress_cb) {
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
                        if (json.action == 'progress') {
                            progress_cb(json.body)
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
                var progress = $(parent).find('.geodir-converter-progress').hide();
                var formData = $(this).serialize();
                var failed = 0;
                var imported = 0;

                //Hide errors
                $(this).find(".geodir-converter-errors").html('').hide();

                //Fade the parent
                parent.css({
                    opacity: 0.4,
                })

                //Success cb
                var success_cb = function(str) {
                    $(progress).hide();
                    $(parent).html(str)
                    var newForm = $(parent).find('form')
                    GD_Converter_attach_handlers(newForm);
                    parent.css({
                        opacity: 1,
                    })
                }

                //Error cb
                var error_cb = function(str) {
                    $(progress).hide();
                    $('.geodir-converter-errors').html(str).show()
                    parent.css({
                        opacity: 1,
                    })
                }

                //Progress cb
                var progress_cb = function(obj) {

                    parent.css({
                        opacity: 1,
                    })

                    //update the failed and imported count
                    failed = failed + obj.failed
                    imported = imported + obj.imported

                    //Show the user current progress
                    $(progress).show();
                    $(progress).find('.total em').text(obj.count)
                    $(progress).find('.processed em').text(obj['progress-offset'])
                    $(progress).find('.imported em').text(imported)
                    $(progress).find('.failed em').text(failed)
                    var w = (obj['progress-offset'] / obj.count) * 100
                    $(progress).find('.gmw').css({
                        width: w + '%',
                    })

                    //Continue with the import
                    obj.nonce = GD_Converter.nonce;
                    obj.action = 'gdconverter_handle_progress';

                    GD_Converter_fetch(
                        obj,
                        error_cb,
                        success_cb,
                        progress_cb
                    )
                }

                //Fetch the next step from the db
                GD_Converter_fetch(
                    formData,
                    error_cb,
                    success_cb,
                    progress_cb
                )
            })
    }

    //Attach handlers to the initial form
    GD_Converter_attach_handlers('.geodir-converter-form1');
})(jQuery);