<?php
/**
 * PermissionRegistry - Central authority for permission management
 *
 * Replaces the hardcoded permission map from User::hasPermission().
 * Modules register their own permissions via the 'permissions.register' hook.
 * Custom role overrides are stored in config('permissions.roles').
 */

class PermissionRegistry {

    /** @var array All registered permissions: array of permission strings */
    private $permissions = array();

    /** @var array Grouped permissions for UI: group => array of permission strings */
    private $groups = array();

    /** @var array Permission labels for UI: permission => label */
    private $labels = array();

    /** @var array Built-in default role permissions: role => array of permission strings */
    private $defaults = array();

    /** @var array Cached resolved permissions per role: role => array of permission strings */
    private $resolved = array();

    public function __construct() {
        $this->registerCorePermissions();
        $this->registerCoreDefaults();
    }

    /**
     * Register core permissions and their groups.
     */
    private function registerCorePermissions() {
        $this->registerPermissions(array(
            'pages.view'     => 'View pages',
            'pages.create'   => 'Create pages',
            'pages.edit'     => 'Edit all pages',
            'pages.edit.own' => 'Edit own pages',
            'pages.delete'   => 'Delete pages',
        ), 'Pages');

        $this->registerPermissions(array(
            'posts.view'     => 'View posts',
            'posts.create'   => 'Create posts',
            'posts.edit'     => 'Edit all posts',
            'posts.edit.own' => 'Edit own posts',
            'posts.delete'   => 'Delete posts',
        ), 'Posts');

        $this->registerPermissions(array(
            'uploads.view'   => 'View uploads',
            'uploads.upload' => 'Upload files',
        ), 'Uploads');

        $this->registerPermissions(array(
            'users.view'   => 'View users',
            'users.create' => 'Create users',
            'users.edit'   => 'Edit users',
            'users.delete' => 'Delete users',
        ), 'Users');
    }

    /**
     * Register built-in default permissions per role.
     */
    private function registerCoreDefaults() {
        $this->defaults = array(
            'editor' => array(
                'pages.view', 'pages.create', 'pages.edit', 'pages.delete',
                'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
                'uploads.view', 'uploads.upload',
            ),
            'author' => array(
                'pages.view', 'pages.create', 'pages.edit.own',
                'posts.view', 'posts.create', 'posts.edit.own',
                'uploads.view', 'uploads.upload',
            ),
            'viewer' => array(
                'pages.view', 'posts.view',
            ),
        );
    }

    // ========== Registration API ==========

    /**
     * Register permissions with a group name.
     *
     * @param array  $permissions Keyed array: permission => label, or numeric array of permission strings
     * @param string $group       Group name for UI display
     */
    public function registerPermissions($permissions, $group = '') {
        if ($group === '') {
            $group = 'Other';
        }

        if (!isset($this->groups[$group])) {
            $this->groups[$group] = array();
        }

        foreach ($permissions as $key => $value) {
            if (is_int($key)) {
                // Numeric array: value is the permission string
                $permission = $value;
                $label = $value;
            } else {
                // Associative array: key is permission, value is label
                $permission = $key;
                $label = $value;
            }

            if (!in_array($permission, $this->permissions, true)) {
                $this->permissions[] = $permission;
                $this->groups[$group][] = $permission;
            }

            $this->labels[$permission] = $label;
        }

        // Clear resolved cache since permissions changed
        $this->resolved = array();
    }

    /**
     * Set default permissions for a role.
     * Modules can call this to provide defaults for their permissions.
     *
     * @param string $role        Role name
     * @param array  $permissions Array of permission strings to add to defaults
     */
    public function addRoleDefaults($role, $permissions) {
        if (!isset($this->defaults[$role])) {
            $this->defaults[$role] = array();
        }
        $this->defaults[$role] = array_values(array_unique(
            array_merge($this->defaults[$role], $permissions)
        ));
        $this->resolved = array();
    }

    // ========== Query API ==========

