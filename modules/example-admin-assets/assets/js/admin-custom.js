/**
 * Example Admin Custom Scripts
 * Loaded via admin.footer hook
 */

(function() {
    'use strict';

    console.log('Admin custom scripts initialized');

    // Example: Add custom functionality to admin panel
    function initCustomFeatures() {
        // Example 1: Add tooltips to all elements with data-example-tooltip
        document.querySelectorAll('[data-example-tooltip]').forEach(function(el) {
            el.title = el.getAttribute('data-example-tooltip');
        });

        // Example 2: Add confirmation to delete buttons
        document.querySelectorAll('.btn-danger[data-example-confirm]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                var message = btn.getAttribute('data-example-confirm') || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });

        // Example 3: Auto-save form data to localStorage
        var forms = document.querySelectorAll('form[data-example-autosave]');
        forms.forEach(function(form) {
            var formId = form.getAttribute('data-example-autosave');

            // Load saved data
            var savedData = localStorage.getItem('form_' + formId);
            if (savedData) {
                try {
                    var data = JSON.parse(savedData);
                    Object.keys(data).forEach(function(name) {
                        var input = form.querySelector('[name="' + name + '"]');
                        if (input && input.type !== 'password') {
                            input.value = data[name];
                        }
                    });
                } catch (e) {
                    console.error('Failed to restore form data:', e);
                }
            }

            // Save on input
            form.addEventListener('input', function() {
                var data = {};
                var inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(function(input) {
                    if (input.name && input.type !== 'password') {
                        data[input.name] = input.value;
                    }
                });
                localStorage.setItem('form_' + formId, JSON.stringify(data));
            });

            // Clear on submit
            form.addEventListener('submit', function() {
                localStorage.removeItem('form_' + formId);
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCustomFeatures);
    } else {
        initCustomFeatures();
    }

    // Example: Global utility function
    window.ExampleAdminUtils = {
        showNotification: function(message, type) {
            type = type || 'info';
            var alert = document.createElement('div');
            alert.className = 'alert alert-' + type + ' alert-dismissible fade show';
            alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';

            var container = document.querySelector('main.flex-fill');
            if (container) {
                container.insertBefore(alert, container.firstChild);
                setTimeout(function() {
                    if (alert.parentNode) {
                        var bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 3000);
            }
        }
    };

})();
