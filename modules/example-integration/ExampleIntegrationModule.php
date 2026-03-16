<?php
/**
 * ExampleIntegrationModule - Demonstrates integration points
 *
 * This module shows how to use various integration points:
 * - theme.navigation (add menu items)
 * - theme.footer.links (add footer links)
 * - admin.sidebar (add admin menu items)
 * - admin.quick_actions (add dashboard actions)
 */

class ExampleIntegrationModule extends Module {

    public function init() {
        // Add navigation item to public menu
        $this->hook('theme.navigation', array($this, 'addNavigationItem'));

        // Add footer link
        $this->hook('theme.footer.links', array($this, 'addFooterLink'));

        // Add admin sidebar item
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'example-integration',
                'title' => 'Example Page',
                'icon' => 'bi-star',
                'group' => 'Examples',
                'order' => 90,
                'url' => base_url('/admin/example'),
            );

            return $items;
        });

        // Add quick action to dashboard
        app()->hooks()->register('admin.quick_actions', function ($actions) {
            if (!is_array($actions)) {
                $actions = array();
            }

            $actions[] = array(
                'id' => 'example-action',
                'title' => 'Example Action',
                'icon' => 'bi-lightning-fill',
                'url' => base_url('/example-page'),
                'order' => 50,
            );

            return $actions;
        });

        // Register routes
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }

    /**
     * Add navigation item to public menu
     */
    public function addNavigationItem($items) {
        if (!is_array($items)) {
            $items = array();
        }

        // Check if enabled in module settings
        $showInNav = module_settings('example-integration')->get('show_in_navigation', true);

        if ($showInNav) {
            $items[] = array(
                'id' => 'example-page',
                'title' => 'Example',
                'url' => base_url('/example-page'),
                'order' => 50,
            );
        }

        return $items;
    }

    /**
     * Add footer link
     */
    public function addFooterLink($links) {
        if (!is_array($links)) {
            $links = array();
        }

        $links[] = array(
            'id' => 'example-link',
            'title' => 'Example',
            'url' => base_url('/example-page'),
            'order' => 10,
        );

        return $links;
    }

    /**
     * Register routes
     */
    public function registerRoutes($data) {
        $router = $data['router'];
        $router->get('/example-page', array($this, 'showExamplePage'));
        return $data;
    }

    /**
     * Show example page
     */
    public function showExamplePage() {
        $view = new View();
        $view->render('page', array(
            'title' => 'Example Integration Page',
            'page' => array(
                'title' => 'Example Integration',
                'content' => $this->getExampleContent(),
            ),
        ));
    }

    /**
     * Get example page content
     */
    private function getExampleContent() {
        return '
            <h1>Example Integration Module</h1>
            <p>This page was added by the Example Integration module using integration points.</p>

            <h2>Integration Points Used</h2>
            <ul>
                <li><strong>theme.navigation</strong> - Added "Example" link to main navigation</li>
                <li><strong>theme.footer.links</strong> - Added "Example" link to footer</li>
                <li><strong>admin.sidebar</strong> - Added "Example Page" to admin sidebar</li>
                <li><strong>admin.quick_actions</strong> - Added "Example Action" to dashboard</li>
            </ul>

            <h2>How It Works</h2>
            <p>The module registers hooks in its <code>init()</code> method:</p>
            <pre><code>// Add navigation item
$this->hook(\'theme.navigation\', array($this, \'addNavigationItem\'));

// Add footer link
$this->hook(\'theme.footer.links\', array($this, \'addFooterLink\'));</code></pre>

            <p>Each hook receives an array of items and returns the modified array with new items added.</p>

            <h2>Module Settings</h2>
            <p>This module respects the "show_in_navigation" setting. You can disable the navigation item in module settings.</p>

            <p><a href="' . base_url() . '" class="btn btn-primary">Back to Home</a></p>
        ';
    }
}
