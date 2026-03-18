<?php
/**
 * SEO Module Settings Schema
 */
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'seo.settings.general',
            'fields' => array(
                array(
                    'path' => 'meta.default_description',
                    'type' => 'textarea',
                    'title' => 'seo.settings.meta.default_description',
                    'help' => 'seo.settings.meta.default_description.help',
                    'default' => 'A powerful flat-file CMS built with PHP'
                ),
                array(
                    'path' => 'meta.default_keywords',
                    'type' => 'text',
                    'title' => 'seo.settings.meta.default_keywords',
                    'help' => 'seo.settings.meta.default_keywords.help',
                    'default' => 'cms, php, flat-file'
                ),
            )
        ),
        array(
            'id' => 'opengraph',
            'title' => 'seo.settings.opengraph',
            'fields' => array(
                array(
                    'path' => 'og.default_image',
                    'type' => 'text',
                    'title' => 'seo.settings.og.default_image',
                    'help' => 'seo.settings.og.default_image.help',
                    'default' => '/' . basename(MANTRA_THEMES) . '/default/assets/images/og-image.jpg'
                ),
                array(
                    'path' => 'og.site_name',
                    'type' => 'text',
                    'title' => 'seo.settings.og.site_name',
                    'help' => 'seo.settings.og.site_name.help',
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'twitter',
            'title' => 'seo.settings.twitter',
            'fields' => array(
                array(
                    'path' => 'twitter.card_type',
                    'type' => 'select',
                    'title' => 'seo.settings.twitter.card_type',
                    'help' => 'seo.settings.twitter.card_type.help',
                    'default' => 'summary_large_image',
                    'options' => array(
                        'summary' => 'seo.settings.twitter.card_type.summary',
                        'summary_large_image' => 'seo.settings.twitter.card_type.summary_large_image',
                        'app' => 'seo.settings.twitter.card_type.app',
                        'player' => 'seo.settings.twitter.card_type.player'
                    )
                ),
                array(
                    'path' => 'twitter.site',
                    'type' => 'text',
                    'title' => 'seo.settings.twitter.site',
                    'help' => 'seo.settings.twitter.site.help',
                    'default' => ''
                ),
                array(
                    'path' => 'twitter.creator',
                    'type' => 'text',
                    'title' => 'seo.settings.twitter.creator',
                    'help' => 'seo.settings.twitter.creator.help',
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'advanced',
            'title' => 'seo.settings.advanced',
            'fields' => array(
                array(
                    'path' => 'robots.default',
                    'type' => 'text',
                    'title' => 'seo.settings.robots.default',
                    'help' => 'seo.settings.robots.default.help',
                    'default' => 'index,follow'
                ),
                array(
                    'path' => 'breadcrumbs.enabled',
                    'type' => 'toggle',
                    'title' => 'seo.settings.breadcrumbs.enabled',
                    'help' => 'seo.settings.breadcrumbs.enabled.help',
                    'default' => true
                ),
                array(
                    'path' => 'breadcrumbs.home_text',
                    'type' => 'text',
                    'title' => 'seo.settings.breadcrumbs.home_text',
                    'help' => 'seo.settings.breadcrumbs.home_text.help',
                    'default' => 'Home'
                ),
            )
        ),
    )
);
