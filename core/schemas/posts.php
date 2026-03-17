<?php

// Collection schema for: posts

return array(
    'version' => 1,
    'defaults' => array(
        'title' => '',
        'slug' => '',
        'content' => '',
        'excerpt' => '',
        'status' => 'draft',
        'category' => '',
        'author' => '',
        'created_at' => '',
        'updated_at' => ''
    ),
    'fields' => array(
        'title' => array(
            'type' => 'string',
            'required' => true,
            'minLength' => 1,
            'maxLength' => 255
        ),
        'slug' => array(
            'type' => 'string',
            'required' => true,
            'minLength' => 1,
            'maxLength' => 255,
            'pattern' => '/^[a-z0-9-]+$/'
        ),
        'status' => array(
            'type' => 'enum',
            'values' => array('draft', 'published', 'archived'),
            'required' => true
        ),
        'excerpt' => array(
            'type' => 'string',
            'maxLength' => 500
        )
    )
);
