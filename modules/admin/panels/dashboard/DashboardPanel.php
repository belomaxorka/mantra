<?php declare(strict_types=1);

namespace Admin;

class DashboardPanel extends AdminPanel {

    public function id() {
        return 'dashboard';
    }

    public function registerRoutes($admin): void {
        $admin->adminRoute('GET', '', [$this, 'dashboard']);
    }

    public function dashboard() {
        $db = $this->db();

        // Stats
        $posts = $db->query('posts');
        $pages = $db->query('pages');
        $users = $db->query('users');

        $publishedPosts = array_filter($posts, fn ($p) => isset($p['status']) && $p['status'] === 'published');
        $publishedPages = array_filter($pages, fn ($p) => isset($p['status']) && $p['status'] === 'published');

        $stats = [
            [
                'title' => t('admin-dashboard.stats.posts'),
                'value' => count($posts),
                'sub' => t('admin-dashboard.stats.published', ['count' => count($publishedPosts)]),
                'icon' => 'bi-file-earmark-text',
                'color' => 'primary',
                'url' => base_url('/admin/posts'),
            ],
            [
                'title' => t('admin-dashboard.stats.pages'),
                'value' => count($pages),
                'sub' => t('admin-dashboard.stats.published', ['count' => count($publishedPages)]),
                'icon' => 'bi-file-earmark-richtext',
                'color' => 'success',
                'url' => base_url('/admin/pages'),
            ],
            [
                'title' => t('admin-dashboard.stats.users'),
                'value' => count($users),
                'icon' => 'bi-people',
                'color' => 'warning',
                'url' => base_url('/admin/users'),
            ],
        ];

        // Recent content (last 5 by updated_at)
        $allContent = array_merge(
            array_map(function ($p) { $p['_type'] = 'post'; return $p; }, $posts),
            array_map(function ($p) { $p['_type'] = 'page'; return $p; }, $pages),
        );
        usort($allContent, function ($a, $b) {
            $ta = $a['updated_at'] ?? '';
            $tb = $b['updated_at'] ?? '';
            return strcmp($tb, $ta);
        });
        $recentContent = array_slice($allContent, 0, 5);

        // Quick actions
        $quickActions = app()->hooks()->fire('admin.quick_actions', []);
        if (!is_array($quickActions)) {
            $quickActions = [];
        }

        foreach ($this->admin->getPanels() as $panel) {
            $panelActions = $panel->getQuickActions();
            if (!empty($panelActions)) {
                foreach ($panelActions as $qa) {
                    $quickActions[] = $qa;
                }
            }
        }

        foreach ($quickActions as &$action) {
            if (is_array($action) && isset($action['title'])) {
                $action['title'] = t($action['title']);
            }
        }
        unset($action);

        usort($quickActions, function ($a, $b) {
            $orderA = isset($a['order']) ? (int)$a['order'] : 100;
            $orderB = isset($b['order']) ? (int)$b['order'] : 100;
            return $orderA - $orderB;
        });

        $content = $this->renderView('dashboard', [
            'user' => $this->getUser(),
            'quickActions' => $quickActions,
            'stats' => $stats,
            'recentContent' => $recentContent,
        ]);

        return $this->renderAdmin(t('admin-dashboard.title'), $content, [
            'user' => $this->getUser(),
        ]);
    }
}
