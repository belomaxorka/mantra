<?php

class SettingsAdminModule implements AdminSubmodule
{
    public function __construct($manifest = array(), $admin = null)
    {
    }

    public function getId()
    {
        return 'settings';
    }

    public function init($admin)
    {
        // Sidebar item
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'settings',
                'title' => array('key' => 'admin.settings.title', 'fallback' => 'Settings'),
                'icon' => 'bi-sliders',
                'group' => array('key' => 'admin.sidebar.group.system', 'fallback' => 'System'),
                'order' => 50,
                'url' => base_url('/admin/settings'),
            );

            return $items;
        });

        if (is_object($admin) && method_exists($admin, 'adminRoute')) {
            $admin->adminRoute('GET', 'settings', array($this, 'settings'));
            $admin->adminRoute('POST', 'settings', array($this, 'settings'));
        }
    }

    public function settings()
    {
        $admin = app()->modules()->getModule('admin');
        if (!$admin) {
            http_response_code(500);
            echo 'Admin module not loaded';
            return;
        }

        // Reuse existing AdminModule implementation to avoid rewriting the schema-driven UI.
        // This keeps AdminModule as a platform/host while routes are owned by admin-submodules.
        if (method_exists($admin, 'settings')) {
            return $admin->settings();
        }

        http_response_code(500);
        echo 'Settings handler not available';
    }
}
