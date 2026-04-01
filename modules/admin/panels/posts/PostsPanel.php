<?php

namespace Admin;

class PostsPanel extends ContentPanel {

    public function id() {
        return 'posts';
    }

    public function init($admin) {
        parent::init($admin);

        $this->hook('permissions.register', array($this, 'registerPermissions'));
    }

    /**
     * Register post permissions with the central registry.
     */
    public function registerPermissions($registry) {
        $registry->registerPermissions(array(
            'posts.view'       => 'View posts',
            'posts.create'     => 'Create posts',
            'posts.edit'       => 'Edit all posts',
            'posts.edit.own'   => 'Edit own posts',
            'posts.delete'     => 'Delete all posts',
            'posts.delete.own' => 'Delete own posts',
        ), 'Posts');

        $registry->addRoleDefaults('editor', array(
            'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
        ));
        $registry->addRoleDefaults('viewer', array(
            'posts.view',
        ));

        return $registry;
    }

    protected function getContentType() {
        return 'Post';
    }

    protected function getCollectionName() {
        return 'posts';
    }

    protected function getDefaultItem() {
        return array(
            'title' => '',
            'slug' => '',
            'content' => '',
            'excerpt' => '',
            'status' => 'draft',
            'category' => '',
            'image' => '',
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
            'excerpt' => app()->request()->post('excerpt', ''),
            'status' => app()->request()->post('status', 'draft'),
            'category' => app()->request()->post('category', ''),
            'image' => app()->request()->postTrimmed('image'),
        );
    }
}
