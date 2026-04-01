<!DOCTYPE html>
<html lang="<?php echo isset($lang) ? $lang : 'en'; ?>">
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

    <div class="scroll-indicator" id="scrollIndicator"></div>

    <header class="site-header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="<?php echo base_url(); ?>"><?php echo e(config('site.name', MANTRA_PROJECT_INFO['name'])); ?></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php
                        $currentPath = strtok($_SERVER['REQUEST_URI'], '?');
                        $siteUrl = rtrim(config('site.url', ''), '/');

                        $navItems = array(
                            array('id' => 'home', 'title' => 'Home', 'url' => base_url(), 'order' => 0),
                        );

                        $navItems = $app->hooks()->fire('theme.navigation', $navItems);

                        if (is_array($navItems)) {
                            usort($navItems, function($a, $b) {
                                return (isset($a['order']) ? (int)$a['order'] : 100) - (isset($b['order']) ? (int)$b['order'] : 100);
                            });

                            foreach ($navItems as $item) {
                                if (!is_array($item) || empty($item['url']) || empty($item['title'])) continue;

                                // Auto-detect active state from current URL
                                if (!empty($item['active'])) {
                                    $isActive = true;
                                } else {
                                    $itemPath = str_replace($siteUrl, '', $item['url']);
                                    $itemPath = $itemPath === '' ? '/' : $itemPath;
                                    $isActive = ($itemPath === '/' && $currentPath === '/')
                                        || ($itemPath !== '/' && strpos($currentPath, $itemPath) === 0);
                                }

                                $active = $isActive ? ' active' : '';
                                $url = htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');
                                $title = htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8');
                                echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . $url . '">' . $title . '</a></li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <?php
    $sidebarItems = $app->hooks()->fire('theme.sidebar', array());
    $hasSidebar = is_array($sidebarItems) && !empty($sidebarItems);
    ?>

    <main>
        <div class="container page-section">
            <?php if ($hasSidebar): ?>
                <div class="row g-4">
                    <div class="col-lg-8">
                        <?php echo isset($content) ? $content : ''; ?>
                    </div>
                    <aside class="col-lg-4">
                        <?php
                        usort($sidebarItems, function($a, $b) {
                            return (isset($a['order']) ? (int)$a['order'] : 100) - (isset($b['order']) ? (int)$b['order'] : 100);
                        });
                        foreach ($sidebarItems as $item) {
                            if (!is_array($item)) continue;
                            echo '<div class="sidebar-widget mb-4">';
                            if (!empty($item['content'])) {
                                echo $item['content'];
                            } elseif (!empty($item['partial'])) {
                                $params = isset($item['params']) && is_array($item['params']) ? $item['params'] : array();
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
                        <?php echo isset($content) ? $content : ''; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <?php
            $footerLinks = $app->hooks()->fire('theme.footer.links', array());
            if (is_array($footerLinks) && !empty($footerLinks)) {
                usort($footerLinks, function($a, $b) {
                    return (isset($a['order']) ? (int)$a['order'] : 100) - (isset($b['order']) ? (int)$b['order'] : 100);
                });
                echo '<ul class="footer-links">';
                foreach ($footerLinks as $link) {
                    if (!is_array($link) || empty($link['url']) || empty($link['title'])) continue;
                    echo '<li><a href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8') . '</a></li>';
                }
                echo '</ul>';
            }
            ?>
            <p class="footer-text mb-0">&copy; <?php echo date('Y'); ?> <?php echo e(config('site.name', MANTRA_PROJECT_INFO['name'])); ?></p>
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
    </script>
    <?php echo $app->hooks()->fire('theme.footer', ''); ?>
    <?php echo $app->hooks()->fire('theme.body.end', ''); ?>
</body>
</html>
