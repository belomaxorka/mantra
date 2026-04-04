<?php declare(strict_types=1);

/**
 * UI Schema for global config settings (Admin -> Settings -> General).
 *
 * IMPORTANT: This is NOT the actual config file!
 * - This file defines the UI form structure (fields, tabs, types, defaults)
 * - Actual config values are stored in: content/settings/config.json
 * - Config is edited through the admin panel or programmatically via ConfigSettings class
 *
 * Options for dynamic selects (themes/modules list) are injected at runtime by AdminModule.
 */

return [
    'version' => 3,
    'migrate' => function ($data, $from, $to) {
        // v2: admin sub-modules replaced by panels — strip from modules.enabled
        if ($from < 2 && isset($data['modules']['enabled']) && is_array($data['modules']['enabled'])) {
            $panelIds = ['admin-dashboard', 'admin-pages', 'admin-posts', 'admin-settings'];
            $data['modules']['enabled'] = array_values(array_diff($data['modules']['enabled'], $panelIds));
        }
        // v3: admin.accent_color — no data migration needed, gets default automatically
        $data['schema_version'] = $to;
        return $data;
    },
    'tabs' => [
        [
            'id' => 'site',
            'title' => 'admin-settings.group.site',
            'fields' => [
                [
                    'path' => 'site.name',
                    'type' => 'text',
                    'title' => 'admin-settings.site.name',
                    'default' => 'Mantra CMS',
                ],
                [
                    'path' => 'site.url',
                    'type' => 'text',
                    'title' => 'admin-settings.site.url',
                    'default' => '',
                ],
            ],
        ],
        [
            'id' => 'locale',
            'title' => 'admin-settings.group.locale',
            'fields' => [
                [
                    'path' => 'locale.timezone',
                    'type' => 'timezone_select',
                    'title' => 'admin-settings.locale.timezone',
                    'default' => 'UTC',
                    'options' => [],
                ],
                [
                    'path' => 'locale.date_format',
                    'type' => 'select',
                    'title' => 'admin-settings.locale.date_format',
                    'default' => 'j F Y',
                    'options' => [
                        'j F Y' => '',
                        'd.m.Y' => '',
                        'm/d/Y' => '',
                        'Y-m-d' => '',
                        'd M Y' => '',
                        'F j, Y' => '',
                    ],
                ],
                [
                    'path' => 'locale.time_format',
                    'type' => 'select',
                    'title' => 'admin-settings.locale.time_format',
                    'default' => 'H:i',
                    'options' => [
                        'H:i' => '',
                        'g:i A' => '',
                    ],
                ],
                [
                    'path' => 'locale.default_language',
                    'type' => 'select',
                    'title' => 'admin-settings.locale.default_language',
                    'default' => 'en',
                    'options' => [],
                ],
                [
                    'path' => 'locale.fallback_locale',
                    'type' => 'select',
                    'title' => 'admin-settings.locale.fallback_locale',
                    'default' => 'en',
                    'options' => [],
                ],
            ],
        ],
        [
            'id' => 'theme',
            'title' => 'admin-settings.group.theme',
            'fields' => [
                [
                    'path' => 'theme.active',
                    'type' => 'select',
                    'title' => 'admin-settings.theme.active',
                    'default' => 'default',
                    'options' => [],
                ],
            ],
        ],
        [
            'id' => 'appearance',
            'title' => 'admin-settings.group.appearance',
            'fields' => [
                [
                    'path' => 'admin.accent_color',
                    'type' => 'select',
                    'title' => 'admin-settings.appearance.accent_color',
                    'default' => 'indigo',
                    'help' => 'admin-settings.appearance.accent_color.help',
                    'options' => [],
                ],
                [
                    'path' => 'admin.sidebar_color',
                    'type' => 'select',
                    'title' => 'admin-settings.appearance.sidebar_color',
                    'default' => 'dark',
                    'help' => 'admin-settings.appearance.sidebar_color.help',
                    'options' => [],
                ],
                [
                    'path' => 'admin.font',
                    'type' => 'select',
                    'title' => 'admin-settings.appearance.font',
                    'default' => 'inter',
                    'help' => 'admin-settings.appearance.font.help',
                    'options' => [],
                ],
            ],
        ],
        [
            'id' => 'content',
            'title' => 'admin-settings.group.content',
            'fields' => [
                [
                    'path' => 'content.format',
                    'type' => 'select',
                    'title' => 'admin-settings.content.format',
                    'default' => 'json',
                    'options' => [
                        'json' => 'JSON',
                        'markdown' => 'Markdown',
                    ],
                ],
                [
                    'path' => 'content.posts_per_page',
                    'type' => 'number',
                    'title' => 'admin-settings.content.posts_per_page',
                    'default' => 10,
                ],
            ],
        ],
        [
            'id' => 'modules',
            'title' => 'admin-settings.group.modules',
            'fields' => [
                [
                    'path' => 'modules.enabled',
                    'type' => 'module_cards',
                    'title' => 'admin-settings.modules.enabled',
                    'default' => ['admin'],
                    'options' => [],
                ],
            ],
        ],
        [
            'id' => 'security',
            'title' => 'admin-settings.group.security',
            'fields' => [
                [
                    'path' => 'security.password_hash_algo',
                    'type' => 'select',
                    'title' => 'admin-settings.security.password_hash_algo',
                    'default' => 'PASSWORD_DEFAULT',
                    'options' => [],
                ],
                [
                    'path' => 'security.csrf_token_name',
                    'type' => 'text',
                    'title' => 'admin-settings.security.csrf_token_name',
                    'default' => 'mantra_csrf',
                ],
            ],
        ],
        [
            'id' => 'session',
            'title' => 'admin-settings.group.session',
            'fields' => [
                [
                    'path' => 'session.name',
                    'type' => 'text',
                    'title' => 'admin-settings.session.name',
                    'default' => 'mantra_session',
                ],
                [
                    'path' => 'session.lifetime',
                    'type' => 'number',
                    'title' => 'admin-settings.session.lifetime',
                    'default' => 7200,
                    'help' => 'admin-settings.session.lifetime.help',
                ],
                [
                    'path' => 'session.cookie_secure',
                    'type' => 'select',
                    'title' => 'admin-settings.session.cookie_secure',
                    'default' => 'auto',
                    'options' => [
                        'auto' => 'Auto (detect HTTPS)',
                        'true' => 'Always secure',
                        'false' => 'Never secure',
                    ],
                    'help' => 'admin-settings.session.cookie_secure.help',
                ],
                [
                    'path' => 'session.cookie_httponly',
                    'type' => 'toggle',
                    'title' => 'admin-settings.session.cookie_httponly',
                    'default' => true,
                    'help' => 'admin-settings.session.cookie_httponly.help',
                ],
                [
                    'path' => 'session.cookie_samesite',
                    'type' => 'select',
                    'title' => 'admin-settings.session.cookie_samesite',
                    'default' => 'Lax',
                    'options' => [],
                    'help' => 'admin-settings.session.cookie_samesite.help',
                ],
                [
                    'path' => 'session.cookie_path',
                    'type' => 'text',
                    'title' => 'admin-settings.session.cookie_path',
                    'default' => '/',
                    'help' => 'admin-settings.session.cookie_path.help',
                ],
                [
                    'path' => 'session.cookie_domain',
                    'type' => 'text',
                    'title' => 'admin-settings.session.cookie_domain',
                    'default' => '',
                    'help' => 'admin-settings.session.cookie_domain.help',
                ],
            ],
        ],
        [
            'id' => 'logging',
            'title' => 'admin-settings.group.logging',
            'fields' => [
                [
                    'path' => 'logging.level',
                    'type' => 'select',
                    'title' => 'admin-settings.logging.level',
                    'default' => 'debug',
                    'options' => [],
                ],
                [
                    'path' => 'logging.retention_days',
                    'type' => 'number',
                    'title' => 'admin-settings.logging.retention_days',
                    'default' => 30,
                ],
            ],
        ],
        [
            'id' => 'proxy',
            'title' => 'admin-settings.group.proxy',
            'fields' => [
                [
                    'path' => 'proxy.trusted_proxies',
                    'type' => 'textarea',
                    'title' => 'admin-settings.proxy.trusted_proxies',
                    'default' => [],
                    'help' => 'admin-settings.proxy.trusted_proxies.help',
                ],
            ],
        ],
        [
            'id' => 'performance',
            'title' => 'admin-settings.group.performance',
            'fields' => [
                [
                    'path' => 'performance.gzip_compression',
                    'type' => 'toggle',
                    'title' => 'admin-settings.performance.gzip_compression',
                    'default' => false,
                    'help' => 'admin-settings.performance.gzip_compression.help',
                ],
            ],
        ],
        [
            'id' => 'debug',
            'title' => 'admin-settings.group.debug',
            'fields' => [
                [
                    'path' => 'debug.enabled',
                    'type' => 'toggle',
                    'title' => 'admin-settings.debug.enabled',
                    'default' => true,
                ],
            ],
        ],
    ],
];
