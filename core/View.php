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
     */
    public function render($template, $data = array()) {
        $this->data = $data;
        
        // Try theme template first
        $templatePath = $this->themePath . '/templates/' . $template . '.php';
        
        // Fallback to module template
        if (!file_exists($templatePath)) {
            // Template might be in format "module:template"
            if (strpos($template, ':') !== false) {
                list($module, $tpl) = explode(':', $template, 2);
                $templatePath = MANTRA_MODULES . '/' . $module . '/views/' . $tpl . '.php';
            }
        }
        
        if (!file_exists($templatePath)) {
            throw new Exception("Template not found: $template");
        }
        
        // Extract data to variables
        extract($this->data);
        
        // Start output buffering
        ob_start();
        
        // Include template
        include $templatePath;
        
        // Get content
        $content = ob_get_clean();
        
        // Apply filters
        $app = Application::getInstance();
        $content = $app->hooks()->fire('view.render', $content);
        
        echo $content;
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
        return $baseUrl . '/themes/' . basename($this->themePath) . '/assets/' . ltrim($path, '/');
    }
}
