<?php
/**
 * Analytics Module Settings Schema
 */
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'analytics.settings.services',
            'fields' => array(
                array(
                    'path' => 'google_analytics_id',
                    'type' => 'text',
                    'title' => 'analytics.settings.google_analytics_id',
                    'help' => 'analytics.settings.google_analytics_id.help',
                    'default' => ''
                ),
                array(
                    'path' => 'yandex_metrika_id',
                    'type' => 'text',
                    'title' => 'analytics.settings.yandex_metrika_id',
                    'help' => 'analytics.settings.yandex_metrika_id.help',
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'custom',
            'title' => 'analytics.settings.custom',
            'fields' => array(
                array(
                    'path' => 'custom_code',
                    'type' => 'textarea',
                    'title' => 'analytics.settings.custom_code',
                    'help' => 'analytics.settings.custom_code.help',
                    'default' => ''
                ),
            )
        ),
    )
);
