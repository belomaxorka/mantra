<?php declare(strict_types=1);
/**
 * Analytics Module Settings Schema
 */
return [
    'version' => 1,
    'tabs' => [
        [
            'id' => 'general',
            'title' => 'analytics.settings.services',
            'fields' => [
                [
                    'path' => 'google_analytics_id',
                    'type' => 'text',
                    'title' => 'analytics.settings.google_analytics_id',
                    'help' => 'analytics.settings.google_analytics_id.help',
                    'default' => '',
                ],
                [
                    'path' => 'yandex_metrika_id',
                    'type' => 'text',
                    'title' => 'analytics.settings.yandex_metrika_id',
                    'help' => 'analytics.settings.yandex_metrika_id.help',
                    'default' => '',
                ],
            ],
        ],
        [
            'id' => 'custom',
            'title' => 'analytics.settings.custom',
            'fields' => [
                [
                    'path' => 'custom_code',
                    'type' => 'textarea',
                    'title' => 'analytics.settings.custom_code',
                    'help' => 'analytics.settings.custom_code.help',
                    'default' => '',
                ],
            ],
        ],
    ],
];
