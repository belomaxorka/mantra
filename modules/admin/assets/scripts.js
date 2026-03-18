// Auto-dismiss alerts after 3 seconds
(function () {
    var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 3000);
    });
})();

// Sidebar collapse toggle
(function () {
    function toggle(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('collapsed');

        var toggles = document.querySelectorAll('[data-admin-collapse="' + CSS.escape(id) + '"]');
        toggles.forEach(function (a) {
            a.classList.toggle('expanded', !el.classList.contains('collapsed'));
        });
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('[data-admin-collapse]');
        if (!a) return;
        e.preventDefault();
        toggle(a.getAttribute('data-admin-collapse'));
    });
})();

// Mobile sidebar toggle
(function () {
    var sidebar = document.getElementById('adminSidebar');
    var backdrop = document.getElementById('adminSidebarBackdrop');
    var toggle = document.getElementById('adminMenuToggle');

    if (!sidebar || !backdrop || !toggle) return;

    function openSidebar() {
        sidebar.classList.add('show');
        backdrop.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }

    // Toggle button click
    toggle.addEventListener('click', function () {
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    // Backdrop click
    backdrop.addEventListener('click', closeSidebar);

    // Close sidebar when clicking on navigation links (on mobile)
    sidebar.addEventListener('click', function (e) {
        var link = e.target.closest('a.nav-link:not(.is-parent)');
        if (link && window.innerWidth <= 991.98) {
            closeSidebar();
        }
    });

    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 991.98) {
            closeSidebar();
        }
    });
})();
