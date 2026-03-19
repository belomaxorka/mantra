<?php

/**
 * UI Schema for global config settings (Admin -> Settings -> General).
 *
 * IMPORTANT: This is NOT the actual config file!
 * - This file defines the UI form structure (fields, tabs, types, defaults)
 * - Actual config values are stored in: content/settings/config.json
 * - Config is edited through the admin panel or programmatically via ConfigSettings class
 *
 * Options for dynamic selects and widgets (themes/modules list) are injected at runtime by AdminModule.
 */

return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'site',
            'title' => 'admin-settings.group.site',
            'fields' => array(
                array(
                    'path' => 'site.name',
                    'type' => 'text',
                    'title' => 'admin-settings.site.name',
                    'default' => 'Mantra CMS',
                ),
                array(
                    'path' => 'site.url',
                    'type' => 'text',
                    'title' => 'admin-settings.site.url',
                    'default' => '',
                ),
            ),
        ),
        array(
            'id' => 'locale',
            'title' => 'admin-settings.group.locale',
            'fields' => array(
                array(
                    'path' => 'locale.timezone',
                    'type' => 'text',
                    'title' => 'admin-settings.locale.timezone',
                    'default' => 'UTC',
                ),
                array(
                    'path' => 'locale.default_language',
                    'type' => 'select',
                    'title' => 'admin-settings.locale.default_language',
                    'default' => 'en',
                    'options' => array(),
                ),
                array(
                    'path' => 'locale.fallback_locale',
                    'type' => 'select',
                    'title' => 'admin-settings.locale.fallback_locale',
                    'default' => 'en',
                    'options' => array(),
                ),
            ),
        ),
        array(
            'id' => 'theme',
            'title' => 'admin-settings.group.theme',
            'fields' => array(
                array(
                    'path' => 'theme.active',
                    'type' => 'select',
                    'title' => 'admin-settings.theme.active',
                    'default' => 'default',
                    'options' => array(),
                ),
            ),
        ),
        array(
            'id' => 'content',
            'title' => 'admin-settings.group.content',
            'fields' => array(
                array(
                    'path' => 'content.format',
                    'type' => 'select',
                    'title' => 'admin-settings.content.format',
                    'default' => 'json',
                    'options' => array(
                        'json' => 'JSON',
                        'markdown' => 'Markdown',
                    ),
                ),
                array(
                    'path' => 'content.posts_per_page',
                    'type' => 'number',
                    'title' => 'admin-settings.content.posts_per_page',
                    'default' => 10,
                ),
            ),
        ),
        array(
            'id' => 'modules',
            'title' => 'admin-settings.group.modules',
            'fields' => array(
                array(
                    'path' => 'modules.enabled',
                    'type' => 'module_cards',
                    'title' => 'admin-settings.modules.enabled_modules',
                    'default' => array('admin'),
                    'options' => array(),
                ),
            ),
        ),
        array(
            'id' => 'security',
            'title' => 'admin-settings.group.security',
            'fields' => array(
                array(
                    'path' => 'security.password_hash_algo',
                    'type' => 'select',
                    'title' => 'admin-settings.security.password_hash_algo',
                    'default' => 'PASSWORD_DEFAULT',
                    'options' => array(),
                ),
                array(
                    'path' => 'security.csrf_token_name',
                    'type' => 'text',
                    'title' => 'admin-settings.security.csrf_token_name',
                    'default' => 'mantra_csrf',
                ),
            ),
        ),
        array(
            'id' => 'session',
            'title' => 'admin-settings.group.session',
            'fields' => array(
                array(
                    'path' => 'session.name',
                    'type' => 'text',
                    'title' => 'admin-settings.session.name',
                    'default' => 'mantra_session',
                ),
                array(
                    'path' => 'session.lifetime',
                    'type' => 'number',
                    'title' => 'admin-settings.session.lifetime',
                    'default' => 7200,
                    'help' => 'admin-settings.session.lifetime.help',
                ),
                array(
                    'path' => 'session.cookie_secure',
                    'type' => 'select',
                    'title' => 'admin-settings.session.cookie_secure',
                    'default' => 'auto',
                    'options' => array(
                        'auto' => 'Auto (detect HTTPS)',
                        'true' => 'Always secure',
                        'false' => 'Never secure',
                    ),
                    'help' => 'admin-settings.session.cookie_secure.help',
                ),
                array(
                    'path' => 'session.cookie_httponly',
                    'type' => 'toggle',
                    'title' => 'admin-settings.session.cookie_httponly',
                    'default' => true,
                    'help' => 'admin-settings.session.cookie_httponly.help',
                ),
                array(
                    'path' => 'session.cookie_samesite',
                    'type' => 'select',
                    'title' => 'admin-settings.session.cookie_samesite',
                    'default' => 'Lax',
                    'options' => array(),
                    'help' => 'admin-settings.session.cookie_samesite.help',
                ),
                array(
                    'path' => 'session.cookie_path',
                    'type' => 'text',
                    'title' => 'admin-settings.session.cookie_path',
                    'default' => '/',
                    'help' => 'admin-settings.session.cookie_path.help',
                ),
                array(
                    'path' => 'session.cookie_domain',
                    'type' => 'text',
                    'title' => 'admin-settings.session.cookie_domain',
                    'default' => '',
                    'help' => 'admin-settings.session.cookie_domain.help',
                ),
            ),
        ),
        array(
            'id' => 'logging',
            'title' => 'admin-settings.group.logging',
            'fields' => array(
                array(
                    'path' => 'logging.level',
                    'type' => 'select',
                    'title' => 'admin-settings.logging.level',
                    'default' => 'debug',
                    'options' => array(),
                ),
                array(
                    'path' => 'logging.retention_days',
                    'type' => 'number',
                    'title' => 'admin-settings.logging.retention_days',
                    'default' => 30,
                ),
            ),
        ),
        array(
            'id' => 'proxy',
            'title' => 'admin-settings.group.proxy',
            'fields' => array(
                array(
                    'path' => 'proxy.trusted_proxies',
                    'type' => 'textarea',
                    'title' => 'admin-settings.proxy.trusted_proxies',
                    'default' => array(),
                    'help' => 'admin-settings.proxy.trusted_proxies.help',
                ),
            ),
        ),
        array(
            'id' => 'performance',
            'title' => 'admin-settings.group.performance',
            'fields' => array(
                array(
                    'path' => 'performance.gzip_compression',
                    'type' => 'toggle',
                    'title' => 'admin-settings.performance.gzip_compression',
                    'default' => false,
                    'help' => 'admin-settings.performance.gzip_compression.help',
                ),
            ),
        ),
        array(
            'id' => 'debug',
            'title' => 'admin-settings.group.debug',
            'fields' => array(
                array(
                    'path' => 'debug.enabled',
                    'type' => 'toggle',
                    'title' => 'admin-settings.debug.enabled',
                    'default' => true,
                ),
            ),
        ),
    ),
);
