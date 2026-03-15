<?php

class ContentAdminModule implements AdminSubmodule {

    public function __construct($manifest = array(), $admin = null) {
    }

    public function getId() {
        return 'content';
    }

    public function init($admin) {
        // Parent "Content" group + children
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'content',
                'title' => array('key' => 'admin.sidebar.group.content', 'fallback' => 'Content'),
                'icon' => 'bi-folder2-open',
                'group' => array('key' => 'admin.sidebar.group.content', 'fallback' => 'Content'),
                'order' => 10,
                'url' => '#',
                'children' => array(
                    array(
                        'id' => 'pages',
                        'title' => array('key' => 'pages.admin.title', 'fallback' => 'Pages'),
                        'icon' => 'bi-file-earmark-text',
                        'url' => base_url('/admin/pages'),
                        'order' => 10,
                    ),
                    array(
                        'id' => 'media',
                        'title' => array('key' => 'media.admin.title', 'fallback' => 'Media'),
                        'icon' => 'bi-images',
                        'url' => base_url('/admin/media'),
                        'order' => 20,
                    ),
                    array(
                        'id' => 'editor',
                        'title' => array('key' => 'editor.admin.title', 'fallback' => 'Editor'),
                        'icon' => 'bi-pencil-square',
                        'url' => base_url('/admin/settings?tab=editor'),
                        'order' => 60,
                    ),
                ),
            );

            return $items;
        });
    }
}
