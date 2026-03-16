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
            'title' => array('key' => 'admin-settings.group.site', 'fallback' => 'Site'),
            'fields' => array(
                array(
                    'path' => 'site.name',
                    'type' => 'text',
                    'title' => array('key' => 'admin-settings.site.name', 'fallback' => 'Site name'),
                    'default' => 'Mantra CMS',
                ),
                array(
                    'path' => 'site.url',
                    'type' => 'text',
                    'title' => array('key' => 'admin-settings.site.url', 'fallback' => 'Site URL'),
                    'default' => '',
                ),
            ),
        ),
        array(
            'id' => 'locale',
            'title' => array('key' => 'admin-settings.group.locale', 'fallback' => 'Locale'),
            'fields' => array(
                array(
                    'path' => 'locale.timezone',
                    'type' => 'text',
                    'title' => array('key' => 'admin-settings.locale.timezone', 'fallback' => 'Timezone'),
                    'default' => 'UTC',
                ),
                array(
                    'path' => 'locale.default_language',
                    'type' => 'select',
                    'title' => array('key' => 'admin-settings.locale.default_language', 'fallback' => 'Default language'),
                    'default' => 'en',
                    'options' => array(),
                ),
                array(
                    'path' => 'locale.fallback_locale',
                    'type' => 'select',
                    'title' => array('key' => 'admin-settings.locale.fallback_locale', 'fallback' => 'Fallback locale'),
                    'default' => 'en',
                    'options' => array(),
                ),
            ),
        ),
        array(
            'id' => 'theme',
            'title' => array('key' => 'admin-settings.group.theme', 'fallback' => 'Theme'),
            'fields' => array(
                array(
                    'path' => 'theme.active',
                    'type' => 'select',
                    'title' => array('key' => 'admin-settings.theme.active', 'fallback' => 'Active theme'),
                    'default' => 'default',
                    'options' => array(),
                ),
            ),
        ),
        array(
            'id' => 'content',
            'title' => array('key' => 'admin-settings.group.content', 'fallback' => 'Content'),
            'fields' => array(
                array(
                    'path' => 'content.format',
                    'type' => 'select',
                    'title' => array('key' => 'admin-settings.content.format', 'fallback' => 'Content format'),
                    'default' => 'json',
                    'options' => array(
                        'json' => 'JSON',
                        'markdown' => 'Markdown',
                    ),
                ),
                array(
                    'path' => 'content.posts_per_page',
                    'type' => 'number',
                    'title' => array('key' => 'admin-settings.content.posts_per_page', 'fallback' => 'Posts per page'),
                    'default' => 10,
                ),
            ),
        ),
        array(
            'id' => 'modules',
            'title' => array('key' => 'admin-settings.group.modules', 'fallback' => 'Modules'),
            'fields' => array(
                array(
                    'path' => 'modules.enabled',
                    'type' => 'module_cards',
                    'title' => array('key' => 'admin-settings.modules.enabled_modules', 'fallback' => 'Enabled modules'),
                    'default' => array('admin'),
                    'options' => array(),
                ),
            ),
        ),
        array(
            'id' => 'security',
            'title' => array('key' => 'admin-settings.group.security', 'fallback' => 'Security'),
            'fields' => array(
                array(
                    'path' => 'security.password_hash_algo',
                    'type' => 'select',
                    'title' => array('key' => 'admin-settings.security.password_hash_algo', 'fallback' => 'Password hash algorithm'),
                    'default' => 'PASSWORD_DEFAULT',
                    'options' => array(
                        'PASSWORD_DEFAULT' => 'PASSWORD_DEFAULT',
                        'PASSWORD_BCRYPT' => 'PASSWORD_BCRYPT',
                        'PASSWORD_ARGON2I' => 'PASSWORD_ARGON2I',
                        'PASSWORD_ARGON2ID' => 'PASSWORD_ARGON2ID',
                    ),
                ),
                array(
                    'path' => 'security.csrf_token_name',
                    'type' => 'text',
                    'title' => array('key' => 'admin-settings.security.csrf_token_name', 'fallback' => 'CSRF token name'),
                    'default' => 'mantra_csrf',
                ),
            ),
        ),
        array(
            'id' => 'session',
            'title' => array('key' => 'admin-settings.group.session', 'fallback' => 'Session'),
            'fields' => array(
                array(
                    'path' => 'session.name',
                    'type' => 'text',
                    'title' => array('key' => 'admin-settings.session.name', 'fallback' => 'Session name'),
                    'default' => 'mantra_session',
                ),
                array(
                    'path' => 'session.lifetime',
                    'type' => 'number',
                    'title' => array('key' => 'admin-settings.session.lifetime', 'fallback' => 'Session lifetime'),
                    'default' => 7200,
                ),
            ),
        ),
        array(
            'id' => 'cache',
            'title' => array('key' => 'admin-settings.group.cache', 'fallback' => 'Cache'),
            'fields' => array(
                array(
                    'path' => 'cache.enabled',
                    'type' => 'toggle',
                    'title' => array('key' => 'admin-settings.cache.enabled', 'fallback' => 'Enable cache'),
                    'default' => true,
                ),
                array(
                    'path' => 'cache.lifetime',
                    'type' => 'number',
                    'title' => array('key' => 'admin-settings.cache.lifetime', 'fallback' => 'Cache lifetime'),
                    'default' => 3600,
                ),
            ),
        ),
        array(
            'id' => 'logging',
            'title' => array('key' => 'admin-settings.group.logging', 'fallback' => 'Logging'),
            'fields' => array(
                array(
                    'path' => 'logging.level',
                    'type' => 'select',
                    'title' => array('key' => 'admin-settings.logging.level', 'fallback' => 'Log level'),
                    'default' => 'debug',
                    'options' => array(),
                ),
                array(
                    'path' => 'logging.retention_days',
                    'type' => 'number',
                    'title' => array('key' => 'admin-settings.logging.retention_days', 'fallback' => 'Retention days'),
                    'default' => 30,
                ),
            ),
        ),
        array(
            'id' => 'proxy',
            'title' => array('key' => 'admin-settings.group.proxy', 'fallback' => 'Proxy'),
            'fields' => array(
                array(
                    'path' => 'proxy.trusted_proxies',
                    'type' => 'textarea',
                    'title' => array('key' => 'admin-settings.proxy.trusted_proxies', 'fallback' => 'Trusted proxies'),
                    'default' => array(),
                    'help' => array('key' => 'admin-settings.proxy.trusted_proxies.help', 'fallback' => 'One IP or CIDR per line.'),
                ),
            ),
        ),
        array(
            'id' => 'debug',
            'title' => array('key' => 'admin-settings.group.debug', 'fallback' => 'Debug'),
            'fields' => array(
                array(
                    'path' => 'debug.enabled',
                    'type' => 'toggle',
                    'title' => array('key' => 'admin-settings.debug.enabled', 'fallback' => 'Enable debug mode'),
                    'default' => true,
                ),
            ),
        ),
    ),
);
