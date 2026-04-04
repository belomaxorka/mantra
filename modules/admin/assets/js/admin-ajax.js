/**
 * Mantra AJAX Helper
 *
 * Usage:
 *   Mantra.ajax('pages.autosave', { id: 123, content: '...' })
 *     .done(function(data) { adminToast('Saved', 'success'); })
 *     .fail(function(error) { console.error(error); });
 *
 *   // File upload
 *   var fd = new FormData();
 *   fd.append('file', fileInput.files[0]);
 *   Mantra.ajax('uploads.upload', fd);
 *
 *   // Public (no-auth) action
 *   Mantra.ajax('search.query', { q: 'test' }, { admin: false, method: 'GET' });
 */
(function(window, $) {
    'use strict';

    var Mantra = window.Mantra || {};

    Mantra.csrfToken = function() {
        var el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    };

    Mantra.baseUrl = function() {
        var el = document.querySelector('meta[name="base-url"]');
        return el ? el.getAttribute('content') : '';
    };

    /**
     * Make an AJAX call to a registered Mantra action.
     *
     * @param {string}           action  Action name (e.g. 'uploads.upload')
     * @param {object|FormData}  data    Payload
     * @param {object}           options Optional overrides:
     *   method:  'POST' (default) | 'GET'
     *   admin:   true  (default) — /admin/ajax; false — /ajax
     *   toast:   true  (default) — show error toasts via adminToast()
     * @returns {jQuery.Deferred} resolves with response data, rejects with error string
     */
    Mantra.ajax = function(action, data, options) {
        options = $.extend({ method: 'POST', admin: true, toast: true }, options);
        var deferred = $.Deferred();

        var url = Mantra.baseUrl()
            + (options.admin ? '/admin/ajax' : '/ajax')
            + '?action=' + encodeURIComponent(action);

        var ajaxOpts = {
            url: url,
            type: options.method,
            headers: { 'X-CSRF-Token': Mantra.csrfToken() },
            dataType: 'json'
        };

        // Upload progress support
        if (typeof options.progress === 'function') {
            ajaxOpts.xhr = function() {
                var xhr = new XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) options.progress(e.loaded, e.total);
                });
                return xhr;
            };
        }

        if (data instanceof FormData) {
            ajaxOpts.data = data;
            ajaxOpts.processData = false;
            ajaxOpts.contentType = false;
        } else if (data && options.method === 'POST') {
            ajaxOpts.data = JSON.stringify(data);
            ajaxOpts.contentType = 'application/json';
        } else if (data && options.method === 'GET') {
            ajaxOpts.data = data;
        }

        var jqXhr = $.ajax(ajaxOpts)
            .done(function(response) {
                if (response && response.ok) {
                    deferred.resolve(response.data);
                } else {
                    var msg = (response && response.error) || 'Unknown error';
                    if (options.toast && window.adminToast) {
                        window.adminToast(msg, 'danger');
                    }
                    deferred.reject(msg);
                }
            })
            .fail(function(xhr) {
                var msg = 'Network error';
                try {
                    var body = JSON.parse(xhr.responseText);
                    msg = body.error || msg;
                } catch (e) {}

                if (xhr.status === 401) {
                    msg = 'Session expired. Please reload the page.';
                }

                if (options.toast && window.adminToast) {
                    window.adminToast(msg, 'danger');
                }
                deferred.reject(msg);
            });

        var promise = deferred.promise();
        promise.abort = function() { jqXhr.abort(); return this; };
        return promise;
    };

    window.Mantra = Mantra;
})(window, jQuery);
