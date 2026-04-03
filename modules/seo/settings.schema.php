<?php declare(strict_types=1);

/**
 * SEO Module Settings Schema
 */
return [
    'version' => 1,
    'tabs' => [
        [
            'id' => 'general',
            'title' => 'seo.settings.general',
            'fields' => [
                [
                    'path' => 'meta.default_description',
                    'type' => 'textarea',
                    'title' => 'seo.settings.meta.default_description',
                    'help' => 'seo.settings.meta.default_description.help',
                    'default' => 'A powerful flat-file CMS built with PHP',
                ],
                [
                    'path' => 'meta.default_keywords',
                    'type' => 'text',
                    'title' => 'seo.settings.meta.default_keywords',
                    'help' => 'seo.settings.meta.default_keywords.help',
                    'default' => 'cms, php, flat-file',
                ],
            ],
        ],
        [
            'id' => 'opengraph',
            'title' => 'seo.settings.opengraph',
            'fields' => [
                [
                    'path' => 'og.default_image',
                    'type' => 'text',
                    'title' => 'seo.settings.og.default_image',
                    'help' => 'seo.settings.og.default_image.help',
                    'default' => '/' . basename(MANTRA_CORE) . '/assets/images/og-image.jpg',
                ],
                [
                    'path' => 'og.site_name',
                    'type' => 'text',
                    'title' => 'seo.settings.og.site_name',
                    'help' => 'seo.settings.og.site_name.help',
                    'default' => '',
                ],
            ],
        ],
        [
            'id' => 'twitter',
            'title' => 'seo.settings.twitter',
            'fields' => [
                [
                    'path' => 'twitter.card_type',
                    'type' => 'select',
                    'title' => 'seo.settings.twitter.card_type',
                    'help' => 'seo.settings.twitter.card_type.help',
                    'default' => 'summary_large_image',
                    'options' => [
                        'summary' => 'seo.settings.twitter.card_type.summary',
                        'summary_large_image' => 'seo.settings.twitter.card_type.summary_large_image',
                        'app' => 'seo.settings.twitter.card_type.app',
                        'player' => 'seo.settings.twitter.card_type.player',
                    ],
                ],
                [
                    'path' => 'twitter.site',
                    'type' => 'text',
                    'title' => 'seo.settings.twitter.site',
                    'help' => 'seo.settings.twitter.site.help',
                    'default' => '',
                ],
                [
                    'path' => 'twitter.creator',
                    'type' => 'text',
                    'title' => 'seo.settings.twitter.creator',
                    'help' => 'seo.settings.twitter.creator.help',
                    'default' => '',
                ],
            ],
        ],
        [
            'id' => 'advanced',
            'title' => 'seo.settings.advanced',
            'fields' => [
                [
                    'path' => 'robots.default',
                    'type' => 'text',
                    'title' => 'seo.settings.robots.default',
                    'help' => 'seo.settings.robots.default.help',
                    'default' => 'index,follow',
                ],
                [
                    'path' => 'breadcrumbs.enabled',
                    'type' => 'toggle',
                    'title' => 'seo.settings.breadcrumbs.enabled',
                    'help' => 'seo.settings.breadcrumbs.enabled.help',
                    'default' => true,
                ],
                [
                    'path' => 'breadcrumbs.home_text',
                    'type' => 'text',
                    'title' => 'seo.settings.breadcrumbs.home_text',
                    'help' => 'seo.settings.breadcrumbs.home_text.help',
                    'default' => 'Home',
                ],
            ],
        ],
    ],
];
