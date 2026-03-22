<?php

class AdminPostsModule extends ContentAdminModule
{
    protected function getContentType()
    {
        return 'Post';
    }

    protected function getCollectionName()
    {
        return 'posts';
    }

    protected function getDefaultItem()
    {
        return array(
            'title' => '',
            'slug' => '',
            'content' => '',
            'excerpt' => '',
            'status' => 'draft',
            'category' => '',
            'author' => '',
            'created_at' => '',
            'updated_at' => ''
        );
    }

    protected function extractFormData()
    {
        return array(
            'title' => post_trimmed('title'),
            'slug' => post_trimmed('slug'),
            'content' => request()->post('content', ''),
            'excerpt' => request()->post('excerpt', ''),
            'status' => request()->post('status', 'draft'),
            'category' => request()->post('category', ''),
        );
    }

    public function init()
    {
        $this->registerSidebarItem(array(
            'id' => 'posts',
            'title' => 'admin-posts.title',
            'icon' => 'bi-file-earmark-text',
            'group' => 'admin.sidebar.group.content',
            'order' => 15,
            'url' => base_url('/admin/posts'),
        ));

        $this->registerQuickAction(array(
            'id' => 'new-post',
            'title' => 'admin-posts.new_post',
            'icon' => 'bi-file-earmark-plus',
            'url' => base_url('/admin/posts/new'),
            'order' => 25,
        ));

        $this->registerAdminRoute('GET', 'posts', array($this, 'listItems'));
        $this->registerAdminRoute('GET', 'posts/new', array($this, 'newItem'));
        $this->registerAdminRoute('POST', 'posts/new', array($this, 'createItem'));
        $this->registerAdminRoute('GET', 'posts/edit/{id}', array($this, 'editItem'));
        $this->registerAdminRoute('POST', 'posts/edit/{id}', array($this, 'updateItem'));
        $this->registerAdminRoute('POST', 'posts/delete/{id}', array($this, 'deleteItem'));
    }
}
