<?php

namespace Admin;

class DashboardPanel extends AdminPanel {

    public function id() {
        return 'dashboard';
    }

    public function registerRoutes($admin) {
        $admin->adminRoute('GET', '', array($this, 'dashboard'));
    }

    public function dashboard() {
        // Collect quick actions from hooks (backward compat with BaseAdminModule)
        $quickActions = app()->hooks()->fire('admin.quick_actions', array());
        if (!is_array($quickActions)) {
            $quickActions = array();
        }

        // Collect quick actions from panels
        foreach ($this->admin->getPanels() as $panel) {
            $panelActions = $panel->getQuickActions();
            if (!empty($panelActions)) {
                foreach ($panelActions as $qa) {
                    $quickActions[] = $qa;
                }
            }
        }

        // Translate titles
        foreach ($quickActions as &$action) {
            if (is_array($action) && isset($action['title'])) {
                $action['title'] = t($action['title']);
            }
        }
        unset($action);

        // Sort by order
        usort($quickActions, function ($a, $b) {
            $orderA = isset($a['order']) ? (int)$a['order'] : 100;
            $orderB = isset($b['order']) ? (int)$b['order'] : 100;
            return $orderA - $orderB;
        });

        $content = $this->renderView('dashboard', array(
            'user' => $this->getUser(),
            'quickActions' => $quickActions
        ));

        return $this->renderAdmin(t('admin-dashboard.title'), $content, array(
            'user' => $this->getUser(),
        ));
    }
}
