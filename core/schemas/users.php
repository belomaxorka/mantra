<?php

// Collection schema for: users
// - version: current document schema version
// - defaults: applied when missing
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
    )
);
