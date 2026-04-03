<?php declare(strict_types=1);

// Collection schema for: categories

return [
    'version' => 1,
    'defaults' => [
        'title' => '',
        'slug' => '',
        'description' => '',
        'order' => 0,
        'created_at' => '',
        'updated_at' => '',
    ],
    'fields' => [
        'title' => [
            'type' => 'string',
            'required' => true,
            'minLength' => 1,
            'maxLength' => 255,
        ],
        'slug' => [
            'type' => 'string',
            'required' => true,
            'minLength' => 1,
            'maxLength' => 255,
            'pattern' => '/^[a-z0-9-]+$/',
        ],
        'order' => [
            'type' => 'integer',
            'min' => 0,
            'max' => 999,
        ],
    ],
];
