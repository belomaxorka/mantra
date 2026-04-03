<?php declare(strict_types=1);

// Collection schema for: posts

return [
    'version' => 2,
    'defaults' => [
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
        'updated_at' => '',
    ],
    'migrate' => function ($doc, $from, $to) {
        if ($from < 2) {
            // author_id added; existing docs keep author (username) for display,
            // author_id will be empty until next edit or matched by username lookup.
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
        'excerpt' => [
            'type' => 'string',
            'maxLength' => 500,
        ],
    ],
];
