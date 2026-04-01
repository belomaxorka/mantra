<?php
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

    public function registerRoutes($admin) {
        $admin->adminRoute('GET',  'permissions', array($this, 'index'));
        $admin->adminRoute('POST', 'permissions', array($this, 'save'));
    }

    /**
     * Display the permission matrix.
     */
    public function index() {
        if (!$this->requireAdmin()) return;

        $registry = permissions();
        if (!$registry) {
            return $this->renderAdmin(
                t('admin-permissions.title'),
                '<div class="alert alert-danger alert-permanent">Permission registry not available</div>'
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

        $registry = permissions();
        if (!$registry) {
            return $this->renderAdmin(
                t('admin-permissions.title'),
                '<div class="alert alert-danger alert-permanent">Permission registry not available</div>'
            );
        }

        $notice = null;

        // Check for reset action
        $resetRole = request()->post('reset_role', '');
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
            $posted = request()->post('role_' . $role, array());
            if (!is_array($posted)) {
                $posted = array();
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
        $roleData = array();
        foreach ($roles as $role) {
            $roleData[$role] = array(
                'permissions' => $registry->getPermissionsForRole($role),
                'hasOverride' => $registry->hasOverride($role),
            );
        }

        $content = $this->renderView('permissions', array(
            'roles' => $roles,
            'grouped' => $grouped,
            'labels' => $labels,
            'roleData' => $roleData,
            'csrf_token' => auth()->generateCsrfToken(),
            'notice' => $notice,
        ));

        return $this->renderAdmin(t('admin-permissions.title'), $content, array(
            'breadcrumbs' => array(
                array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
                array('title' => t('admin-permissions.title')),
            ),
        ));
    }
}
