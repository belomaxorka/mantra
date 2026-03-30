<?php
/**
 * View - Template rendering engine
 * Simple but extensible template system
 */

class View {
    private $data = array();
    private $themePath = '';

    public function __construct() {
        $theme = config('theme.active', 'default');
        $this->themePath = MANTRA_THEMES . '/' . $theme;
    }

    /**
     * Render a template
     *
     * Template resolution order:
     * 1. Theme template (allows theme to override): themes/{theme}/templates/{template}.php
     * 2. Explicit module syntax: "module:template" -> modules/{module}/views/{template}.php
     * 3. Smart fallback via _module parameter: modules/{_module}/views/{template}.php
     */
    public function render($template, $data = array()) {
        echo $this->fetch($template, $data);
    }

    /**
     * Render template file with output buffering and error handling
     *
     * @param string $templatePath Path to template file
     * @param array $data Data to extract into template scope
     * @return string Rendered content
     */
    private function renderTemplate($templatePath, $data) {
        return $this->captureOutput(function() use ($templatePath, $data) {
            extract($data, EXTR_SKIP);
            include $templatePath;
        });
    }

    /**
     * Render layout with content
     *
     * @param string $layoutPath Path to layout file
     * @param array $data Data to extract into layout scope
     * @param string $content Rendered content to inject
     * @return string Rendered layout
     */
    private function renderLayout($layoutPath, $data, $content) {
        // Merge content into data to prevent extract() conflicts
        $layoutData = array_merge($data, array('content' => $content));

        return $this->captureOutput(function() use ($layoutPath, $layoutData) {
            extract($layoutData, EXTR_SKIP);
            include $layoutPath;
        });
    }

    /**
     * Capture output from callback with error handling
     *
     * @param callable $callback Function to execute
     * @return string Captured output
     * @throws Exception Re-throws any exception after cleaning buffer
     */
    private function captureOutput($callback) {
        $level = ob_get_level();
        ob_start();
        try {
            $callback();
            return ob_get_clean();
        } catch (Exception $e) {
            // Clean all buffers created by this call
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /**
     * Render a template partial (reusable fragment without layout wrapping)
     *
     * Resolution order:
     * 1. Theme partial: themes/{theme}/templates/partials/{name}.php
     * 2. Module partial: modules/{module}/views/partials/{partial}.php (via "module:partial" syntax)
     *
     * @param string $name Partial name, e.g. "sidebar" or "seo:breadcrumbs"
     * @param array $params Parameters to extract into partial scope
     * @return string Rendered HTML
     */
    public function partial($name, $params = array()) {
        $partialPath = $this->resolvePartialPath($name);

        if ($partialPath === null) {
            return '<!-- Partial not found: ' . htmlspecialchars($name) . ' -->';
        }

        try {
            return $this->captureOutput(function() use ($partialPath, $params) {
                extract($params, EXTR_SKIP);
                include $partialPath;
            });
        } catch (Exception $e) {
            // Don't throw - partials shouldn't break the page
            logger()->error('Partial render error', array(
                'partial' => $name,
                'exception' => $e
            ));
            return '<!-- Partial error: ' . htmlspecialchars($name) . ' -->';
        }
    }

    /**
     * Resolve partial file path
     *
     * @param string $name Partial name
     * @return string|null Resolved path or null if not found
     */
    private function resolvePartialPath($name) {
        if (str_contains($name, ':')) {
            // Module partial: "module:partial"
            list($module, $partial) = explode(':', $name, 2);

            // Theme can override module partials
            $themePath = $this->themePath . '/templates/partials/' . $module . '/' . $partial . '.php';
            if (file_exists($themePath)) {
                return $themePath;
            }

            $modulePath = MANTRA_MODULES . '/' . $module . '/views/partials/' . $partial . '.php';
            return file_exists($modulePath) ? $modulePath : null;
        }

        // Theme partial
        $path = $this->themePath . '/templates/partials/' . $name . '.php';
        return file_exists($path) ? $path : null;
    }

    /**
     * Render and return as string (without output)
     */
    public function fetch($template, $data = array()) {
        $this->data = $data;

        // Try theme template first (allows theme to override)
        $templatePath = $this->themePath . '/templates/' . $template . '.php';
        $isModuleTemplate = false;

        // Fallback to module template
        if (!file_exists($templatePath)) {
            // Template might be in format "module:template"
            if (str_contains($template, ':')) {
                list($module, $tpl) = explode(':', $template, 2);
                $templatePath = MANTRA_MODULES . '/' . $module . '/views/' . $tpl . '.php';
                $isModuleTemplate = true;
            } else {
                // Smart fallback: if module is specified in data, try module views
                if (isset($data['_module']) && !empty($data['_module'])) {
                    $modulePath = MANTRA_MODULES . '/' . $data['_module'] . '/views/' . $template . '.php';
                    if (file_exists($modulePath)) {
                        $templatePath = $modulePath;
                        $isModuleTemplate = true;
                    }
                }
            }
        }

        if (!file_exists($templatePath)) {
            throw new Exception("Template not found: $template");
        }

        // Render content with error handling
        $content = $this->renderTemplate($templatePath, $this->data);

        // Wrap in layout if not a module template
        if (!$isModuleTemplate) {
            $layoutPath = $this->themePath . '/templates/layout.php';
            if (file_exists($layoutPath)) {
                $content = $this->renderLayout($layoutPath, $this->data, $content);
            }
        }

        // Apply filters to final output
        $app = Application::getInstance();
        $content = $app->hooks()->fire('view.render', $content);

        return $content;
    }

    /**
     * Escape HTML (alias: e)
     */
    public function escape($value) {
        if (is_array($value)) {
            return array_map(array($this, 'escape'), $value);
        }
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
        $baseUrl = rtrim(config('site.url', ''), '/');
        return $baseUrl . '/' . basename(MANTRA_THEMES) . '/' . basename($this->themePath) . '/assets/' . ltrim($path, '/');
    }
}