    /**
     * Get all registered permission strings.
     *
     * @return array
     */
    public function getAll() {
        return $this->permissions;
    }

    /**
     * Get permissions organized by group (for admin UI).
     *
     * @return array group => array of permission strings
     */
    public function getGrouped() {
        return $this->groups;
    }

    /**
     * Get human-readable label for a permission.
     *
     * @param string $permission
     * @return string
     */
    public function getLabel($permission) {
        return isset($this->labels[$permission]) ? $this->labels[$permission] : $permission;
    }

    /**
     * Get all labels.
     *
     * @return array permission => label
     */
    public function getLabels() {
        return $this->labels;
    }

    /**
     * Get available roles (excluding admin which always has all permissions).
     *
     * @return array
     */
    public function getConfigurableRoles() {
        return array('editor', 'author', 'viewer');
    }

    /**
     * Get all roles including admin.
     *
     * @return array
     */
    public function getRoles() {
        return array('admin', 'editor', 'author', 'viewer');
    }

    /**
     * Get built-in default permissions for a role.
     *
     * @param string $role
     * @return array
     */
    public function getDefaultsForRole($role) {
        return isset($this->defaults[$role]) ? $this->defaults[$role] : array();
    }

    /**
     * Get effective permissions for a role (config override or defaults).
     *
     * @param string $role
     * @return array
     */
    public function getPermissionsForRole($role) {
        if ($role === 'admin') {
            return $this->permissions;
        }

        if (isset($this->resolved[$role])) {
            return $this->resolved[$role];
        }

        // Check config overrides
        $overrides = config('permissions.roles.' . $role);
        if (is_array($overrides) && !empty($overrides)) {
            // Filter to only valid permissions
            $valid = array_values(array_intersect($overrides, $this->permissions));
            $this->resolved[$role] = $valid;
            return $valid;
        }

        // Fall back to defaults
        $defaults = $this->getDefaultsForRole($role);
        $this->resolved[$role] = $defaults;
        return $defaults;
    }

    /**
     * Check if a role has a specific permission.
     *
     * Returns:
     *   true   - role has full access to this permission
     *   'own'  - role has access only to own content (ownership check needed)
     *   false  - role does not have this permission
     *
     * @param string $role
     * @param string $permission
     * @return bool|string
     */
    public function hasPermission($role, $permission) {
        if ($role === 'admin') {
            return true;
        }

        $perms = $this->getPermissionsForRole($role);

        // Exact match: role has this permission directly
        if (in_array($permission, $perms, true)) {
            return true;
        }

        // If checking a base permission (e.g. 'posts.edit') and role has the
        // '.own' variant (e.g. 'posts.edit.own'), return 'own' sentinel
        // to signal that an ownership check is needed.
        if (substr($permission, -4) !== '.own') {
            $ownPermission = $permission . '.own';
            if (in_array($ownPermission, $perms, true)) {
                return 'own';
            }
        }

        return false;
    }

    /**
     * Check if role has a custom override in config.
     *
     * @param string $role
     * @return bool
     */
    public function hasOverride($role) {
        $overrides = config('permissions.roles.' . $role);
        return is_array($overrides) && !empty($overrides);
    }

    // ========== Persistence ==========

    /**
     * Save custom permissions for a role to config.
     *
     * @param string $role
     * @param array  $permissions
     */
    public function setRolePermissions($role, $permissions) {
        if ($role === 'admin') {
            return;
        }

        // Filter to only valid registered permissions
        $valid = array_values(array_intersect($permissions, $this->permissions));

        config()->set('permissions.roles.' . $role, $valid);
        config()->save();

        // Clear cache
        unset($this->resolved[$role]);
    }

    /**
     * Reset a role to built-in defaults (remove config override).
     *
     * @param string $role
     */
    public function resetRole($role) {
        if ($role === 'admin') {
            return;
        }

        config()->set('permissions.roles.' . $role, array());
        config()->save();

        unset($this->resolved[$role]);
    }
}
