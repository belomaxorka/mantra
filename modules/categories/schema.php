<?php

// Collection schema for: categories

return array(
    'version' => 1,
    'defaults' => array(
        'title' => '',
        'slug' => '',
        'description' => '',
        'order' => 0,
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
        'order' => array(
            'type' => 'integer',
            'min' => 0,
            'max' => 999
        )
    )
);
