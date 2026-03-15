<?php

// Collection schema for: users
// - version: current document schema version
// - defaults: applied when missing
// - migrate: optional callable($doc, $fromVersion, $toVersion)

return array(
    'version' => 1,
    'defaults' => array(
        'role' => 'editor',
        'status' => 'active'
    )
);
