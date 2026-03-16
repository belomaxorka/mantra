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
                ),
            );

            return $items;
        });
    }
}
