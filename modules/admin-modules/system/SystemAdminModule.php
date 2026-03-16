<?php

class SystemAdminModule implements AdminSubmodule {

    public function __construct($manifest = array(), $admin = null) {
    }

    public function getId() {
        return 'system';
    }

    public function init($admin) {
        // Parent "System" group + children
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'system',
                'title' => array('key' => 'admin.sidebar.group.system', 'fallback' => 'System'),
                'icon' => 'bi-gear',
                'group' => array('key' => 'admin.sidebar.group.system', 'fallback' => 'System'),
                'order' => 20,
                'url' => '#',
                'children' => array(
                ),
            );

            return $items;
        });
    }
}
