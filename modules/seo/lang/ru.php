<?php declare(strict_types=1);

return [
    // Module name
    'seo.name' => 'SEO',

    // Settings tabs
    'seo.settings.general' => 'Основные SEO',
    'seo.settings.opengraph' => 'Open Graph',
    'seo.settings.twitter' => 'Twitter Card',
    'seo.settings.advanced' => 'Дополнительно',

    // General SEO
    'seo.settings.meta.default_description' => 'Meta описание по умолчанию',
    'seo.settings.meta.default_description.help' => 'Описание по умолчанию для страниц без собственного meta описания',
    'seo.settings.meta.default_keywords' => 'Ключевые слова по умолчанию',
    'seo.settings.meta.default_keywords.help' => 'Ключевые слова через запятую для SEO',

    // Open Graph
    'seo.settings.og.default_image' => 'URL изображения OG по умолчанию',
    'seo.settings.og.default_image.help' => 'Полный URL или путь к изображению Open Graph по умолчанию (рекомендуется 1200x630px)',
    'seo.settings.og.site_name' => 'Название сайта',
    'seo.settings.og.site_name.help' => 'Название сайта для Open Graph (оставьте пустым для использования site.name из конфига)',

    // Twitter Card
    'seo.settings.twitter.card_type' => 'Тип карточки',
    'seo.settings.twitter.card_type.help' => 'Тип Twitter карточки',
    'seo.settings.twitter.card_type.summary' => 'Краткая',
    'seo.settings.twitter.card_type.summary_large_image' => 'Краткая с большим изображением',
    'seo.settings.twitter.card_type.app' => 'Приложение',
    'seo.settings.twitter.card_type.player' => 'Плеер',
    'seo.settings.twitter.site' => 'Twitter аккаунт сайта',
    'seo.settings.twitter.site.help' => 'Имя пользователя Twitter для сайта (например, @yoursite)',
    'seo.settings.twitter.creator' => 'Twitter аккаунт автора',
    'seo.settings.twitter.creator.help' => 'Имя пользователя Twitter автора контента по умолчанию (например, @author)',

    // Advanced
    'seo.settings.robots.default' => 'Robots meta по умолчанию',
    'seo.settings.robots.default.help' => 'Значение robots meta тега по умолчанию (например, index,follow)',
    'seo.settings.breadcrumbs.enabled' => 'Включить хлебные крошки',
    'seo.settings.breadcrumbs.enabled.help' => 'Показывать навигационные хлебные крошки на страницах',
    'seo.settings.breadcrumbs.home_text' => 'Текст ссылки "Главная"',
    'seo.settings.breadcrumbs.home_text.help' => 'Текст для ссылки на главную страницу в хлебных крошках',
];
