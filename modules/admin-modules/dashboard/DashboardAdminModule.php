<?php

class DashboardAdminModule implements AdminSubmodule {

    public function __construct($manifest = array(), $admin = null) {
    }

    public function getId() {
        return 'dashboard';
    }

    public function init($admin) {
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

        if (is_object($admin) && method_exists($admin, 'adminRoute')) {
            $admin->adminRoute('GET', '', array($this, 'dashboard'));
        }
    }

    public function dashboard() {
        $admin = app()->modules()->getModule('admin');

        $view = new View();
        $content = $view->fetch('admin-modules/dashboard:dashboard', array(
            'user' => auth()->user()
        ));

        if ($admin && method_exists($admin, 'render')) {
            return $admin->render('Dashboard', $content, array(
                'user' => auth()->user(),
            ));
        }

        echo $content;
    }
}
