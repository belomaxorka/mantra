<?php
/**
 * View helpers
 */

/**
 * Get shared view instance or render template
 * @param string|null $template Template name
 * @param array $data Template data
 * @return View|string
 */
function view($template = null, $data = array())
{
    static $view = null;

    if ($view === null) {
        $view = new View();
    }

    if ($template === null) {
        return $view;
    }

    return $view->render($template, $data);
}

/**
 * Render a template partial (reusable fragment without layout)
 *
 * @param string $name Partial name (e.g., "sidebar", "seo:breadcrumbs")
 * @param array $params Parameters to extract into partial scope
 * @return string Rendered HTML
 */
function partial($name, $params = array())
{
    return view()->partial($name, $params);
}

/**
 * Get base URL
 */
function base_url($path = '')
{
    $siteUrl = config('site.url', '');

    // Normalize both forward and back slashes to avoid URLs like "//admin" or "\\admin".
    if ($siteUrl === '') {
        return '/' . ltrim($path, "/\\");
    }
    return rtrim($siteUrl, "/\\") . '/' . ltrim($path, "/\\");
}
