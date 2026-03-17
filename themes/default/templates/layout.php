<!DOCTYPE html>
<html lang="<?php echo isset($lang) ? $lang : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $this->escape($title) : e(MANTRA_PROJECT_INFO['name']); ?></title>
    <link href="/core/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $this->asset('css/style.css'); ?>">
    <?php
    // Hook: allow modules to add content to <head>
    $app = Application::getInstance();
    echo $app->hooks()->fire('theme.head', '');
    ?>
</head>
<body>
    <?php
    // Hook: allow modules to add content after <body>
    echo $app->hooks()->fire('theme.body.start', '');
    ?>

    <header class="bg-dark text-white">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="<?php echo base_url(); ?>"><?php echo e(MANTRA_PROJECT_INFO['name']); ?></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php
                        // Build navigation items
                        $navItems = array(
                            array(
                                'id' => 'home',
                                'title' => 'Home',
                                'url' => base_url(),
                                'order' => 0,
                            ),
                            array(
                                'id' => 'admin',
                                'title' => 'Admin',
                                'url' => base_url('/admin'),
                                'order' => 100,
                            ),
                        );

                        // Hook: allow modules to add navigation items
                        $navItems = $app->hooks()->fire('theme.navigation', $navItems);

                        if (is_array($navItems)) {
                            // Sort by order
                            usort($navItems, function($a, $b) {
                                $orderA = isset($a['order']) ? (int)$a['order'] : 100;
                                $orderB = isset($b['order']) ? (int)$b['order'] : 100;
                                return $orderA - $orderB;
                            });

                            // Render navigation items
                            foreach ($navItems as $item) {
                                if (!is_array($item) || empty($item['url']) || empty($item['title'])) {
                                    continue;
                                }

                                $active = !empty($item['active']) ? ' active' : '';
                                $url = htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');
                                $title = htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8');

                                echo '<li class="nav-item">';
                                echo '<a class="nav-link' . $active . '" href="' . $url . '">' . $title . '</a>';
                                echo '</li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <?php echo isset($content) ? $content : ''; ?>
            </div>
            <aside class="col-lg-4">
                <?php
                // Hook: allow modules to add sidebar widgets
                $sidebarWidgets = $app->hooks()->fire('theme.sidebar', array());

                if (is_array($sidebarWidgets) && !empty($sidebarWidgets)) {
                    // Sort by order
                    usort($sidebarWidgets, function($a, $b) {
                        $orderA = isset($a['order']) ? (int)$a['order'] : 100;
                        $orderB = isset($b['order']) ? (int)$b['order'] : 100;
                        return $orderA - $orderB;
                    });

                    // Render widgets
                    foreach ($sidebarWidgets as $widget) {
                        if (!is_array($widget)) {
                            continue;
                        }

                        echo '<div class="sidebar-widget mb-4">';

                        // Widget can provide direct content or reference a widget template
                        if (!empty($widget['content'])) {
                            echo $widget['content'];
                        } elseif (!empty($widget['widget'])) {
                            $params = isset($widget['params']) && is_array($widget['params']) ? $widget['params'] : array();
                            echo widget($widget['widget'], $params);
                        }

                        echo '</div>';
                    }
                }
                ?>
            </aside>
        </div>
    </main>

    <footer class="bg-light text-center py-4 mt-5">
        <div class="container">
            <?php
            // Hook: allow modules to add footer links
            $footerLinks = $app->hooks()->fire('theme.footer.links', array());

            if (is_array($footerLinks) && !empty($footerLinks)) {
                // Sort by order
                usort($footerLinks, function($a, $b) {
                    $orderA = isset($a['order']) ? (int)$a['order'] : 100;
                    $orderB = isset($b['order']) ? (int)$b['order'] : 100;
                    return $orderA - $orderB;
                });

                echo '<div class="mb-2">';
                $links = array();
                foreach ($footerLinks as $link) {
                    if (!is_array($link) || empty($link['url']) || empty($link['title'])) {
                        continue;
                    }
                    $url = htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8');
                    $title = htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8');
                    $links[] = '<a href="' . $url . '" class="text-muted text-decoration-none">' . $title . '</a>';
                }
                echo implode(' <span class="text-muted">|</span> ', $links);
                echo '</div>';
            }
            ?>
            <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> <?php echo e(MANTRA_PROJECT_INFO['name']); ?></p>
        </div>
    </footer>

    <script src="/core/assets/bootstrap/bootstrap.min.js"></script>
    <?php
    // Hook: allow modules to add scripts before </body>
    echo $app->hooks()->fire('theme.footer', '');
    ?>

    <?php
    // Hook: allow modules to add content before </body>
    echo $app->hooks()->fire('theme.body.end', '');
    ?>
</body>
</html>
