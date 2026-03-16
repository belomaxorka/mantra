<?php

return array(
    'admin.settings.title' => 'Settings',
    'admin.settings.general' => 'General',
    'admin.settings.save' => 'Save',

    'admin.settings.group.site' => 'Site',
    'admin.settings.group.locale' => 'Locale',
    'admin.settings.group.theme' => 'Theme',
    'admin.settings.group.content' => 'Content',
    'admin.settings.group.modules' => 'Modules',
    'admin.settings.group.security' => 'Security',
    'admin.settings.group.session' => 'Session',
    'admin.settings.group.cache' => 'Cache',
    'admin.settings.group.logging' => 'Logging',
    'admin.settings.group.proxy' => 'Proxy / CDN',
    'admin.settings.group.debug' => 'Debug',
    'admin.settings.group.advanced' => 'Advanced',

    // Keys from Config::defaults() (dot-paths)
    'admin.settings.site.name' => 'Site name',
    'admin.settings.site.url' => 'Site URL',

    'admin.settings.locale.timezone' => 'Timezone',
    'admin.settings.locale.default_language' => 'Default language',
    'admin.settings.locale.fallback_locale' => 'Fallback locale',

    'admin.settings.debug.enabled' => 'Debug mode',

    'admin.settings.logging.level' => 'Log level',
    'admin.settings.logging.retention_days' => 'Log retention (days)',

    'admin.settings.cache.enabled' => 'Cache enabled',
    'admin.settings.cache.lifetime' => 'Cache lifetime (seconds)',

    'admin.settings.session.name' => 'Session name',
    'admin.settings.session.lifetime' => 'Session lifetime (seconds)',

    'admin.settings.security.password_hash_algo' => 'Password hash algorithm',
    'admin.settings.security.csrf_token_name' => 'CSRF token name',

    'admin.settings.proxy.trusted_proxies' => 'Trusted proxies',
    'admin.settings.proxy.trusted_proxies.help' => 'One IP/CIDR per line. Proxy headers are only trusted when REMOTE_ADDR matches.',

    'admin.settings.content.format' => 'Content format',
    'admin.settings.content.posts_per_page' => 'Posts per page',

    'admin.settings.theme.active' => 'Active theme',

    'admin.settings.modules.enabled' => 'Enabled modules',
    'admin.settings.modules.enabled.help' => 'One module ID per line.',

    'admin.settings.advanced' => 'Advanced',
    'admin.settings.advanced.help' => '',

    // For security.password_hash_algo
    'admin.settings.security.password_hash_algo.help' => 'Use a PHP password hashing algorithm identifier like PASSWORD_DEFAULT.',

    // For site.url
    'admin.settings.site.url.help' => 'Base URL used to build links (e.g. https://example.com).',

    // Modules list (General settings)
    'admin.modules.author' => 'Author:',
    'admin.modules.homepage' => 'Site:',
    'admin.modules.settings' => 'Settings',
    'admin.modules.delete' => 'Delete',
    'admin.modules.delete_confirm' => 'Delete this module? This will remove its files from the server.',
);
