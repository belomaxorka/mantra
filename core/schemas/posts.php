<?php

// Collection schema for: posts

return array(
    'version' => 2,
    'defaults' => array(
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
    ),
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
