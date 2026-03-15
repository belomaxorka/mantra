<?php

class ExampleAdminModule implements AdminSubmodule {

    public function __construct($manifest = array(), $admin = null) {
        // no-op for now
    }

    public function getId() {
        return 'example';
    }

    public function init($admin) {
        // Sidebar: demonstrate nested tree
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'example',
                'title' => array('key' => 'admin.example.title', 'fallback' => 'Example'),
                'icon' => 'bi-lightning-charge',
                'group' => array('key' => 'admin.sidebar.group.system', 'fallback' => 'System'),
                'order' => 90,
                'children' => array(
                    array(
                        'id' => 'example',
                        'title' => array('key' => 'admin.example.child.hello', 'fallback' => 'Hello'),
                        'url' => base_url('/admin/example/hello'),
                        'order' => 10,
                    ),
                ),
            );

            return $items;
        });

        // Assets
        app()->hooks()->register('admin.head', function ($html) {
            if (!is_string($html)) {
                $html = '';
            }
            $html .= "\n" . '<style>.example-admin-badge{display:inline-block;padding:2px 6px;border-radius:6px;background:#0d6efd;color:#fff;font-size:12px;}</style>';
            return $html;
        });

        app()->hooks()->register('admin.footer', function ($html) {
            if (!is_string($html)) {
                $html = '';
            }
            $html .= "\n" . '<script>window.__adminExampleLoaded = true;</script>';
            return $html;
        });

        // Route
        if (is_object($admin) && method_exists($admin, 'adminRoute')) {
            $admin->adminRoute('GET', 'example/hello', array($this, 'hello'));
        }
    }

    public function hello() {
        $content = '<h1>Example admin submodule</h1>';
        $content .= '<p><span class="example-admin-badge">OK</span> admin.head/admin.footer hooks loaded.</p>';

        $admin = app()->modules()->getModule('admin');
        if ($admin && method_exists($admin, 'render')) {
            return $admin->render('Example', $content);
        }

        echo $content;
    }
}
