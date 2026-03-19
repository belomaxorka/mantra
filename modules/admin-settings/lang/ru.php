<?php

return array(
    // Module name
    'admin-settings.name' => 'Настройки',

    // Settings page
    'admin-settings.title' => 'Настройки',
    'admin-settings.general' => 'Общие',
    'admin-settings.modules' => 'Модули',
    'admin-settings.save' => 'Сохранить настройки',
    'admin-settings.saved' => 'Настройки успешно сохранены',
    'admin-settings.error' => 'Ошибка при сохранении настроек',

    // Tabs
    'admin-settings.tab.general' => 'Общие',
    'admin-settings.tab.modules' => 'Модули',
    'admin-settings.tab.advanced' => 'Дополнительно',

    // Module management
    'admin-settings.modules.enabled' => 'Включенные модули',
    'admin-settings.modules.available' => 'Доступные модули',
    'admin-settings.modules.enable' => 'Включить',
    'admin-settings.modules.disable' => 'Отключить',

    // Settings groups
    'admin-settings.group.site' => 'Сайт',
    'admin-settings.group.locale' => 'Локализация',
    'admin-settings.group.theme' => 'Тема',
    'admin-settings.group.content' => 'Контент',
    'admin-settings.group.modules' => 'Модули',
    'admin-settings.group.security' => 'Безопасность',
    'admin-settings.group.session' => 'Сессия',
    'admin-settings.group.logging' => 'Логирование',
    'admin-settings.group.proxy' => 'Прокси',
    'admin-settings.group.performance' => 'Производительность',
    'admin-settings.group.debug' => 'Отладка',

    // Site settings
    'admin-settings.site.name' => 'Название сайта',
    'admin-settings.site.url' => 'URL сайта',

    // Locale settings
    'admin-settings.locale.timezone' => 'Часовой пояс',
    'admin-settings.locale.default_language' => 'Язык по умолчанию',
    'admin-settings.locale.fallback_locale' => 'Резервная локаль',

    // Theme settings
    'admin-settings.theme.active' => 'Активная тема',
    'admin-settings.theme.active_theme_info' => 'Информация об активной теме',
    'admin-settings.theme.name' => 'Название',
    'admin-settings.theme.version' => 'Версия',
    'admin-settings.theme.author' => 'Автор',
    'admin-settings.theme.description' => 'Описание',

    // Content settings
    'admin-settings.content.format' => 'Формат контента',
    'admin-settings.content.posts_per_page' => 'Постов на странице',

    // Modules settings
    'admin-settings.modules.enabled_modules' => 'Включенные модули',
    'admin-settings.modules.core_modules' => 'Основные модули',
    'admin-settings.modules.other_modules' => 'Другие модули',

    // Security settings
    'admin-settings.security.password_hash_algo' => 'Алгоритм хеширования паролей',
    'admin-settings.security.csrf_token_name' => 'Имя CSRF токена',

    // Session settings
    'admin-settings.session.name' => 'Имя сессии',
    'admin-settings.session.lifetime' => 'Время жизни сессии (секунды)',
    'admin-settings.session.lifetime.help' => 'Длительность сессии в секундах. 0 означает до закрытия браузера.',
    'admin-settings.session.cookie_secure' => 'Флаг Secure для cookie',
    'admin-settings.session.cookie_secure.help' => 'Auto определяет HTTPS автоматически. Always требует HTTPS. Never небезопасно.',
    'admin-settings.session.cookie_httponly' => 'Флаг HttpOnly для cookie',
    'admin-settings.session.cookie_httponly.help' => 'Запрещает доступ к cookie сессии из JavaScript (рекомендуется для безопасности).',
    'admin-settings.session.cookie_samesite' => 'Атрибут SameSite для cookie',
    'admin-settings.session.cookie_samesite.help' => 'Контролирует поведение cookie между сайтами. Рекомендуется Lax. Требует PHP 7.3+.',
    'admin-settings.session.cookie_path' => 'Путь cookie',
    'admin-settings.session.cookie_path.help' => 'Путь, где cookie действителен. Используйте / для всего сайта.',
    'admin-settings.session.cookie_domain' => 'Домен cookie',
    'admin-settings.session.cookie_domain.help' => 'Домен, где cookie действителен. Оставьте пустым для текущего домена.',

    // Logging settings
    'admin-settings.logging.level' => 'Уровень логирования',
    'admin-settings.logging.retention_days' => 'Дней хранения',

    // Proxy settings
    'admin-settings.proxy.trusted_proxies' => 'Доверенные прокси',
    'admin-settings.proxy.trusted_proxies.help' => 'Один IP или CIDR на строку.',

    // Performance settings
    'admin-settings.performance.gzip_compression' => 'Включить gzip сжатие',
    'admin-settings.performance.gzip_compression.help' => 'Сжимать HTML вывод для уменьшения трафика. Лучше настраивать на уровне веб-сервера (Apache/Nginx).',

    // Debug settings
    'admin-settings.debug.enabled' => 'Включить режим отладки',

    // Datetime strings
    'datetime.ago' => '%s назад',
    'datetime.in_future' => 'через %s',
    'datetime.just_now' => 'только что',

    // Time units (Russian has 3 forms: one/few/many)
    'datetime.second.one' => 'секунду',
    'datetime.second.few' => 'секунды',
    'datetime.second.many' => 'секунд',
    'datetime.minute.one' => 'минуту',
    'datetime.minute.few' => 'минуты',
    'datetime.minute.many' => 'минут',
    'datetime.hour.one' => 'час',
    'datetime.hour.few' => 'часа',
    'datetime.hour.many' => 'часов',
    'datetime.day.one' => 'день',
    'datetime.day.few' => 'дня',
    'datetime.day.many' => 'дней',
    'datetime.month.one' => 'месяц',
    'datetime.month.few' => 'месяца',
    'datetime.month.many' => 'месяцев',
    'datetime.year.one' => 'год',
    'datetime.year.few' => 'года',
    'datetime.year.many' => 'лет',
);
