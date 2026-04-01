<?php

namespace Admin;

class UsersPanel extends ContentPanel {

    private $userManager = null;

    public function id() {
        return 'users';
    }

    public function init($admin) {
        parent::init($admin);

        $this->hook('permissions.register', array($this, 'registerPermissions'));
    }

    /**
     * Register user management permissions with the central registry.
     */
    public function registerPermissions($registry) {
        $registry->registerPermissions(array(
            'users.view'   => 'View users',
            'users.create' => 'Create users',
            'users.edit'   => 'Edit users',
            'users.delete' => 'Delete users',
        ), 'Users');

        return $registry;
    }

    protected function getContentType() {
        return 'User';
    }

    protected function getCollectionName() {
        return 'users';
    }

    protected function getDefaultItem() {
        return array(
            'username' => '',
            'email' => '',
            'password' => '',
            'role' => 'editor',
            'status' => 'active',
        );
    }

    protected function extractFormData() {
        $data = array(
            'email'  => app()->request()->postTrimmed('email'),
            'role'   => app()->request()->post('role', 'editor'),
            'status' => app()->request()->post('status', 'active'),
        );

        // Password: empty means keep current (User::update handles this)
        $password = app()->request()->post('password', '');
        if ($password !== '') {
            $data['password'] = $password;
        }

        return $data;
    }

    // Users don't have slugs
    protected function ensureSlug($data) {
        return $data;
    }

    // Use generated ID instead of slug-based
    protected function generateId($data) {
        return $this->db()->generateId();
    }

    private function getUserManager() {
        if ($this->userManager === null) {
            $this->userManager = new \User();
        }
        return $this->userManager;
    }

    // ========== Overrides ==========

    public function listItems() {
        if (!$this->requirePermission('users.view')) return;

        $users = $this->getUserManager()->all();
        $currentUser = $this->getUser();

        $content = $this->renderView($this->getListTemplate(), array_merge(
            array(
                'users' => $users,
                'currentUserId' => isset($currentUser['_id']) ? $currentUser['_id'] : '',
            ),
            $this->getPermissionFlags()
        ));

        return $this->renderAdmin(t($this->getDomain() . '.title'), $content, array(
            'breadcrumbs' => $this->getListBreadcrumbs(),
        ));
    }

    public function createItem() {
        if (!$this->requirePermission('users.create')) return;
        if (!$this->verifyCsrf()) return;

        $data = $this->extractFormData();
        $data['username'] = app()->request()->postTrimmed('username');

        // Only admins can assign roles
        $currentUser = $this->getUser();
        $currentRole = isset($currentUser['role']) ? $currentUser['role'] : '';
        if ($currentRole !== 'admin') {
            $data['role'] = 'viewer';
        }

        $result = $this->getUserManager()->create($data);

        if ($result === false) {
            $data['password'] = '';
            $title = t($this->getDomain() . '.new');
            $content = $this->renderView($this->getEditTemplate(), array(
                'user' => $data,
                'isNew' => true,
                'csrf_token' => $this->auth()->generateCsrfToken(),
                'error' => t('admin-users.create_error'),
            ));
            return $this->renderAdmin($title, $content, array(
                'breadcrumbs' => $this->getItemBreadcrumbs($title),
            ));
        }

        $this->redirectAdmin($this->getAdminPath());
    }

    public function updateItem($params) {
        if (!$this->requirePermission('users.edit')) return;
        if (!$this->verifyCsrf()) return;

        $id = isset($params['id']) ? $params['id'] : '';
        $user = $this->getUserManager()->find($id);

        if (!$user) {
            http_response_code(404);
            return $this->renderAdmin('Not Found',
                '<div class="alert alert-danger alert-permanent">' . e(t('admin-users.not_found')) . '</div>');
        }

        $data = $this->extractFormData();

        // Escalation protection: only admins can change roles
        $currentUser = $this->getUser();
        $currentRole = isset($currentUser['role']) ? $currentUser['role'] : '';
        if ($currentRole !== 'admin') {
            unset($data['role']);
        }

        // Prevent non-admins from editing admin accounts
        $targetRole = isset($user['role']) ? $user['role'] : '';
        if ($currentRole !== 'admin' && $targetRole === 'admin') {
            http_response_code(403);
            echo $this->renderAdmin(
                t('admin.common.access_denied'),
                '<div class="alert alert-danger alert-permanent">' . e(t('admin.common.access_denied')) . '</div>'
            );
            return;
        }

        $this->getUserManager()->update($id, $data);
        $this->redirectAdmin($this->getAdminPath());
    }

    public function deleteItem($params) {
        if (!$this->requirePermission('users.delete')) return;
        if (!$this->verifyCsrf()) return;

        $id = isset($params['id']) ? $params['id'] : '';
        $currentUser = $this->getUser();

        // Prevent self-deletion
        if (isset($currentUser['_id']) && $id === $currentUser['_id']) {
            $this->redirectAdmin($this->getAdminPath());
            return;
        }

        // Prevent deleting last admin
        $target = $this->getUserManager()->find($id);
        if ($target && isset($target['role']) && $target['role'] === 'admin') {
            $admins = $this->getUserManager()->all(array('role' => 'admin'));
            if (count($admins) <= 1) {
                $this->redirectAdmin($this->getAdminPath());
                return;
            }
        }

        $this->getUserManager()->delete($id);
        $this->redirectAdmin($this->getAdminPath());
    }
}
