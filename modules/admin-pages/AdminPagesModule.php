<?php

class AdminPagesModule extends ContentAdminModule
{
    protected function getContentType()
    {
        return 'Page';
    }

    protected function getCollectionName()
    {
        return 'pages';
    }

    protected function getDefaultItem()
    {
        return array(
            'title' => '',
            'slug' => '',
            'content' => '',
            'status' => 'draft',
            'image' => '',
            'show_in_navigation' => false,
            'navigation_order' => 50,
            'author' => '',
            'created_at' => '',
            'updated_at' => ''
        );
    }

    protected function extractFormData()
    {
        return array(
            'title' => trim(request()->post('title', '')),
            'slug' => trim(request()->post('slug', '')),
            'content' => request()->post('content', ''),
            'status' => request()->post('status', 'draft'),
            'image' => trim(request()->post('image', '')),
            'show_in_navigation' => (bool)request()->post('show_in_navigation', false),
            'navigation_order' => (int)request()->post('navigation_order', 50),
        );
    }

    public function init()
    {
        $this->registerSidebarItem(array(
            'id' => 'pages',
            'title' => 'admin-pages.title',
            'icon' => 'bi-file-earmark-text',
            'group' => 'admin.sidebar.group.content',
            'order' => 10,
            'url' => base_url('/admin/pages'),
        ));

        $this->registerQuickAction(array(
            'id' => 'new-page',
            'title' => 'admin-pages.new',
            'icon' => 'bi-file-earmark-plus',
            'url' => base_url('/admin/pages/new'),
            'order' => 20,
        ));

        $this->registerAdminRoute('GET', 'pages', array($this, 'listItems'));
        $this->registerAdminRoute('GET', 'pages/new', array($this, 'newItem'));
        $this->registerAdminRoute('POST', 'pages/new', array($this, 'createItem'));
        $this->registerAdminRoute('GET', 'pages/edit/{id}', array($this, 'editItem'));
        $this->registerAdminRoute('POST', 'pages/edit/{id}', array($this, 'updateItem'));
        $this->registerAdminRoute('POST', 'pages/delete/{id}', array($this, 'deleteItem'));
    }
}
