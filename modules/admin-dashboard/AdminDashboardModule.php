<?php

class AdminDashboardModule extends Module {

    public function init() {
        // Root dashboard item
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'dashboard',
                'title' => array('key' => 'admin.dashboard.title', 'fallback' => 'Dashboard'),
                'icon' => 'bi-speedometer2',
                'group' => array('key' => 'admin.sidebar.group.general', 'fallback' => 'General'),
                'order' => 0,
                'url' => base_url('/admin'),
            );

            return $items;
        });

        // Register routes
        app()->hooks()->register('routes.register', function ($data) {
            $admin = app()->modules()->getModule('admin');
            if ($admin && method_exists($admin, 'adminRoute')) {
                $admin->adminRoute('GET', '', array($this, 'dashboard'));
            }
        });
    }

    public function dashboard() {
        $admin = app()->modules()->getModule('admin');

        // Collect quick actions from modules via hook
        $quickActions = app()->hooks()->fire('admin.quick_actions', array());
        if (!is_array($quickActions)) {
            $quickActions = array();
        }

        // Sort by order
        usort($quickActions, function($a, $b) {
            $orderA = isset($a['order']) ? (int)$a['order'] : 100;
            $orderB = isset($b['order']) ? (int)$b['order'] : 100;
            return $orderA - $orderB;
        });

        $view = new View();
        $content = $view->fetch('admin-dashboard:dashboard', array(
            'user' => auth()->user(),
            'quickActions' => $quickActions
        ));

        if ($admin && method_exists($admin, 'render')) {
            return $admin->render('Dashboard', $content, array(
                'user' => auth()->user(),
            ));
        }

        echo $content;
    }
}
