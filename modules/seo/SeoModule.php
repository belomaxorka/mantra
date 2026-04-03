<?php declare(strict_types=1);

/**
 * SEO Module - Example of extensibility features
 *
 * Demonstrates:
 * - Hooking into page rendering
 * - Adding meta tags to <head>
 * - Modifying page data
 * - Providing partials
 * - Using module settings (settings.schema.php)
 */

use Module\Module;

class SeoModule extends Module
{

    public function init(): void
    {
        // Hook into theme head to add meta tags
        $this->hook('theme.head', [$this, 'addMetaTags']);

        // Hook into page data to add SEO fields
        $this->hook('page.single.data', [$this, 'addSeoData']);
        $this->hook('post.single.data', [$this, 'addSeoData']);
        $this->hook('product.single.data', [$this, 'addSeoData']);

    }

    /**
     * Add meta tags to <head>
     */
    public function addMetaTags($content)
    {
        $request = app()->request();
        $path = $request->path();

        // Get settings
        $settings = $this->settings();

        // Get current page/post data from request
        $title = config('site.name', MANTRA_PROJECT_INFO['name']);
        $description = $settings->get('meta.default_description', 'A powerful flat-file CMS');
        $keywords = $settings->get('meta.default_keywords', '');

        // Open Graph
        $ogImage = $settings->get('og.default_image', '');
        if (!str_starts_with($ogImage, 'http')) {
            $ogImage = base_url($ogImage);
        }
        $ogSiteName = $settings->get('og.site_name', '');
        if (empty($ogSiteName)) {
            $ogSiteName = config('site.name', MANTRA_PROJECT_INFO['name']);
        }

        // Twitter
        $twitterCard = $settings->get('twitter.card_type', 'summary_large_image');
        $twitterSite = $settings->get('twitter.site', '');
        $twitterCreator = $settings->get('twitter.creator', '');

        // Robots
        $robots = $settings->get('robots.default', 'index,follow');

        // Build meta tags
        $meta = [];

        // Basic SEO
        $meta[] = '<meta name="description" content="' . e($description) . '">';
        if (!empty($keywords)) {
            $meta[] = '<meta name="keywords" content="' . e($keywords) . '">';
        }
        $meta[] = '<meta name="robots" content="' . e($robots) . '">';
        $meta[] = '<link rel="canonical" href="' . e(base_url($path)) . '">';

        // Open Graph
        $meta[] = '<meta property="og:title" content="' . e($title) . '">';
        $meta[] = '<meta property="og:description" content="' . e($description) . '">';
        $meta[] = '<meta property="og:image" content="' . e($ogImage) . '">';
        $meta[] = '<meta property="og:url" content="' . e(base_url($path)) . '">';
        $meta[] = '<meta property="og:type" content="website">';
        $meta[] = '<meta property="og:site_name" content="' . e($ogSiteName) . '">';

        // Twitter Card
        $meta[] = '<meta name="twitter:card" content="' . e($twitterCard) . '">';
        $meta[] = '<meta name="twitter:title" content="' . e($title) . '">';
        $meta[] = '<meta name="twitter:description" content="' . e($description) . '">';
        $meta[] = '<meta name="twitter:image" content="' . e($ogImage) . '">';
        if (!empty($twitterSite)) {
            $meta[] = '<meta name="twitter:site" content="' . e($twitterSite) . '">';
        }
        if (!empty($twitterCreator)) {
            $meta[] = '<meta name="twitter:creator" content="' . e($twitterCreator) . '">';
        }

        return $content . "\n    " . implode("\n    ", $meta);
    }

    /**
     * Add SEO data to page/post/product view
     */
    public function addSeoData($data)
    {
        $settings = $this->settings();

        // Check if breadcrumbs are enabled
        if (!$settings->get('breadcrumbs.enabled', true)) {
            return $data;
        }

        // Get home text from settings
        $homeText = $settings->get('breadcrumbs.home_text', 'Home');

        // Add breadcrumbs data
        $breadcrumbs = [
            ['title' => $homeText, 'url' => base_url()],
        ];

        if (isset($data['page'])) {
            $breadcrumbs[] = [
                'title' => $data['page']['title'],
                'url' => null,
            ];
        } elseif (isset($data['post'])) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => base_url('/blog')];
            $breadcrumbs[] = [
                'title' => $data['post']['title'],
                'url' => null,
            ];
        }

        $data['breadcrumbs'] = $breadcrumbs;
        return $data;
    }

}
