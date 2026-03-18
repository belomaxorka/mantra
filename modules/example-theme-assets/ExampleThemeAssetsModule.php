<?php
/**
 * Example: Theme Assets Module
 *
 * Demonstrates how to load custom CSS and JS files in the public theme
 * using the Module API methods.
 */

class ExampleThemeAssetsModule extends Module {

    public function init() {
        // Method 1: Enqueue external files (recommended)
        $this->enqueueStyle('css/theme-custom.css');
        $this->enqueueScript('js/theme-custom.js');

        // Method 2: Add inline styles
        $this->addInlineStyle('
        /* Inline theme styles */
        body {
            /* Example: Add custom font */
        }
        ');

        // Method 3: Add inline scripts
        $this->addInlineScript("
        // Inline theme script
        console.log('Theme assets module loaded');
        ");

        // Method 4: Conditional loading (advanced)
        $this->hook('theme.head', array($this, 'conditionalAssets'));
    }

    /**
     * Example: Load assets conditionally based on page type
     */
    public function conditionalAssets($content) {
        $request = request();
        $path = $request->path();

        // Example: Load special CSS only on blog posts
        if (strpos($path, '/post/') === 0) {
            $url = $this->asset('css/post-specific.css');
            return $content . "\n    <link rel=\"stylesheet\" href=\"" . e($url) . "\">";
        }

        return $content;
    }
}
