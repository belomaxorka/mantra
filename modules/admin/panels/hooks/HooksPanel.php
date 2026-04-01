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

        // Build hook data: merge registry + runtime listeners
        $groups = array();
        $allHookNames = array_unique(array_merge(array_keys($registry), $activeHooks));
        sort($allHookNames);

        foreach ($allHookNames as $name) {
            // Determine group from hook name prefix
            $group = $this->getHookGroup($name);

            $info = isset($registry[$name]) ? $registry[$name] : array();
            $info['name'] = $name;
            $info['listeners'] = $hookManager->listenerCount($name);
            $info['registered'] = isset($registry[$name]);

            if (!isset($groups[$group])) {
                $groups[$group] = array();
            }
            $groups[$group][] = $info;
        }

        $content = $this->renderView('hooks', array(
            'groups' => $groups,
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

    private function getHookGroup($name) {
        if (strpos($name, 'system.') === 0 || $name === 'routes.register' || $name === 'view.render') return 'system';
        if (strpos($name, 'theme.') === 0) return 'theme';
        if (strpos($name, 'admin.') === 0) return 'admin';
        if (strpos($name, 'page.') === 0 || strpos($name, 'post.') === 0) return 'content';
        if ($name === 'permissions.register') return 'system';
        return 'other';
    }
}
