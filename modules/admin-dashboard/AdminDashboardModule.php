<?php

class AdminDashboardModule extends AdminModule {

    public function init() {
        $this->registerSidebarItem(array(
            'id' => 'dashboard',
            'title' => array('key' => 'admin.dashboard.title', 'fallback' => 'Dashboard'),
            'icon' => 'bi-speedometer2',
            'group' => array('key' => 'admin.sidebar.group.general', 'fallback' => 'General'),
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
