<?php
/**
 * SEO Module Settings Schema
 */
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => array('key' => 'seo.settings.general', 'fallback' => 'General SEO'),
            'fields' => array(
                array(
                    'path' => 'meta.default_description',
                    'type' => 'textarea',
                    'title' => array('key' => 'seo.settings.meta.default_description', 'fallback' => 'Default Meta Description'),
                    'help' => array('key' => 'seo.settings.meta.default_description.help', 'fallback' => 'Default description for pages without custom meta description'),
                    'default' => 'A powerful flat-file CMS built with PHP'
                ),
                array(
                    'path' => 'meta.default_keywords',
                    'type' => 'text',
                    'title' => array('key' => 'seo.settings.meta.default_keywords', 'fallback' => 'Default Keywords'),
                    'help' => array('key' => 'seo.settings.meta.default_keywords.help', 'fallback' => 'Comma-separated keywords for SEO'),
                    'default' => 'cms, php, flat-file'
                ),
            )
        ),
        array(
            'id' => 'opengraph',
            'title' => array('key' => 'seo.settings.opengraph', 'fallback' => 'Open Graph'),
            'fields' => array(
                array(
                    'path' => 'og.default_image',
                    'type' => 'text',
                    'title' => array('key' => 'seo.settings.og.default_image', 'fallback' => 'Default OG Image URL'),
                    'help' => array('key' => 'seo.settings.og.default_image.help', 'fallback' => 'Full URL or path to default Open Graph image (1200x630px recommended)'),
                    'default' => '/themes/default/assets/images/og-image.jpg'
                ),
                array(
                    'path' => 'og.site_name',
                    'type' => 'text',
                    'title' => array('key' => 'seo.settings.og.site_name', 'fallback' => 'Site Name'),
                    'help' => array('key' => 'seo.settings.og.site_name.help', 'fallback' => 'Site name for Open Graph (leave empty to use site.name from config)'),
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'twitter',
            'title' => array('key' => 'seo.settings.twitter', 'fallback' => 'Twitter Card'),
            'fields' => array(
                array(
                    'path' => 'twitter.card_type',
                    'type' => 'select',
                    'title' => array('key' => 'seo.settings.twitter.card_type', 'fallback' => 'Card Type'),
                    'help' => array('key' => 'seo.settings.twitter.card_type.help', 'fallback' => 'Twitter card type'),
                    'default' => 'summary_large_image',
                    'options' => array(
                        'summary' => array('key' => 'seo.settings.twitter.card_type.summary', 'fallback' => 'Summary'),
                        'summary_large_image' => array('key' => 'seo.settings.twitter.card_type.summary_large_image', 'fallback' => 'Summary with Large Image'),
                        'app' => array('key' => 'seo.settings.twitter.card_type.app', 'fallback' => 'App'),
                        'player' => array('key' => 'seo.settings.twitter.card_type.player', 'fallback' => 'Player')
                    )
                ),
                array(
                    'path' => 'twitter.site',
                    'type' => 'text',
                    'title' => array('key' => 'seo.settings.twitter.site', 'fallback' => 'Twitter Site Handle'),
                    'help' => array('key' => 'seo.settings.twitter.site.help', 'fallback' => 'Twitter username for the website (e.g., @yoursite)'),
                    'default' => ''
                ),
                array(
                    'path' => 'twitter.creator',
                    'type' => 'text',
                    'title' => array('key' => 'seo.settings.twitter.creator', 'fallback' => 'Twitter Creator Handle'),
                    'help' => array('key' => 'seo.settings.twitter.creator.help', 'fallback' => 'Default Twitter username for content creator (e.g., @author)'),
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'advanced',
            'title' => array('key' => 'seo.settings.advanced', 'fallback' => 'Advanced'),
            'fields' => array(
                array(
                    'path' => 'robots.default',
                    'type' => 'text',
                    'title' => array('key' => 'seo.settings.robots.default', 'fallback' => 'Default Robots Meta'),
                    'help' => array('key' => 'seo.settings.robots.default.help', 'fallback' => 'Default robots meta tag value (e.g., index,follow)'),
                    'default' => 'index,follow'
                ),
                array(
                    'path' => 'breadcrumbs.enabled',
                    'type' => 'toggle',
                    'title' => array('key' => 'seo.settings.breadcrumbs.enabled', 'fallback' => 'Enable Breadcrumbs'),
                    'help' => array('key' => 'seo.settings.breadcrumbs.enabled.help', 'fallback' => 'Show breadcrumb navigation on pages'),
                    'default' => true
                ),
                array(
                    'path' => 'breadcrumbs.home_text',
                    'type' => 'text',
                    'title' => array('key' => 'seo.settings.breadcrumbs.home_text', 'fallback' => 'Breadcrumb Home Text'),
                    'help' => array('key' => 'seo.settings.breadcrumbs.home_text.help', 'fallback' => 'Text for home link in breadcrumbs'),
                    'default' => 'Home'
                ),
            )
        ),
    )
);
