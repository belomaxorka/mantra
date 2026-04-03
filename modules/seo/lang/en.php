<?php declare(strict_types=1);

return [
    // Module name
    'seo.name' => 'SEO',

    // Settings tabs
    'seo.settings.general' => 'General SEO',
    'seo.settings.opengraph' => 'Open Graph',
    'seo.settings.twitter' => 'Twitter Card',
    'seo.settings.advanced' => 'Advanced',

    // General SEO
    'seo.settings.meta.default_description' => 'Default Meta Description',
    'seo.settings.meta.default_description.help' => 'Default description for pages without custom meta description',
    'seo.settings.meta.default_keywords' => 'Default Keywords',
    'seo.settings.meta.default_keywords.help' => 'Comma-separated keywords for SEO',

    // Open Graph
    'seo.settings.og.default_image' => 'Default OG Image URL',
    'seo.settings.og.default_image.help' => 'Full URL or path to default Open Graph image (1200x630px recommended)',
    'seo.settings.og.site_name' => 'Site Name',
    'seo.settings.og.site_name.help' => 'Site name for Open Graph (leave empty to use site.name from config)',

    // Twitter Card
    'seo.settings.twitter.card_type' => 'Card Type',
    'seo.settings.twitter.card_type.help' => 'Twitter card type',
    'seo.settings.twitter.card_type.summary' => 'Summary',
    'seo.settings.twitter.card_type.summary_large_image' => 'Summary with Large Image',
    'seo.settings.twitter.card_type.app' => 'App',
    'seo.settings.twitter.card_type.player' => 'Player',
    'seo.settings.twitter.site' => 'Twitter Site Handle',
    'seo.settings.twitter.site.help' => 'Twitter username for the website (e.g., @yoursite)',
    'seo.settings.twitter.creator' => 'Twitter Creator Handle',
    'seo.settings.twitter.creator.help' => 'Default Twitter username for content creator (e.g., @author)',

    // Advanced
    'seo.settings.robots.default' => 'Default Robots Meta',
    'seo.settings.robots.default.help' => 'Default robots meta tag value (e.g., index,follow)',
    'seo.settings.breadcrumbs.enabled' => 'Enable Breadcrumbs',
    'seo.settings.breadcrumbs.enabled.help' => 'Show breadcrumb navigation on pages',
    'seo.settings.breadcrumbs.home_text' => 'Breadcrumb Home Text',
    'seo.settings.breadcrumbs.home_text.help' => 'Text for home link in breadcrumbs',
];
