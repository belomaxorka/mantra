<?php
/**
 * Analytics Module Settings Schema
 */
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'Analytics Services',
            'fields' => array(
                array(
                    'path' => 'google_analytics_id',
                    'type' => 'text',
                    'title' => 'Google Analytics ID',
                    'help' => 'Your Google Analytics tracking ID (e.g., G-XXXXXXXXXX or UA-XXXXXXXXX-X)',
                    'default' => ''
                ),
                array(
                    'path' => 'yandex_metrika_id',
                    'type' => 'text',
                    'title' => 'Yandex Metrika ID',
                    'help' => 'Your Yandex Metrika counter ID (numeric)',
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'custom',
            'title' => 'Custom Code',
            'fields' => array(
                array(
                    'path' => 'custom_code',
                    'type' => 'textarea',
                    'title' => 'Custom Tracking Code',
                    'help' => 'Add custom analytics or tracking scripts (will be inserted in footer)',
                    'default' => ''
                ),
            )
        ),
    )
);
