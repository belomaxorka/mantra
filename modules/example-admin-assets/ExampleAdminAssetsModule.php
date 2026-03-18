<?php
/**
 * Example: Admin Assets Module
 *
 * Demonstrates how to load custom CSS and JS files in the admin panel
 * using admin.head and admin.footer hooks.
 */

class ExampleAdminAssetsModule extends Module {

    public function init() {
        // Hook into admin head to add CSS
        $this->hook('admin.head', array($this, 'addAdminStyles'));

        // Hook into admin footer to add JS
        $this->hook('admin.footer', array($this, 'addAdminScripts'));
    }

    /**
     * Add custom CSS to admin panel
     * This hook fires in <head> section
     */
    public function addAdminStyles($content) {
        $moduleUrl = base_url('/modules/example-admin-assets');

        $styles = <<<HTML

    <!-- Example Admin Assets: Custom Styles -->
    <link rel="stylesheet" href="{$moduleUrl}/assets/css/admin-custom.css">
    <style>
        /* Inline styles example */
        .example-highlight {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
    </style>
HTML;

        return $content . $styles;
    }

    /**
     * Add custom JS to admin panel
     * This hook fires before </body>
     */
    public function addAdminScripts($content) {
        $moduleUrl = base_url('/modules/example-admin-assets');

        $scripts = <<<HTML

    <!-- Example Admin Assets: Custom Scripts -->
    <script src="{$moduleUrl}/assets/js/admin-custom.js"></script>
    <script>
        // Inline script example
        console.log('Example Admin Assets module loaded');

        // Add custom functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Add a custom class to all cards
            document.querySelectorAll('.card').forEach(function(card) {
                card.classList.add('example-enhanced');
            });
        });
    </script>
HTML;

        return $content . $scripts;
    }
}
