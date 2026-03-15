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
    }
}
