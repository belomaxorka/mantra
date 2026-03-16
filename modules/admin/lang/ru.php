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

    // Keys from Config::defaults() (dot-paths)
    'admin.settings.site.name' => 'Название сайта',
    'admin.settings.site.url' => 'URL сайта',

    'admin.settings.locale.timezone' => 'Часовой пояс',
    'admin.settings.locale.default_language' => 'Язык по умолчанию',
    'admin.settings.locale.fallback_locale' => 'Fallback-локаль',

    'admin.settings.debug.enabled' => 'Режим отладки',

    'admin.settings.logging.level' => 'Уровень логирования',
    'admin.settings.logging.retention_days' => 'Хранить логи (дней)',

    'admin.settings.cache.enabled' => 'Кэш включён',
    'admin.settings.cache.lifetime' => 'Время жизни кэша (сек)',

    'admin.settings.session.name' => 'Имя сессии',
    'admin.settings.session.lifetime' => 'Время жизни сессии (сек)',

    'admin.settings.security.password_hash_algo' => 'Алгоритм хеширования пароля',
    'admin.settings.security.csrf_token_name' => 'Имя CSRF токена',

    'admin.settings.proxy.trusted_proxies' => 'Доверенные прокси',
    'admin.settings.proxy.trusted_proxies.help' => 'Один IP/CIDR на строку. Заголовки прокси учитываются только если REMOTE_ADDR совпадает.',

    'admin.settings.content.format' => 'Формат контента',
    'admin.settings.content.posts_per_page' => 'Постов на страницу',

    'admin.settings.theme.active' => 'Активная тема',

    'admin.settings.modules.enabled' => 'Включённые модули',
    'admin.settings.modules.enabled.help' => 'Один ID модуля на строку.',

    'admin.settings.advanced' => 'Дополнительно',
    'admin.settings.advanced.help' => '',

    // Для security.password_hash_algo
    'admin.settings.security.password_hash_algo.help' => 'Используйте строковый идентификатор алгоритма, например PASSWORD_DEFAULT.',

    // Для site.url
    'admin.settings.site.url.help' => 'Базовый URL для генерации ссылок (например https://example.com).',

    // Список модулей (Основные настройки)
    'admin.modules.author' => 'Автор:',
    'admin.modules.homepage' => 'Сайт:',
    'admin.modules.settings' => 'Настройки',
    'admin.modules.delete' => 'Удалить',
    'admin.modules.delete_confirm' => 'Удалить этот модуль? Это удалит его файлы с сервера.',
);
