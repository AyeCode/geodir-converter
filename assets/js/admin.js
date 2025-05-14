/**
 * GeoDir Converter Module
 *
 * @package GeoDir_Converter
 */

(function ($, GeoDir_Converter) {
    'use strict';

    /**
     * Performs AJAX request.
     *
     * @param {string} action - Action to perform.
     * @param {Function} callback - Callback function.
     * @param {Object} data - Data to send (optional).
     * @param {Object} atts - Additional parameters for $.ajax (optional).
     * @return {jqXHR} jQuery XMLHttpRequest object.
     */
    GeoDir_Converter.ajax = function (action, callback, data, atts) {
        atts = typeof atts !== 'undefined' ? atts : {};
        data = typeof data !== 'undefined' ? data : {};

        const nonce = GeoDir_Converter.nonces.hasOwnProperty(action) ? GeoDir_Converter.nonces[action] : '';
        if (data instanceof FormData) {
            data.append('action', action);
            data.append('geodir_converter_nonce', nonce);

            atts.processData = false;
            atts.contentType = false;
        } else {
            data.action = action;
            data.geodir_converter_nonce = nonce;
        }

        atts = $.extend(atts, {
            url: GeoDir_Converter.ajaxUrl,
            dataType: 'json',
            data: data,
            success: function (response) {
                const success = true === response.success;
                const responseData = response.data || {};

                callback(success, responseData);
            },
        });

        return $.ajax(atts);
    }

    /**
     * Control Button base object.
     *
     * @type {Object}
     */
    GeoDir_Converter.ControlButton = {
        inSuspended: false,
        wasDisabled: false,
        defaultText: '',
        actionText: '',
        ajaxAction: '',
        converter: null,

        /**
         * Initializes the button.
         *
         * @param {jQuery} el - Button element.
         * @param {Object} args - Button arguments.
         * @return {Object} The button instance.
         */
        init: function (el, args) {
            this.element = el;
            this.defaultText = args.defaultText;
            this.actionText = args.actionText;
            this.ajaxAction = args.ajaxAction;
            this.converter = args.converter;

            this.element.on('click', this.click.bind(this));

            return this;
        },

        /**
         * Handles button click.
         *
         * @return {boolean} False if suspended, otherwise calls doAction.
         */
        click: function () {
            if (this.inSuspended) {
                return false;
            }
            this.doAction();
        },

        /**
         * Performs the button's action.
         */
        doAction: function () { },

        /**
         * Activates the button.
         */
        activate: function () {
            this.inSuspended = true;
            this.element.prop('disabled', true);
            this.element.text(this.actionText);
        },

        /**
         * Enables the button.
         */
        enable: function () {
            this.inSuspended = false;
            this.element.prop('disabled', false);
            this.element.text(this.defaultText);
        },

        /**
         * Disables the button.
         */
        disable: function () {
            this.inSuspended = false;
            this.element.prop('disabled', true);
            this.element.text(this.defaultText);
        },

        /**
         * Suspends the button.
         */
        suspend: function () {
            this.inSuspended = true;
            this.wasDisabled = !!this.element.prop('disabled');
            this.element.prop('disabled', true);
        },

        /**
         * Restores the button.
         */
        restore: function () {
            this.inSuspended = false;
            this.element.prop('disabled', this.wasDisabled);
        }
    };

    /**
     * Start Button Control.
     *
     * @type {Object}
     */
    GeoDir_Converter.ImportButton = $.extend({}, GeoDir_Converter.ControlButton, {
        doAction: function () {
            const importerId = this.converter.importerId;
            const form = this.converter.settings.find('form');
            const formData = form.serializeObject();
            const errorHandler = this.converter.errorHandler;
            const self = this;

            if (!importerId) {
                return;
            }

            formData.test_mode = form.find('#test_mode').is(':checked') ? 'yes' : 'no';

            this.activate();
            errorHandler.hide();

            GeoDir_Converter.ajax(self.ajaxAction, function (success, data) {
                if (!success) {
                    self.enable();
                    self.converter.stop();
                    errorHandler.show(data.message);
                } else {
                    self.converter.start();
                }
            }, {
                importerId: importerId,
                settings: formData,
            }, { method: 'POST' });
        }
    });

    /**
     * Configure Button Control.
     *
     * @type {Object}
     */
    GeoDir_Converter.ConfigureButton = $.extend({}, GeoDir_Converter.ControlButton, {
        activate: function () {
            this.element
                .addClass('btn-translucent-success')
                .removeClass('btn-outline-primary')
                .text(this.actionText);
        },
        enable: function () {
            this.element
                .addClass('btn-outline-primary')
                .removeClass('btn-translucent-success')
                .text(this.defaultText);
        },
        doAction: function () {
            const wrapper = $('.geodir-converter-wrapper');
            const converter = this.converter.element;
            const settings = this.converter.settings;

            wrapper.find('.card-header h6').text(GeoDir_Converter.i18n.importSource);

            wrapper.find('.geodir-converter-importer')
                .not(converter)
                .addClass('d-none');

            $('.geodir-converter-settings')
                .not(settings)
                .addClass('d-none');

            this.element.addClass('d-none');

            this.converter.backButton.element.removeClass('d-none');
            converter.addClass('border-bottom-0');
            settings.removeClass('d-none');
        }
    });

    /**
     * Cancel Button Control.
     *
     * @type {Object}
     */
    GeoDir_Converter.BackButton = $.extend({}, GeoDir_Converter.ControlButton, {
        doAction: function () {
            const wrapper = $('.geodir-converter-wrapper');
            const converter = this.converter.element;
            const settings = this.converter.settings;

            this.element.addClass('d-none');
            this.converter.configureButton.element.removeClass('d-none');
            converter.removeClass('border-bottom-0');

            settings.addClass("d-none");

            wrapper.find('.card-header h6').text(GeoDir_Converter.i18n.selectImport);
            wrapper.find('.geodir-converter-importer').removeClass('d-none');

            if (settings.find('form').length) {
                settings.find('form')[0].reset();
                this.converter.errorHandler.clear();
            }
        }
    });

    /**
    * Abort Button Control.
    *
    * @type {Object}
    */
    GeoDir_Converter.AbortButton = $.extend({}, GeoDir_Converter.ControlButton, {
        doAction: function () {
            this.activate();
            this.converter.stop();
            const importerId = this.converter.importerId;
            const self = this;

            GeoDir_Converter.ajax(self.ajaxAction, function (success, data) {
                self.converter.start();
                if (!success) {
                    self.enable();
                }
            }, { importerId }, { method: 'POST' });
        }
    });

    /**
     * Logs Handler.
     *
     * @type {Object}
     */
    GeoDir_Converter.LogsHandler = $.extend({}, {
        shown: 0,

        /**
         * Initializes the logs handler.
         *
         * @param {jQuery} el - Logs container element.
         * @return {Object} The logs handler instance.
         */
        init: function (el) {
            this.element = el;
            return this;
        },

        /**
         * Inserts logs into the container.
         *
         * @param {string} logs - Logs to insert.
         */
        insertLogs: function (logs) {
            this.element.append(logs);
        },

        /**
         * Sets the number of logs shown.
         *
         * @param {number} count - Number of logs shown.
         */
        setShown: function (count) {
            this.shown = count;
        },

        /**
         * Clears the logs.
         */
        clear: function () {
            this.shown = 0;
            this.element.html('');
        },

    });

    /**
    * Error Handler.
    *
    * @type {Object}
    */
    GeoDir_Converter.ErrorHandler = {
        /**
         * Initializes the error handler.
         * @param {jQuery} el - The element where errors will be displayed.
         * @returns {Object} - The error handler instance.
         */
        init: function (el) {
            this.element = el;
            return this;
        },

        /**
         * Displays an error message.
         * @param {string} message - The error message to display.
         */
        show: function (message) {
            this.element.html(message).removeClass('d-none');
        },

        /**
         * Hides the error message.
         */
        hide: function () {
            this.element.html('').addClass('d-none');
        },

        /**
         * Clears the error message and hides the error container.
         */
        clear: function () {
            this.hide();
        },

        /**
         * Checks if the error container is currently visible.
         * @returns {boolean} - True if the error container is visible, otherwise false.
         */
        isVisible: function () {
            return !this.element.hasClass('d-none');
        }
    };

    /**
     * Progress Bar.
     *
     * @type {Object}
     */
    GeoDir_Converter.ProgressBar = $.extend({}, {
        barEl: null,

        /**
         * Initializes the progress bar.
         *
         * @param {jQuery} el - Progress bar container element.
         * @return {Object} The progress bar instance.
         */
        init: function (el) {
            this.element = el;
            this.barEl = this.element.find('.progress-bar');
            return this;
        },

        /**
         * Updates the progress bar.
         *
         * @param {number} newProgress - New progress percentage.
         */
        updateProgress: function (newProgress) {
            this.element.removeClass('d-none');
            this.barEl.css('width', newProgress + '%').text(newProgress + '%');
        }
    });

    /**
     * Converter main object.
     *
     * @type {Object}
     */
    GeoDir_Converter.Converter = {
        /**
         * Interval for regular progress checks (in milliseconds).
         * @type {number}
         */
        tickInterval: 2000,

        /**
         * Interval for the initial progress check (in milliseconds).
         * provides quick feedback when the import starts.
         * @type {number}
         */
        shortTickInterval: 400,
        retriesCount: 1,
        retriesLeft: 0,
        inProgress: false,
        updateTimeout: null,
        preventUpdates: false,
        importerId: null,

        /**
         * Initializes the converter.
         *
         * @param {jQuery} el - Converter container element.
         * @param {Object} args - Converter arguments.
         * @return {Object} The converter instance.
         */
        init: function (el, args) {
            this.element = el;
            this.inProgress = args.inProgress;
            this.resetRetries();

            this.importerId = this.element.data('importer');
            this.settings = this.element.find('.geodir-converter-settings');

            let progressBar = $.extend({}, GeoDir_Converter.ProgressBar);
            this.progressBar = progressBar.init(this.element.find('.geodir-converter-progress'));

            let logsHandler = $.extend({}, GeoDir_Converter.LogsHandler);
            this.logsHandler = logsHandler.init(this.element.find('.geodir-converter-logs'));

            let configureButton = $.extend({}, GeoDir_Converter.ConfigureButton);
            this.configureButton = configureButton.init(this.element.find('.geodir-converter-configure'), {
                defaultText: GeoDir_Converter.i18n.runConverter,
                actionText: GeoDir_Converter.i18n.importing,
                converter: this
            });

            let backButton = $.extend({}, GeoDir_Converter.BackButton);
            this.backButton = backButton.init(this.element.find('.geodir-converter-back'), {
                converter: this
            });

            let importButton = $.extend({}, GeoDir_Converter.ImportButton);
            this.importButton = importButton.init(this.element.find('.geodir-converter-import'), {
                defaultText: GeoDir_Converter.i18n.import,
                actionText: GeoDir_Converter.i18n.importing,
                ajaxAction: GeoDir_Converter.actions.import,
                converter: this
            });

            let abortButton = $.extend({}, GeoDir_Converter.AbortButton);
            this.abortButton = abortButton.init(this.element.find('.geodir-converter-abort'), {
                defaultText: GeoDir_Converter.i18n.abort,
                actionText: GeoDir_Converter.i18n.aborting,
                ajaxAction: GeoDir_Converter.actions.abort,
                converter: this
            });

            let errorHandler = $.extend({}, GeoDir_Converter.ErrorHandler);
            this.errorHandler = errorHandler.init(this.element.find('.geodir-converter-error'), {
                converter: this
            });

            if (this.inProgress) {
                this.start();
            }

            return this;
        },

        /**
         * Starts the converter.
         */
        start: function () {
            this.preventUpdates = false;
            this.logsHandler.clear();
            this.updateTimeout = setTimeout(this.tick.bind(this), this.shortTickInterval);
        },

        /**
         * Stops the converter.
         */
        stop: function () {
            clearTimeout(this.updateTimeout);
            this.preventUpdates = true;
        },

        /**
         * Resets the retry count.
         */
        resetRetries: function () {
            this.retriesLeft = this.retriesCount;
        },

        /**
         * Performs a tick of the converter.
         */
        tick: function () {
            const self = this;

            GeoDir_Converter.ajax(GeoDir_Converter.actions.progress, function (success, data) {
                if (self.preventUpdates) {
                    return;
                }

                if (!success) {
                    if (self.retriesLeft > 0) {
                        self.retriesLeft--;
                        self.updateTimeout = setTimeout(self.tick.bind(self), self.tickInterval);
                    } else {
                        self.abortButton.disable();
                    }
                    return;
                }

                self.resetRetries();

                data.inProgress ? self.markInProgress() : self.markStopped();
                data.inProgress ? self.configureButton.activate() : self.configureButton.enable();

                self.progressBar.updateProgress(data.progress);
                self.logsHandler.setShown(data.logsShown);
                self.logsHandler.insertLogs(data.logs);

                if (self.inProgress) {
                    self.updateTimeout = setTimeout(self.tick.bind(self), self.tickInterval);
                } else {
                    self.abortButton.disable();
                    self.importButton.enable();
                }

            }, { logsShown: self.logsHandler.shown, importerId: this.importerId });
        },

        /**
         * Marks the converter as in progress.
         */
        markInProgress: function () {
            this.inProgress = true;
            this.importButton.activate();
            this.abortButton.enable();
        },

        /**
         * Marks the converter as stopped.
         */
        markStopped: function () {
            this.inProgress = false;
            this.importButton.enable();
            this.abortButton.disable();
        }
    };

    $(function () {
        const importers = $('.geodir-converter-importer');
        importers.each(function () {
            let converter = $.extend({}, GeoDir_Converter.Converter);
            converter.init($(this), {
                inProgress: Boolean($(this).data('progress'))
            });
        });
    });

    $.fn.serializeObject = function () {
        var o = {};
        var a = this.serializeArray();
        $.each(a, function () {
            var name = this.name.replace(/\[\]$/, '');

            if (o[name]) {
                if (!Array.isArray(o[name])) {
                    o[name] = [o[name]];
                }
                o[name].push(this.value || '');
            } else {
                o[name] = this.value || '';
            }
        });
        return o;
    };
}(jQuery, GeoDir_Converter));