<?php
/**
 * Analytics Module Settings Schema
 */
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => array('key' => 'analytics.settings.services', 'fallback' => 'Analytics Services'),
            'fields' => array(
                array(
                    'path' => 'google_analytics_id',
                    'type' => 'text',
                    'title' => array('key' => 'analytics.settings.google_analytics_id', 'fallback' => 'Google Analytics ID'),
                    'help' => array('key' => 'analytics.settings.google_analytics_id.help', 'fallback' => 'Your Google Analytics tracking ID (e.g., G-XXXXXXXXXX or UA-XXXXXXXXX-X)'),
                    'default' => ''
                ),
                array(
                    'path' => 'yandex_metrika_id',
                    'type' => 'text',
                    'title' => array('key' => 'analytics.settings.yandex_metrika_id', 'fallback' => 'Yandex Metrika ID'),
                    'help' => array('key' => 'analytics.settings.yandex_metrika_id.help', 'fallback' => 'Your Yandex Metrika counter ID (numeric)'),
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'custom',
            'title' => array('key' => 'analytics.settings.custom', 'fallback' => 'Custom Code'),
            'fields' => array(
                array(
                    'path' => 'custom_code',
                    'type' => 'textarea',
                    'title' => array('key' => 'analytics.settings.custom_code', 'fallback' => 'Custom Tracking Code'),
                    'help' => array('key' => 'analytics.settings.custom_code.help', 'fallback' => 'Add custom analytics or tracking scripts (will be inserted in footer)'),
                    'default' => ''
                ),
            )
        ),
    )
);
