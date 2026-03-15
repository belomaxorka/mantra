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

    // Keys from Config::defaults()
    'admin.settings.site_name' => 'Site name',
    'admin.settings.site_url' => 'Site URL',
    'admin.settings.timezone' => 'Timezone',
    'admin.settings.default_language' => 'Default language',
    'admin.settings.fallback_locale' => 'Fallback locale',

    'admin.settings.debug' => 'Debug mode',

    'admin.settings.log_level' => 'Log level',
    'admin.settings.log_retention_days' => 'Log retention (days)',

    'admin.settings.cache_enabled' => 'Cache enabled',
    'admin.settings.cache_lifetime' => 'Cache lifetime (seconds)',

    'admin.settings.session_name' => 'Session name',
    'admin.settings.session_lifetime' => 'Session lifetime (seconds)',

    'admin.settings.password_hash_algo' => 'Password hash algorithm',
    'admin.settings.csrf_token_name' => 'CSRF token name',

    'admin.settings.trusted_proxies' => 'Trusted proxies',
    'admin.settings.trusted_proxies.help' => 'One IP/CIDR per line. Proxy headers are only trusted when REMOTE_ADDR matches.',

    'admin.settings.content_format' => 'Content format',
    'admin.settings.posts_per_page' => 'Posts per page',

    'admin.settings.active_theme' => 'Active theme',

    'admin.settings.enabled_modules' => 'Enabled modules',
    'admin.settings.enabled_modules.help' => 'One module ID per line.',
);
