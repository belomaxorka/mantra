<?php
/**
 * Example Integration Module Settings Schema
 */

return array(
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'General',
            'fields' => array(
                array(
                    'path' => 'show_in_navigation',
                    'type' => 'toggle',
                    'title' => 'Show in Navigation',
                    'help' => 'Display link in main navigation menu',
                    'default' => true,
                ),
            ),
        ),
    ),
);
