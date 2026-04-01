<?php

namespace Admin;

class PagesPanel extends ContentPanel {

    public function id() {
        return 'pages';
    }

    protected function getContentType() {
        return 'Page';
    }

    protected function getCollectionName() {
        return 'pages';
    }

    protected function getDefaultItem() {
        return array(
            'title' => '',
            'slug' => '',
            'content' => '',
            'status' => 'draft',
            'show_in_navigation' => false,
            'navigation_order' => 50,
            'author' => '',
            'author_id' => '',
            'created_at' => '',
            'updated_at' => ''
        );
    }

    protected function extractFormData() {
        return array(
            'title' => app()->request()->postTrimmed('title'),
            'slug' => app()->request()->postTrimmed('slug'),
            'content' => app()->request()->post('content', ''),
            'status' => app()->request()->post('status', 'draft'),
            'show_in_navigation' => (bool)app()->request()->post('show_in_navigation', false),
            'navigation_order' => (int)app()->request()->post('navigation_order', 50),
        );
    }

    public function init($admin) {
        parent::init($admin);

        $this->hook('permissions.register', array($this, 'registerPermissions'));
        $this->hook('theme.navigation', array($this, 'addPagesToNavigation'));
    }

    /**
     * Register page permissions with the central registry.
     */
    public function registerPermissions($registry) {
        $registry->registerPermissions(array(
            'pages.view'       => 'View pages',
            'pages.create'     => 'Create pages',
            'pages.edit'       => 'Edit all pages',
            'pages.edit.own'   => 'Edit own pages',
            'pages.delete'     => 'Delete all pages',
            'pages.delete.own' => 'Delete own pages',
        ), 'Pages');

        $registry->addRoleDefaults('editor', array(
            'pages.view', 'pages.create', 'pages.edit', 'pages.delete',
        ));
        $registry->addRoleDefaults('viewer', array(
            'pages.view',
        ));

        return $registry;
    }

    /**
     * Add published pages with show_in_navigation to the theme navigation menu.
     */
    public function addPagesToNavigation($navItems) {
        if (!is_array($navItems)) {
            $navItems = array();
        }

        $pages = app()->db()->query('pages', array(
            'status' => 'published',
            'show_in_navigation' => true
        ));

        foreach ($pages as $page) {
            $navItems[] = array(
                'id' => 'page-' . $page['slug'],
                'title' => $page['title'],
                'url' => base_url('/' . $page['slug']),
                'order' => isset($page['navigation_order']) ? (int)$page['navigation_order'] : 50,
            );
        }

        return $navItems;
    }
}
