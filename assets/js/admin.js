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
            const self = this;
            const importerId = this.converter.importerId;
            const errorHandler = this.converter.errorHandler;
            const form = this.converter.settings.find('form');
            const files = this.converter.files;
            const settings = form.serializeObject();
            const test_mode = form.find('#test_mode').is(':checked') ? 'yes' : 'no';
            const formData = new FormData();

            if (!importerId) {
                return;
            }

            formData.append('test_mode', test_mode);
            formData.append('importerId', importerId);
            formData.append('settings', JSON.stringify(settings));

            // Handle files
            if (files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    formData.append('files[]', files[i]); 
                }
            }

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
            }, formData, {
                method: 'POST',
                contentType: false,
                processData: false,
            });
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
            this.element.scrollTop(this.element[0].scrollHeight);
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
     * CSV Upload Handler.
     *
     * Handles drag & drop, file selection, and AJAX upload with progress bar.
     */
    GeoDir_Converter.DropZone = $.extend({}, {
        dropzone: null,
        input: null,
        uploads: null,

        /**
         * Initializes the drop zone.
         *
         */
        init: function (el, args) {
            this.element = el;
            this.converter = args.converter;
            this.dropzone = this.element.find('.geodir-converter-drop-zone');
            this.btn = this.element.find('.geodir-converter-files-btn');
            this.input = this.element.find('.geodir-converter-files-input');
            this.uploads = this.element.find('.geodir-converter-uploads');

            const self = this;

            // Disable step 2 inputs.
            this.disableStep2Inputs(true);

            // Button click triggers file input.
            this.btn.on('click', function () {
                self.input.trigger('click');
            });

            // Handle dragover
            this.dropzone.on('dragover dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.dropzone.addClass('dragover');
            });

            // Handle dragleave/drop
            this.dropzone.on('dragleave dragend drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.dropzone.removeClass('dragover');
            });

            // Drop files
            this.dropzone.on('drop', function (e) {
                self.handleFiles(e.originalEvent.dataTransfer.files);
            });

            // File input change
            this.input.on('change', function (e) {
                e.preventDefault();
                self.handleFiles(this.files);
            });

            return this;
        },

        /**
         * Handles CSV files dropped or selected.
         *
         * @param {FileList} files
         */
        handleFiles: function (files) {
            const self = this;

            Array.from(files).forEach(function (file) {
                if (file.name.toLowerCase().endsWith('.csv')) {
                    self.uploadFile(file);
                } else {
                    aui_toast("geodir_converter_error", "error", `${file.name} is not a CSV file.`);
                }
            });
        },

        /**
         * Uploads a single file.
         *
         * @param {File} file
         */
        uploadFile: function (file) {
            const self = this;
            const fileId = 'upload-' + Date.now();
            const item = this.renderUploadItem(fileId, file.name);
            const progressBar = $.extend({}, GeoDir_Converter.ProgressBar);
            const progress = progressBar.init(item.find('.progress'));
            const status = item.find('.geodir-converter-progress-status');
            const icon = item.find('.geodir-converter-progress-icon');
            const $moduleTypes = this.element.find('[name="edirectory_modules[]"]');

            const formData = new FormData();
            formData.append('file', file);
            formData.append('importerId', 'edirectory');

            GeoDir_Converter.ajax(GeoDir_Converter.actions.upload, function (success, data) {
                progress.barEl.removeClass('progress-bar-animated');
                icon.removeClass('fa-sync');

                if (success) {
                    progress.barEl.addClass('bg-success');
                    icon.addClass('fa-check text-success');
                    status.text(data.message);

                    if (!self.converter.files.some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified)) {
                        self.converter.files.push(file);
                    }

                    const selected = $moduleTypes.map(function () {
                        return $(this).val();
                    }).get();

                    if (data.module_type && !selected.includes(data.module_type)) {
                        selected.push(data.module_type);
                        $moduleTypes.remove();

                        const hiddenInputs = selected.map(val =>
                            `<input type="hidden" name="edirectory_modules[]" value="${val}">`
                        ).join('');

                        self.element.append(hiddenInputs);
                    }

                    // Enable step 2 inputs.
                    self.disableStep2Inputs(false);
                } else {
                    progress.barEl.addClass('bg-danger');
                    icon.addClass('fa-triangle-exclamation text-danger');
                    status.text(`Upload failed: ${data.message}`);
                    self.disableStep2Inputs(true);
                }
            }, formData, {
                method: 'POST',
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function (evt) {
                        if (evt.lengthComputable) {
                            const percent = Math.round((evt.loaded / evt.total) * 100);
                            progress.updateProgress(percent);
                        }
                    }, false);
                    return xhr;
                },
                error: function () {
                    progress.barEl.removeClass('progress-bar-animated').addClass('bg-danger');
                    icon.removeClass('fa-sync').addClass('fa-triangle-exclamation text-danger');
                    status.text('Server error during upload.');
                    self.disableStep2Inputs(true);
                }
            });
        },

        /**
         * Disables/enables step 2 inputs.
         *
         * @param {boolean} disabled
         */
        disableStep2Inputs: function (disabled) {
            this.element.find('.geodir-converter-configure-wrapper').find('input, select, textarea, button').not('[name="edirectory_modules[]"]').prop('disabled', disabled);
        },

        /**
         * Renders an upload item.
         * 
         * @param {string} id
         * @param {string} name
         */
        renderUploadItem: function (id, name) {
            const item = $(`
                <div class="upload-item my-2" data-id="${id}">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-truncate">${name}</span>
                        <i class="fas fa-solid fa-sync text-muted ms-2 geodir-converter-progress-icon" aria-hidden="true"></i>
                    </div>
                    <div class="progress my-1 d-none" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                    </div>
                    <div class="geodir-converter-progress-status small text-muted mt-1">${GeoDir_Converter.i18n.uploading}</div>
                </div>
            `);

            this.uploads.append(item);

            return this.uploads.find(`[data-id="${id}"]`);
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
        files: [],

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

            let dropZone = $.extend({}, GeoDir_Converter.DropZone);
            this.dropZone = dropZone.init(this.element.find('.geodir-converter-connect-wrapper'), {
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