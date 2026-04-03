<?php declare(strict_types=1);

namespace Admin;

class HooksPanel extends AdminPanel {

    public function id() {
        return 'hooks';
    }

    public function init($admin): void {
        parent::init($admin);
    }

    public function registerRoutes($admin): void {
        $admin->adminRoute('GET', 'hooks', [$this, 'index']);
    }

    public function index() {
        if (!$this->requirePermission('admin')) return;

        $hookManager = app()->hooks();
        $registry = \HookRegistry::all();
        $activeHooks = $hookManager->getActiveHooks();

        $allHookNames = array_unique(array_merge(array_keys($registry), $activeHooks));
        sort($allHookNames);

        // Split into core groups and module groups
        $coreGroups = [];
        $moduleGroups = [];

        foreach ($allHookNames as $name) {
            $info = $registry[$name] ?? [];
            $info['name'] = $name;
            $info['listeners'] = $hookManager->listenerCount($name);
            $info['registered'] = isset($registry[$name]);

            $source = $info['source'] ?? '';

            if ($source !== '') {
                // Module hook — group by source
                if (!isset($moduleGroups[$source])) {
                    $moduleGroups[$source] = [];
                }
                $moduleGroups[$source][] = $info;
            } else {
                // Core hook — group by prefix
                $group = $this->getCoreGroup($name);
                if (!isset($coreGroups[$group])) {
                    $coreGroups[$group] = [];
                }
                $coreGroups[$group][] = $info;
            }
        }

        ksort($moduleGroups);

        $content = $this->renderView('hooks', [
            'coreGroups' => $coreGroups,
            'moduleGroups' => $moduleGroups,
            'totalHooks' => count($allHookNames),
            'totalListeners' => array_sum(array_map(fn ($name) => $hookManager->listenerCount($name), $allHookNames)),
        ]);

        return $this->renderAdmin(t('admin-hooks.title'), $content, [
            'breadcrumbs' => [
                ['title' => t('admin-dashboard.title'), 'url' => base_url('/admin')],
                ['title' => t('admin-hooks.title')],
            ],
        ]);
    }

    private function getCoreGroup($name) {
        if (str_starts_with($name, 'system.') || $name === 'routes.register' || $name === 'view.render' || $name === 'permissions.register') return 'system';
        if (str_starts_with($name, 'theme.')  ) return 'theme';
        if (str_starts_with($name, 'admin.')  ) return 'admin';
        if (str_starts_with($name, 'page.') || str_starts_with($name, 'post.')  ) return 'content';
        return 'other';
    }
}
