<?php

return array(
    // Module name
    'admin-settings.name' => 'Settings',

    // Settings page
    'admin-settings.title' => 'Settings',
    'admin-settings.general' => 'General',
    'admin-settings.modules' => 'Modules',
    'admin-settings.save' => 'Save Settings',
    'admin-settings.saved' => 'Settings saved successfully',
    'admin-settings.error' => 'Error saving settings',

    // Module management
    'admin-settings.modules.enabled' => 'Enabled Modules',
    'admin-settings.modules.available' => 'Available Modules',
    'admin-settings.modules.enable' => 'Enable',
    'admin-settings.modules.disable' => 'Disable',

    // Settings groups
    'admin-settings.group.site' => 'Site',
    'admin-settings.group.locale' => 'Locale',
    'admin-settings.group.theme' => 'Theme',
    'admin-settings.group.content' => 'Content',
    'admin-settings.group.modules' => 'Modules',
    'admin-settings.group.security' => 'Security',
    'admin-settings.group.session' => 'Session',
    'admin-settings.group.logging' => 'Logging',
    'admin-settings.group.proxy' => 'Proxy',
    'admin-settings.group.performance' => 'Performance',
    'admin-settings.group.debug' => 'Debug',

    // Site settings
    'admin-settings.site.name' => 'Site name',
    'admin-settings.site.url' => 'Site URL',

    // Locale settings
    'admin-settings.locale.timezone' => 'Timezone',
    'admin-settings.locale.date_format' => 'Date format',
    'admin-settings.locale.time_format' => 'Time format',
    'admin-settings.locale.default_language' => 'Default language',
    'admin-settings.locale.fallback_locale' => 'Fallback locale',

    // Theme settings
    'admin-settings.theme.active' => 'Active theme',
    'admin-settings.theme.active_theme_info' => 'Active theme information',
    'admin-settings.theme.name' => 'Name',
    'admin-settings.theme.version' => 'Version',
    'admin-settings.theme.author' => 'Author',
    'admin-settings.theme.description' => 'Description',

    // Content settings
    'admin-settings.content.format' => 'Content format',
    'admin-settings.content.posts_per_page' => 'Posts per page',

    // Modules settings
    'admin-settings.modules.core_modules' => 'Core Modules',
    'admin-settings.modules.other_modules' => 'Other Modules',

    // Security settings
    'admin-settings.security.password_hash_algo' => 'Password hash algorithm',
    'admin-settings.security.csrf_token_name' => 'CSRF token name',

    // Session settings
    'admin-settings.session.name' => 'Session name',
    'admin-settings.session.lifetime' => 'Session lifetime (seconds)',
    'admin-settings.session.lifetime.help' => 'Session duration in seconds. 0 means until browser closes.',
    'admin-settings.session.cookie_secure' => 'Secure cookie flag',
    'admin-settings.session.cookie_secure.help' => 'Auto detects HTTPS. Always secure requires HTTPS. Never secure is insecure.',
    'admin-settings.session.cookie_httponly' => 'HttpOnly cookie flag',
    'admin-settings.session.cookie_httponly.help' => 'Prevents JavaScript access to session cookies (recommended for security).',
    'admin-settings.session.cookie_samesite' => 'SameSite cookie attribute',
    'admin-settings.session.cookie_samesite.help' => 'Controls cross-site cookie behavior. Lax is recommended. Requires PHP 7.3+.',
    'admin-settings.session.cookie_path' => 'Cookie path',
    'admin-settings.session.cookie_path.help' => 'Path where cookie is valid. Use / for entire site.',
    'admin-settings.session.cookie_domain' => 'Cookie domain',
    'admin-settings.session.cookie_domain.help' => 'Domain where cookie is valid. Leave empty for current domain.',

    // Logging settings
    'admin-settings.logging.level' => 'Log level',
    'admin-settings.logging.retention_days' => 'Retention days',

    // Proxy settings
    'admin-settings.proxy.trusted_proxies' => 'Trusted proxies',
    'admin-settings.proxy.trusted_proxies.help' => 'One IP or CIDR per line.',

    // Performance settings
    'admin-settings.performance.gzip_compression' => 'Enable gzip compression',
    'admin-settings.performance.gzip_compression.help' => 'Compress HTML output to reduce bandwidth. Best configured at web server level (Apache/Nginx).',

    // Debug settings
    'admin-settings.debug.enabled' => 'Enable debug mode',
);
