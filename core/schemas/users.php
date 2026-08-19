<?php declare(strict_types=1);

// Core collection schema for users. Authentication and installation depend on
// these invariants before the optional administration UI has been loaded.

return [
    'version' => 1,
    'unique' => [
        'username' => ['case_insensitive' => true],
        'email' => ['case_insensitive' => true],
    ],
    'defaults' => [
        'username' => '',
        'email' => '',
        'password' => '',
        'role' => 'editor',
        'status' => 'active',
    ],
    'fields' => [
        'username' => [
            'type' => 'string',
            'required' => true,
            'minLength' => User::MIN_USERNAME_LENGTH,
            'maxLength' => User::MAX_USERNAME_LENGTH,
            'pattern' => User::USERNAME_PATTERN,
        ],
        'email' => [
            'type' => 'email',
            'required' => false,
            'maxLength' => User::MAX_EMAIL_LENGTH,
        ],
        'password' => [
            'type' => 'string',
            'required' => true,
            'minLength' => 60,
            'maxLength' => 255,
        ],
        'role' => [
            'type' => 'enum',
            'values' => User::ROLES,
            'required' => true,
        ],
        'status' => [
            'type' => 'enum',
            'values' => User::STATUSES,
            'required' => true,
        ],
    ],
];
