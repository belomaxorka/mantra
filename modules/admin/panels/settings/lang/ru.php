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

    // Module management
    'admin-settings.modules.enabled' => 'Включенные модули',
    'admin-settings.modules.available' => 'Доступные модули',
    'admin-settings.modules.enable' => 'Включить',
    'admin-settings.modules.disable' => 'Отключить',

    // Settings groups
    'admin-settings.group.site' => 'Сайт',
    'admin-settings.group.locale' => 'Локализация',
    'admin-settings.group.theme' => 'Тема',
    'admin-settings.group.appearance' => 'Внешний вид',
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
    'admin-settings.locale.date_format' => 'Формат даты',
    'admin-settings.locale.time_format' => 'Формат времени',
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

    // Appearance settings
    'admin-settings.appearance.accent_color' => 'Акцентный цвет',
    'admin-settings.appearance.accent_color.help' => 'Выберите основной акцентный цвет панели администрирования.',
    'admin-settings.appearance.preset.indigo' => 'Индиго (по умолчанию)',
    'admin-settings.appearance.preset.blue' => 'Синий',
    'admin-settings.appearance.preset.sky' => 'Небесный',
    'admin-settings.appearance.preset.teal' => 'Бирюзовый',
    'admin-settings.appearance.preset.emerald' => 'Изумрудный',
    'admin-settings.appearance.preset.amber' => 'Янтарный',
    'admin-settings.appearance.preset.orange' => 'Оранжевый',
    'admin-settings.appearance.preset.rose' => 'Розовый',
    'admin-settings.appearance.preset.violet' => 'Фиолетовый',
    'admin-settings.appearance.preset.slate' => 'Серый',
    'admin-settings.appearance.sidebar_color' => 'Цвет сайдбара',
    'admin-settings.appearance.sidebar_color.help' => 'Выберите цветовую схему боковой панели.',
    'admin-settings.appearance.sidebar.dark' => 'Тёмный (по умолчанию)',
    'admin-settings.appearance.sidebar.midnight' => 'Полуночный',
    'admin-settings.appearance.sidebar.charcoal' => 'Угольный',
    'admin-settings.appearance.sidebar.ocean' => 'Океан',
    'admin-settings.appearance.sidebar.forest' => 'Лесной',
    'admin-settings.appearance.sidebar.plum' => 'Сливовый',
    'admin-settings.appearance.sidebar.light' => 'Светлый',
    'admin-settings.appearance.font' => 'Шрифт',
    'admin-settings.appearance.font.help' => 'Выберите шрифт панели администрирования.',
    'admin-settings.appearance.font.inter' => 'Inter (по умолчанию)',
    'admin-settings.appearance.font.system' => 'Системный',
    'admin-settings.appearance.font.roboto' => 'Roboto',
    'admin-settings.appearance.font.nunito' => 'Nunito',
    'admin-settings.appearance.font.source-sans' => 'Source Sans',
    'admin-settings.appearance.font.jetbrains-mono' => 'JetBrains Mono',
    'admin-settings.appearance.theme' => 'Тема',
    'admin-settings.appearance.theme.light' => 'Светлая',
    'admin-settings.appearance.theme.dark' => 'Тёмная',

    // Debug settings
    'admin-settings.debug.enabled' => 'Включить режим отладки',
);
