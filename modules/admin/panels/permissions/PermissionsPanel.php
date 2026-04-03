<?php declare(strict_types=1);
/**
 * PermissionsPanel - Role permission management
 *
 * Provides a matrix UI for configuring which permissions each role has.
 * Permissions are registered by core and modules via the 'permissions.register' hook.
 * Custom overrides are stored in config('permissions.roles').
 */

namespace Admin;

class PermissionsPanel extends AdminPanel {

    public function id() {
        return 'permissions';
    }

    public function registerRoutes($admin): void {
        $admin->adminRoute('GET',  'permissions', [$this, 'index']);
        $admin->adminRoute('POST', 'permissions', [$this, 'save']);
    }

    /**
     * Display the permission matrix.
     */
    public function index() {
        if (!$this->requireAdmin()) return;

        $registry = app()->service('permissions');
        if (!$registry) {
            return $this->renderAdmin(
                t('admin-permissions.title'),
                '<div class="alert alert-danger alert-permanent">Permission registry not available</div>',
            );
        }

        return $this->renderPermissions($registry);
    }

    /**
     * Save permission changes or reset a role.
     */
    public function save() {
        if (!$this->requireAdmin()) return;
        if (!$this->verifyCsrf()) return;

        $registry = app()->service('permissions');
        if (!$registry) {
            return $this->renderAdmin(
                t('admin-permissions.title'),
                '<div class="alert alert-danger alert-permanent">Permission registry not available</div>',
            );
        }

        $notice = null;

        // Check for reset action
        $resetRole = app()->request()->post('reset_role', '');
        if ($resetRole !== '') {
            $configurableRoles = $registry->getConfigurableRoles();
            if (in_array($resetRole, $configurableRoles, true)) {
                $registry->resetRole($resetRole);
                $notice = sprintf(t('admin-permissions.reset_success'), $resetRole);
            }
            return $this->renderPermissions($registry, $notice);
        }

        // Save permissions for each configurable role
        $configurableRoles = $registry->getConfigurableRoles();
        $allPermissions = $registry->getAll();

        foreach ($configurableRoles as $role) {
            $posted = app()->request()->post('role_' . $role, []);
            if (!is_array($posted)) {
                $posted = [];
            }

            // Filter to only valid registered permissions
            $valid = array_values(array_intersect($posted, $allPermissions));
            $registry->setRolePermissions($role, $valid);
        }

        $notice = t('admin-permissions.saved');

        return $this->renderPermissions($registry, $notice);
    }

    /**
     * Render the permissions page.
     */
    private function renderPermissions($registry, $notice = null) {
        $roles = $registry->getConfigurableRoles();
        $grouped = $registry->getGrouped();
        $labels = $registry->getLabels();

        // Build role data
        $roleData = [];
        foreach ($roles as $role) {
            $roleData[$role] = [
                'permissions' => $registry->getPermissionsForRole($role),
                'hasOverride' => $registry->hasOverride($role),
            ];
        }

        $content = $this->renderView('permissions', [
            'roles' => $roles,
            'grouped' => $grouped,
            'labels' => $labels,
            'roleData' => $roleData,
            'csrf_token' => $this->auth()->generateCsrfToken(),
            'notice' => $notice,
        ]);

        return $this->renderAdmin(t('admin-permissions.title'), $content, [
            'breadcrumbs' => [
                ['title' => t('admin-dashboard.title'), 'url' => base_url('/admin')],
                ['title' => t('admin-permissions.title')],
            ],
        ]);
    }
}
