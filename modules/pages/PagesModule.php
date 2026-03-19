<?php
/**
 * PagesModule - Public pages integration
 *
 * Adds published pages with show_in_navigation flag to the site navigation menu.
 */

class PagesModule extends Module
{

    public function init()
    {
        // Hook into theme navigation to add pages
        $this->hook('theme.navigation', array($this, 'addPagesToNavigation'));
    }

    /**
     * Add published pages to navigation menu
     */
    public function addPagesToNavigation($navItems)
    {
        if (!is_array($navItems)) {
            $navItems = array();
        }

        $pages = db()->query('pages', array(
            'status' => 'published',
            'show_in_navigation' => true
        ));

        foreach ($pages as $page) {
            $navItems[] = array(
                'id' => 'page-' . $page['slug'],
                'title' => $page['title'],
                'url' => base_url('/' . $page['slug']),
                'order' => isset($page['navigation_order']) ? (int)$page['navigation_order'] : 50,
            );
        }

        return $navItems;
    }
}
