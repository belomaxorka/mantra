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
 * Render widget/component
 *
 * @param string $name Widget name (e.g., "sidebar", "module:widget")
 * @param array $params Parameters to pass to widget
 * @return string Rendered widget HTML
 */
function widget($name, $params = array())
{
    return view()->widget($name, $params);
}

/**
 * Get base URL
 */
function base_url($path = '')
{
    $siteUrl = config('site.url');
    if (!$siteUrl) {
        $app = Application::getInstance();
        $siteUrl = $app->config('site.url');
    }

    if (!$siteUrl) {
        $siteUrl = '';
    }

    // Normalize both forward and back slashes to avoid URLs like "//admin" or "\\admin".
    if ($siteUrl === '') {
        return '/' . ltrim($path, "/\\");
    }
    return rtrim($siteUrl, "/\\") . '/' . ltrim($path, "/\\");
}
