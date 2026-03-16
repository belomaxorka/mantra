<?php

class AdminDashboardModule extends BaseAdminModule {

    public function init() {
        $this->registerSidebarItem(array(
            'id' => 'dashboard',
            'title' => 'admin-dashboard.title',
            'icon' => 'bi-speedometer2',
            'group' => 'admin.sidebar.group.general',
            'order' => 0,
            'url' => base_url('/admin'),
        ));
        
        $this->registerAdminRoute('GET', '', array($this, 'dashboard'));
    }

    public function dashboard() {
        $quickActions = app()->hooks()->fire('admin.quick_actions', array());
        if (!is_array($quickActions)) {
            $quickActions = array();
        }

        // Normalize quick actions (translate titles)
        foreach ($quickActions as &$action) {
            if (is_array($action) && isset($action['title'])) {
                $action['title'] = $this->resolveAdminString($action['title']);
            }
        }
        unset($action);

        usort($quickActions, function($a, $b) {
            $orderA = isset($a['order']) ? (int)$a['order'] : 100;
            $orderB = isset($b['order']) ? (int)$b['order'] : 100;
            return $orderA - $orderB;
        });

        $content = $this->renderView('admin-dashboard:dashboard', array(
            'user' => $this->getUser(),
            'quickActions' => $quickActions
        ));

        return $this->renderAdmin('Dashboard', $content, array(
            'user' => $this->getUser(),
        ));
    }
}
