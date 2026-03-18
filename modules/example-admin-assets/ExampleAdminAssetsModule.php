<?php
/**
 * Example: Admin Assets Module
 *
 * Demonstrates how to load custom CSS and JS files in the admin panel
 * using the new Module API methods.
 */

class ExampleAdminAssetsModule extends Module {

    public function init() {
        // Method 1: Use the new enqueue methods (recommended)
        $this->enqueueAdminStyle('css/admin-custom.css');
        $this->enqueueAdminScript('js/admin-custom.js');

        // Method 2: Add inline styles
        $this->addAdminInlineStyle('
        /* Inline styles example */
        .example-highlight {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        ');

        // Method 3: Add inline scripts
        $this->addAdminInlineScript("
        // Inline script example
        console.log('Example Admin Assets module loaded');

        // Add custom functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Add a custom class to all cards
            document.querySelectorAll('.card').forEach(function(card) {
                card.classList.add('example-enhanced');
            });
        });
        ");

        // Method 4: Manual hook (for advanced use cases)
        // $this->hook('admin.head', array($this, 'customHeadContent'));
    }

    /**
     * Example of manual hook for advanced use cases
     */
    public function customHeadContent($content) {
        // You can still use manual hooks if you need more control
        // For example, conditional loading based on current page
        $request = request();
        $path = $request->path();

        if (strpos($path, '/admin/pages') === 0) {
            // Only load on pages admin
            $url = $this->asset('css/pages-specific.css');
            return $content . "\n    <link rel=\"stylesheet\" href=\"" . e($url) . "\">";
        }

        return $content;
    }
}
