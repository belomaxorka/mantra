<?php
/**
 * AdminPanelInterface - Contract for admin panels
 *
 * Panels are lightweight admin-area pages managed by AdminModule.
 * Unlike modules, panels get auth, layout, and sidebar for free.
 */

namespace Admin;

interface AdminPanelInterface {

    /**
     * Unique panel identifier (e.g. 'pages', 'dashboard')
     * @return string
     */
    public function id();

    /**
     * Called once after instantiation. Receives the AdminModule instance.
     * Use for hook registration or one-time setup.
     *
     * @param \AdminModule $admin
     */
    public function init($admin);

    /**
     * Register routes via $admin->adminRoute().
     * Auth middleware is applied automatically by AdminModule.
     *
     * @param \AdminModule $admin
     */
    public function registerRoutes($admin);

    /**
     * Return a sidebar item array, or null if this panel has no sidebar entry.
     * Format: array with keys id, title, icon, group, order, url, children.
     *
     * @return array|null
     */
    public function getSidebarItem();

    /**
     * Return an array of quick-action arrays for the dashboard.
     * Each item: array with keys id, title, icon, url, order.
     *
     * @return array
     */
    public function getQuickActions();
}
