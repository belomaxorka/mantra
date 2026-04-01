<?php
/**
 * HookRegistry - Documents standard hook contracts
 *
 * Provides a discoverable catalogue of all core hooks so module
 * developers know what data each hook receives and should return.
 * This is documentation-in-code, not runtime enforcement.
 */

class HookRegistry {

    /**
     * Standard hook definitions.
     * Each entry: 'hook.name' => array(description, data_type, return_type)
     */
    private static $hooks = array(

        // ── System lifecycle ────────────────────────────────
        'system.init' => array(
            'description' => 'Fired after all modules are loaded, before routing',
            'data_type'   => 'null',
            'return_type' => 'null',
        ),
        'system.shutdown' => array(
            'description' => 'Fired after the request has been handled',
            'data_type'   => 'null',
            'return_type' => 'null',
        ),
        'routes.register' => array(
            'description' => 'Modules register their routes here',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),

        // ── View ────────────────────────────────────────────
        'view.render' => array(
            'description' => 'Filter final rendered HTML before output',
            'data_type'   => 'string',
            'return_type' => 'string',
        ),

        // ── Theme (public) ──────────────────────────────────
        'theme.head' => array(
            'description' => 'Inject HTML into the public <head> section',
            'data_type'   => 'string',
            'return_type' => 'string',
        ),
        'theme.body.start' => array(
            'description' => 'Inject HTML right after the opening <body> tag',
            'data_type'   => 'string',
            'return_type' => 'string',
        ),
        'theme.navigation' => array(
            'description' => 'Build the main navigation menu items',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'theme.sidebar' => array(
            'description' => 'Build sidebar widget items for public theme',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'theme.footer.links' => array(
            'description' => 'Build footer link items',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'theme.footer' => array(
            'description' => 'Inject scripts/HTML before closing </body>',
            'data_type'   => 'string',
            'return_type' => 'string',
        ),
        'theme.body.end' => array(
            'description' => 'Inject HTML right before the closing </body> tag',
            'data_type'   => 'string',
            'return_type' => 'string',
        ),

        // ── Admin panel ─────────────────────────────────────
        'admin.head' => array(
            'description' => 'Inject HTML into the admin <head> section',
            'data_type'   => 'string',
            'return_type' => 'string',
        ),
        'admin.footer' => array(
            'description' => 'Inject scripts/HTML into the admin footer',
            'data_type'   => 'string',
            'return_type' => 'string',
        ),
        'admin.sidebar' => array(
            'description' => 'Build admin sidebar navigation items',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'admin.quick_actions' => array(
            'description' => 'Register dashboard quick action buttons',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),

        // ── Permissions ────────────────────────────────────
        'permissions.register' => array(
            'description' => 'Register module permissions with the PermissionRegistry',
            'data_type'   => 'PermissionRegistry',
            'return_type' => 'PermissionRegistry',
        ),

        // ── Content: home page ──────────────────────────────
        'page.home.query' => array(
            'description' => 'Modify query parameters for the home page post list',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'page.home.posts' => array(
            'description' => 'Filter the post list on the home page',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'page.home.data' => array(
            'description' => 'Modify template data for the home page',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),

        // ── Content: blog listing ───────────────────────────
        'page.blog.query' => array(
            'description' => 'Modify query parameters for the blog listing',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'page.blog.posts' => array(
            'description' => 'Filter the post list on the blog page',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'page.blog.data' => array(
            'description' => 'Modify template data for the blog page',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),

        // ── Content: single page ────────────────────────────
        'page.single.query' => array(
            'description' => 'Modify query parameters for a single page',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'page.single.loaded' => array(
            'description' => 'Filter the loaded page document before rendering',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'page.single.data' => array(
            'description' => 'Modify template data for a single page',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),

        // ── Content: single post ────────────────────────────
        'post.single.query' => array(
            'description' => 'Modify query parameters for a single post',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'post.single.loaded' => array(
            'description' => 'Filter the loaded post document before rendering',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
        'post.single.data' => array(
            'description' => 'Modify template data for a single post',
            'data_type'   => 'array',
            'return_type' => 'array',
        ),
    );

    /**
     * Get the definition for a specific hook
     *
     * @param string $name Hook name
     * @return array|null array with description, data_type, return_type or null
     */
    public static function describe($name) {
        return isset(self::$hooks[$name]) ? self::$hooks[$name] : null;
    }

    /**
     * Get all registered hook definitions
     *
     * @return array
     */
    public static function all() {
        return self::$hooks;
    }

    /**
     * Check whether a hook name is a known standard hook
     *
     * @param string $name
     * @return bool
     */
    public static function isStandard($name) {
        return isset(self::$hooks[$name]);
    }

    /**
     * Register a hook definition (for modules/panels that expose their own hooks)
     *
     * @param string $name Hook name
     * @param string $description Human-readable description
     * @param string $dataType Expected input type (e.g. 'array', 'string', 'null')
     * @param string $returnType Expected return type
     * @param array  $extra Extra fields: 'source', 'context', etc.
     */
    public static function define($name, $description, $dataType = 'mixed', $returnType = 'mixed', $extra = array()) {
        $entry = array(
            'description' => $description,
            'data_type'   => $dataType,
            'return_type' => $returnType,
        );
        if (!empty($extra)) {
            $entry = array_merge($entry, $extra);
        }
        self::$hooks[$name] = $entry;
    }
}
