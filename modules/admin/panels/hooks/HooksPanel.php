<?php

namespace Admin;

class HooksPanel extends AdminPanel {

    public function id() {
        return 'hooks';
    }

    public function init($admin) {
        parent::init($admin);
    }

    public function registerRoutes($admin) {
        $admin->adminRoute('GET', 'hooks', array($this, 'index'));
    }

    public function index() {
        if (!$this->requirePermission('admin')) return;

        $hookManager = app()->hooks();
        $registry = \HookRegistry::all();
        $activeHooks = $hookManager->getActiveHooks();

        $allHookNames = array_unique(array_merge(array_keys($registry), $activeHooks));
        sort($allHookNames);

        // Split into core groups and module groups
        $coreGroups = array();
        $moduleGroups = array();

        foreach ($allHookNames as $name) {
            $info = isset($registry[$name]) ? $registry[$name] : array();
            $info['name'] = $name;
            $info['listeners'] = $hookManager->listenerCount($name);
            $info['registered'] = isset($registry[$name]);

            $source = isset($info['source']) ? $info['source'] : '';

            if ($source !== '') {
                // Module hook — group by source
                if (!isset($moduleGroups[$source])) {
                    $moduleGroups[$source] = array();
                }
                $moduleGroups[$source][] = $info;
            } else {
                // Core hook — group by prefix
                $group = $this->getCoreGroup($name);
                if (!isset($coreGroups[$group])) {
                    $coreGroups[$group] = array();
                }
                $coreGroups[$group][] = $info;
            }
        }

        ksort($moduleGroups);

        $content = $this->renderView('hooks', array(
            'coreGroups' => $coreGroups,
            'moduleGroups' => $moduleGroups,
            'totalHooks' => count($allHookNames),
            'totalListeners' => array_sum(array_map(function ($name) use ($hookManager) {
                return $hookManager->listenerCount($name);
            }, $allHookNames)),
        ));

        return $this->renderAdmin(t('admin-hooks.title'), $content, array(
            'breadcrumbs' => array(
                array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
                array('title' => t('admin-hooks.title')),
            ),
        ));
    }

    private function getCoreGroup($name) {
        if (strpos($name, 'system.') === 0 || $name === 'routes.register' || $name === 'view.render' || $name === 'permissions.register') return 'system';
        if (strpos($name, 'theme.') === 0) return 'theme';
        if (strpos($name, 'admin.') === 0) return 'admin';
        if (strpos($name, 'page.') === 0 || strpos($name, 'post.') === 0) return 'content';
        return 'other';
    }
}
