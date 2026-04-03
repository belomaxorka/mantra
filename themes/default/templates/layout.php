<!DOCTYPE html>
<html lang="<?php echo $lang ?? 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $this->escape($title) : e(MANTRA_PROJECT_INFO['name']); ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%236366f1'/><text x='16' y='23' font-size='20' font-weight='700' fill='white' text-anchor='middle' font-family='sans-serif'>M</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $this->asset('css/style.css'); ?>">
    <?php
    $app = Application::getInstance();
    echo $app->hooks()->fire('theme.head', '');
    ?>
</head>
<body>
    <?php echo $app->hooks()->fire('theme.body.start', ''); ?>

    <?php
    // Build nav items once, render in desktop nav + mobile drawer
    $currentPath = strtok($_SERVER['REQUEST_URI'], '?');
    $siteUrl = config('site.url', '');
    $navItems = [
        ['id' => 'home', 'title' => 'Home', 'url' => base_url(), 'order' => 0],
    ];
    $navItems = $app->hooks()->fire('theme.navigation', $navItems);
    $navHtml = '';
    if (is_array($navItems)) {
        usort($navItems, fn($a, $b) => (isset($a['order']) ? (int)$a['order'] : 100) - (isset($b['order']) ? (int)$b['order'] : 100));
        foreach ($navItems as $item) {
            if (!is_array($item) || empty($item['url']) || empty($item['title'])) continue;
            if (!empty($item['active'])) {
                $isActive = true;
            } else {
                $itemPath = str_replace($siteUrl, '', $item['url']);
                $itemPath = $itemPath === '' ? '/' : $itemPath;
                $isActive = ($itemPath === '/' && $currentPath === '/')
                    || ($itemPath !== '/' && str_starts_with($currentPath, $itemPath)  );
            }
            $active = $isActive ? ' active' : '';
            $url = htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8');
            $navHtml .= '<li class="nav-item"><a class="nav-link' . $active . '" href="' . $url . '">' . $title . '</a></li>';
        }
    }
    ?>

    <div class="scroll-indicator" id="scrollIndicator"></div>
    <div class="nav-backdrop" id="navBackdrop"></div>
    <nav class="nav-drawer" id="navDrawer">
        <div class="nav-drawer-header">
            <span class="nav-drawer-brand"><?php echo e(config('site.name', MANTRA_PROJECT_INFO['name'])); ?></span>
            <button class="nav-drawer-close" id="navDrawerClose" type="button" aria-label="Close menu">&times;</button>
        </div>
        <ul class="navbar-nav"><?php echo $navHtml; ?></ul>
    </nav>

    <header class="site-header">
        <nav class="navbar">
            <div class="container">
                <a class="navbar-brand" href="<?php echo base_url(); ?>"><?php echo e(config('site.name', MANTRA_PROJECT_INFO['name'])); ?></a>
                <button class="nav-toggle" id="navToggle" type="button" aria-label="Toggle navigation">
                    <span class="nav-toggle-icon"></span>
                </button>
                <ul class="navbar-nav desktop-nav"><?php echo $navHtml; ?></ul>
            </div>
        </nav>
    </header>

    <?php
    $sidebarItems = $app->hooks()->fire('theme.sidebar', []);
    $hasSidebar = is_array($sidebarItems) && !empty($sidebarItems);
    ?>

    <main>
        <div class="container page-section">
            <?php if ($hasSidebar): ?>
                <div class="row g-4">
                    <div class="col-lg-8">
                        <?php echo $content ?? ''; ?>
                    </div>
                    <aside class="col-lg-4">
                        <?php
                        usort($sidebarItems, fn($a, $b) => (isset($a['order']) ? (int)$a['order'] : 100) - (isset($b['order']) ? (int)$b['order'] : 100));
                        foreach ($sidebarItems as $item) {
                            if (!is_array($item)) continue;
                            echo '<div class="sidebar-widget mb-4">';
                            if (!empty($item['content'])) {
                                echo $item['content'];
                            } elseif (!empty($item['partial'])) {
                                $params = isset($item['params']) && is_array($item['params']) ? $item['params'] : [];
                                echo partial($item['partial'], $params);
                            }
                            echo '</div>';
                        }
                        ?>
                    </aside>
                </div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php echo $content ?? ''; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <?php
            $footerLinks = $app->hooks()->fire('theme.footer.links', []);
            if (is_array($footerLinks) && !empty($footerLinks)) {
                usort($footerLinks, fn($a, $b) => (isset($a['order']) ? (int)$a['order'] : 100) - (isset($b['order']) ? (int)$b['order'] : 100));
                echo '<ul class="footer-links">';
                foreach ($footerLinks as $link) {
                    if (!is_array($link) || empty($link['url']) || empty($link['title'])) continue;
                    echo '<li><a href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8') . '</a></li>';
                }
                echo '</ul>';
            }
            ?>
            <p class="footer-text mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo e(config('site.name', MANTRA_PROJECT_INFO['name'])); ?>
                <?php if ($app->auth()->check()): ?>
                    <span class="footer-sep">&middot;</span>
                    <a href="<?php echo base_url('/admin'); ?>" class="footer-admin-link">Admin</a>
                <?php endif; ?>
            </p>
        </div>
    </footer>

    <button class="back-to-top" id="backToTop" aria-label="Back to top">&uarr;</button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        var indicator = document.getElementById('scrollIndicator');
        var btn = document.getElementById('backToTop');
        window.addEventListener('scroll', function() {
            var scrollTop = window.scrollY;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            if (docHeight > 0) {
                indicator.style.width = (scrollTop / docHeight * 100) + '%';
            }
            btn.classList.toggle('visible', scrollTop > 400);
        }, {passive: true});
        btn.addEventListener('click', function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    })();
    (function() {
        var drawer = document.getElementById('navDrawer');
        var backdrop = document.getElementById('navBackdrop');
        var toggle = document.getElementById('navToggle');
        var close = document.getElementById('navDrawerClose');
        if (!drawer || !backdrop || !toggle) return;

        function open() {
            drawer.classList.add('open');
            backdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function shut() {
            drawer.classList.remove('open');
            backdrop.classList.remove('open');
            document.body.style.overflow = '';
        }

        toggle.addEventListener('click', function() {
            drawer.classList.contains('open') ? shut() : open();
        });
        backdrop.addEventListener('click', shut);
        if (close) close.addEventListener('click', shut);
        drawer.addEventListener('click', function(e) {
            if (e.target.closest('a.nav-link') && window.innerWidth <= 991.98) shut();
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991.98) shut();
        });
    })();
    </script>
    <?php echo $app->hooks()->fire('theme.footer', ''); ?>
    <?php echo $app->hooks()->fire('theme.body.end', ''); ?>
</body>
</html>
