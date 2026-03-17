<?php

// Collection schema for: users
// - version: current document schema version
// - defaults: applied when missing
// - fields: validation rules
// - migrate: optional callable($doc, $fromVersion, $toVersion)

return array(
    'version' => 1,
    'defaults' => array(
        'username' => '',
        'email' => '',
        'password' => '',
        'role' => 'editor',
        'status' => 'active',
        'created_at' => '',
        'updated_at' => ''
    ),
    'fields' => array(
        'username' => array(
            'type' => 'string',
            'required' => true,
            'minLength' => 3,
            'maxLength' => 50,
            'pattern' => '/^[a-zA-Z0-9_-]+$/'
        ),
        'email' => array(
            'type' => 'email',
            'required' => false,
            'maxLength' => 255
        ),
        'password' => array(
            'type' => 'string',
            'required' => true,
            'minLength' => 60, // bcrypt hash length
            'maxLength' => 255
        ),
        'role' => array(
            'type' => 'enum',
            'values' => array('admin', 'editor', 'viewer'),
            'required' => true
        ),
        'status' => array(
            'type' => 'enum',
            'values' => array('active', 'inactive', 'banned'),
            'required' => true
        )
    )
);
