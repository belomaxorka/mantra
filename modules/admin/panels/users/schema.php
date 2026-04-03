<?php declare(strict_types=1);

// Collection schema for: users
// - version: current document schema version
// - defaults: applied when missing
// - fields: validation rules
// - migrate: optional callable($doc, $fromVersion, $toVersion)

return [
    'version' => 1,
    'defaults' => [
        'username' => '',
        'email' => '',
        'password' => '',
        'role' => 'editor',
        'status' => 'active',
        'created_at' => '',
        'updated_at' => '',
    ],
    'fields' => [
        'username' => [
            'type' => 'string',
            'required' => true,
            'minLength' => 3,
            'maxLength' => 50,
            'pattern' => '/^[a-zA-Z0-9_-]+$/',
        ],
        'email' => [
            'type' => 'email',
            'required' => false,
            'maxLength' => 255,
        ],
        'password' => [
            'type' => 'string',
            'required' => true,
            'minLength' => 60, // bcrypt hash length
            'maxLength' => 255,
        ],
        'role' => [
            'type' => 'enum',
            'values' => ['admin', 'editor', 'viewer'],
            'required' => true,
        ],
        'status' => [
            'type' => 'enum',
            'values' => ['active', 'inactive', 'banned'],
            'required' => true,
        ],
    ],
];
