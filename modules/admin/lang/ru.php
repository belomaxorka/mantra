<?php

return array(
    'admin.settings.title' => 'Настройки',
    'admin.settings.general' => 'Основные',
    'admin.settings.save' => 'Сохранить',

    'admin.settings.group.site' => 'Сайт',
    'admin.settings.group.locale' => 'Локаль',
    'admin.settings.group.theme' => 'Тема',
    'admin.settings.group.content' => 'Контент',
    'admin.settings.group.modules' => 'Модули',
    'admin.settings.group.security' => 'Безопасность',
    'admin.settings.group.session' => 'Сессия',
    'admin.settings.group.cache' => 'Кэш',
    'admin.settings.group.logging' => 'Логи',
    'admin.settings.group.proxy' => 'Прокси / CDN',
    'admin.settings.group.debug' => 'Отладка',
    'admin.settings.group.advanced' => 'Дополнительно',

    // Keys from Config::defaults()
    'admin.settings.site_name' => 'Название сайта',
    'admin.settings.site_url' => 'URL сайта',
    'admin.settings.timezone' => 'Часовой пояс',
    'admin.settings.default_language' => 'Язык по умолчанию',
    'admin.settings.fallback_locale' => 'Fallback-локаль',

    'admin.settings.debug' => 'Режим отладки',

    'admin.settings.log_level' => 'Уровень логирования',
    'admin.settings.log_retention_days' => 'Хранить логи (дней)',

    'admin.settings.cache_enabled' => 'Кэш включён',
    'admin.settings.cache_lifetime' => 'Время жизни кэша (сек)',

    'admin.settings.session_name' => 'Имя сессии',
    'admin.settings.session_lifetime' => 'Время жизни сессии (сек)',

    'admin.settings.password_hash_algo' => 'Алгоритм хеширования пароля',
    'admin.settings.csrf_token_name' => 'Имя CSRF токена',

    'admin.settings.trusted_proxies' => 'Доверенные прокси',
    'admin.settings.trusted_proxies.help' => 'Один IP/CIDR на строку. Заголовки прокси учитываются только если REMOTE_ADDR совпадает.',

    'admin.settings.content_format' => 'Формат контента',
    'admin.settings.posts_per_page' => 'Постов на страницу',

    'admin.settings.active_theme' => 'Активная тема',

    'admin.settings.enabled_modules' => 'Включённые модули',
    'admin.settings.enabled_modules.help' => 'Один ID модуля на строку.',
);
