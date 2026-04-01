<?php

namespace Admin;

class PostsPanel extends ContentPanel {

    public function id() {
        return 'posts';
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
            'author' => '',
            'created_at' => '',
            'updated_at' => ''
        );
    }

    protected function extractFormData() {
        return array(
            'title' => post_trimmed('title'),
            'slug' => post_trimmed('slug'),
            'content' => request()->post('content', ''),
            'excerpt' => request()->post('excerpt', ''),
            'status' => request()->post('status', 'draft'),
            'category' => request()->post('category', ''),
        );
    }
}
