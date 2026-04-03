/**
 * Admin Toast Notification System
 *
 * Provides window.adminToast(message, type, options) for programmatic toasts
 * and auto-reads server-side flash messages from #adminFlashData on page load.
 */
(function () {
  'use strict';

  var container = document.getElementById('adminToastContainer');
  if (!container) return;

  var icons = {
    success: 'bi-check-circle-fill',
    danger: 'bi-exclamation-triangle-fill',
    warning: 'bi-exclamation-triangle-fill',
    info: 'bi-info-circle-fill'
  };

  /**
   * Show a toast notification.
   *
   * @param {string} message  Text to display (will be escaped)
   * @param {string} [type]   success | danger | warning | info (default: info)
   * @param {object} [options] { delay: ms } (default: 5000)
   */
  window.adminToast = function (message, type, options) {
    type = type || 'info';
    options = options || {};

    var icon = icons[type] || icons.info;
    var delay = options.delay || 5000;

    var toast = document.createElement('div');
    toast.className = 'toast align-items-center border-0 toast-' + type;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    var inner = document.createElement('div');
    inner.className = 'd-flex';

    var body = document.createElement('div');
    body.className = 'toast-body';

    var iconEl = document.createElement('i');
    iconEl.className = 'bi ' + icon + ' me-2';

    var text = document.createTextNode(message);

    body.appendChild(iconEl);
    body.appendChild(text);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-close me-2 m-auto';
    btn.setAttribute('data-bs-dismiss', 'toast');
    btn.setAttribute('aria-label', 'Close');

    inner.appendChild(body);
    inner.appendChild(btn);
    toast.appendChild(inner);
    container.appendChild(toast);

    var bsToast = new bootstrap.Toast(toast, { delay: delay });
    bsToast.show();

    toast.addEventListener('hidden.bs.toast', function () {
      toast.remove();
    });
  };

  // Auto-show flash messages from server
  var flashEl = document.getElementById('adminFlashData');
  if (flashEl) {
    try {
      var messages = JSON.parse(flashEl.textContent);
      for (var i = 0; i < messages.length; i++) {
        adminToast(messages[i].message, messages[i].type);
      }
    } catch (e) {
      // Silently ignore malformed flash data
    }
  }
})();
