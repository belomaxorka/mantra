<?php
/**
 * SEO Module Settings Schema
 */
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'General SEO',
            'fields' => array(
                array(
                    'path' => 'meta.default_description',
                    'type' => 'textarea',
                    'title' => 'Default Meta Description',
                    'help' => 'Default description for pages without custom meta description',
                    'default' => 'A powerful flat-file CMS built with PHP'
                ),
                array(
                    'path' => 'meta.default_keywords',
                    'type' => 'text',
                    'title' => 'Default Keywords',
                    'help' => 'Comma-separated keywords for SEO',
                    'default' => 'cms, php, flat-file'
                ),
            )
        ),
        array(
            'id' => 'opengraph',
            'title' => 'Open Graph',
            'fields' => array(
                array(
                    'path' => 'og.default_image',
                    'type' => 'text',
                    'title' => 'Default OG Image URL',
                    'help' => 'Full URL or path to default Open Graph image (1200x630px recommended)',
                    'default' => '/themes/default/assets/images/og-image.jpg'
                ),
                array(
                    'path' => 'og.site_name',
                    'type' => 'text',
                    'title' => 'Site Name',
                    'help' => 'Site name for Open Graph (leave empty to use site.name from config)',
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'twitter',
            'title' => 'Twitter Card',
            'fields' => array(
                array(
                    'path' => 'twitter.card_type',
                    'type' => 'select',
                    'title' => 'Card Type',
                    'help' => 'Twitter card type',
                    'default' => 'summary_large_image',
                    'options' => array(
                        'summary' => 'Summary',
                        'summary_large_image' => 'Summary with Large Image',
                        'app' => 'App',
                        'player' => 'Player'
                    )
                ),
                array(
                    'path' => 'twitter.site',
                    'type' => 'text',
                    'title' => 'Twitter Site Handle',
                    'help' => 'Twitter username for the website (e.g., @yoursite)',
                    'default' => ''
                ),
                array(
                    'path' => 'twitter.creator',
                    'type' => 'text',
                    'title' => 'Twitter Creator Handle',
                    'help' => 'Default Twitter username for content creator (e.g., @author)',
                    'default' => ''
                ),
            )
        ),
        array(
            'id' => 'advanced',
            'title' => 'Advanced',
            'fields' => array(
                array(
                    'path' => 'robots.default',
                    'type' => 'text',
                    'title' => 'Default Robots Meta',
                    'help' => 'Default robots meta tag value (e.g., index,follow)',
                    'default' => 'index,follow'
                ),
                array(
                    'path' => 'breadcrumbs.enabled',
                    'type' => 'toggle',
                    'title' => 'Enable Breadcrumbs',
                    'help' => 'Show breadcrumb navigation on pages',
                    'default' => true
                ),
                array(
                    'path' => 'breadcrumbs.home_text',
                    'type' => 'text',
                    'title' => 'Breadcrumb Home Text',
                    'help' => 'Text for home link in breadcrumbs',
                    'default' => 'Home'
                ),
            )
        ),
    )
);
