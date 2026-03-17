<?php

// Collection schema for: pages

return array(
    'version' => 1,
    'defaults' => array(
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
        'navigation_order' => array(
            'type' => 'integer',
            'min' => 0,
            'max' => 999
        ),
        'show_in_navigation' => array(
            'type' => 'boolean'
        )
    )
);
