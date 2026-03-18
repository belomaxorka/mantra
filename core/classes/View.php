<?php
/**
 * View - Template rendering engine
 * Simple but extensible template system
 */

class View {
    private $data = array();
    private $themePath = '';
    
    public function __construct() {
        $app = Application::getInstance();
        $theme = $app->config('theme.active', 'default');
        $this->themePath = MANTRA_THEMES . '/' . $theme;
    }
    
    /**
     * Render a template
     *
     * Template resolution order:
     * 1. Theme template (allows theme to override): themes/{theme}/templates/{template}.php
     * 2. Explicit module syntax: "module:template" -> modules/{module}/views/{template}.php
     * 3. Smart fallback via _module parameter: modules/{_module}/views/{template}.php
     *
     * This allows modules to be self-contained while themes can optionally override.
     */
    public function render($template, $data = array()) {
        $this->data = $data;

        // Try theme template first (allows theme to override)
        $templatePath = $this->themePath . '/templates/' . $template . '.php';

        // Fallback to module template
        if (!file_exists($templatePath)) {
            // Template might be in format "module:template"
            if (strpos($template, ':') !== false) {
                list($module, $tpl) = explode(':', $template, 2);
                $templatePath = MANTRA_MODULES . '/' . $module . '/views/' . $tpl . '.php';
            } else {
                // Smart fallback: if module is specified in data, try module views
                if (isset($data['_module']) && !empty($data['_module'])) {
                    $modulePath = MANTRA_MODULES . '/' . $data['_module'] . '/views/' . $template . '.php';
                    if (file_exists($modulePath)) {
                        $templatePath = $modulePath;
                    }
                }
            }
        }

        if (!file_exists($templatePath)) {
            throw new Exception("Template not found: $template");
        }

        // Extract data to variables
        extract($this->data);

        // Start output buffering for content
        ob_start();

        // Include template
        include $templatePath;

        // Get content
        $content = ob_get_clean();

        // Apply filters
        $app = Application::getInstance();
        $content = $app->hooks()->fire('view.render', $content);

        // Wrap in layout if not a module template
        if (strpos($template, ':') === false) {
            $layoutPath = $this->themePath . '/templates/layout.php';
            if (file_exists($layoutPath)) {
                // Extract data again for layout
                extract($this->data);
                ob_start();
                include $layoutPath;
                $content = ob_get_clean();
            }
        }

        echo $content;
    }

    /**
     * Render widget/component (for embedding in templates)
     *
     * @param string $name Widget name in format "module:widget" or just "widget"
     * @param array $params Parameters to pass to widget
     * @return string Rendered widget HTML
     */
    public function widget($name, $params = array()) {
        $app = Application::getInstance();

        // Hook: allow modules to provide widgets
        $widgetData = array(
            'name' => $name,
            'params' => $params,
            'output' => ''
        );

        $widgetData = $app->hooks()->fire('widget.render', $widgetData);

        // If hook provided output, return it
        if (!empty($widgetData['output'])) {
            return $widgetData['output'];
        }

        // Try to load widget template
        if (strpos($name, ':') !== false) {
            // Module widget: "module:widget"
            list($module, $widget) = explode(':', $name, 2);
            $widgetPath = MANTRA_MODULES . '/' . $module . '/widgets/' . $widget . '.php';
        } else {
            // Theme widget
            $widgetPath = $this->themePath . '/widgets/' . $name . '.php';
        }

        if (file_exists($widgetPath)) {
            extract($params);
            ob_start();
            include $widgetPath;
            return ob_get_clean();
        }

        return '<!-- Widget not found: ' . htmlspecialchars($name) . ' -->';
    }
    
    /**
     * Render and return as string
     */
    public function fetch($template, $data = array()) {
        ob_start();
        $this->render($template, $data);
        return ob_get_clean();
    }
    
    /**
     * Escape HTML (alias: e)
     */
    public function escape($value) {
        if (is_array($value)) {
            return array_map(array($this, 'escape'), $value);
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Short alias for escape
     */
    public function e($value) {
        return $this->escape($value);
    }
    
    /**
     * Get asset URL
     */
    public function asset($path) {
        $app = Application::getInstance();
        $baseUrl = $app->config('site.url', '');
        return $baseUrl . '/' . basename(MANTRA_THEMES) . '/' . basename($this->themePath) . '/assets/' . ltrim($path, '/');
    }
}
