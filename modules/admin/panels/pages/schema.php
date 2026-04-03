<?php declare(strict_types=1);

// Collection schema for: pages

return [
    'version' => 2,
    'defaults' => [
        'title' => '',
        'slug' => '',
        'content' => '',
        'status' => 'draft',
        'show_in_navigation' => false,
        'navigation_order' => 50,
        'author' => '',
        'author_id' => '',
        'created_at' => '',
        'updated_at' => '',
    ],
    'migrate' => function ($doc, $from, $to) {
        if ($from < 2) {
            if (!isset($doc['author_id'])) {
                $doc['author_id'] = '';
            }
            $doc['schema_version'] = 2;
        }
        return $doc;
    },
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
        'status' => [
            'type' => 'enum',
            'values' => ['draft', 'published', 'archived'],
            'required' => true,
        ],
        'navigation_order' => [
            'type' => 'integer',
            'min' => 0,
            'max' => 999,
        ],
        'show_in_navigation' => [
            'type' => 'boolean',
        ],
    ],
];
