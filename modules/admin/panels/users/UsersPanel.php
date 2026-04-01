<?php

namespace Admin;

class UsersPanel extends AdminPanel {

    private $userManager = null;

    public function id() {
        return 'users';
    }

    public function registerRoutes($admin) {
        $path = 'users';
        $admin->adminRoute('GET',  $path,                  array($this, 'listUsers'));
        $admin->adminRoute('GET',  $path . '/new',         array($this, 'newUser'));
        $admin->adminRoute('POST', $path . '/new',         array($this, 'createUser'));
        $admin->adminRoute('GET',  $path . '/edit/{id}',   array($this, 'editUser'));
        $admin->adminRoute('POST', $path . '/edit/{id}',   array($this, 'updateUser'));
        $admin->adminRoute('POST', $path . '/delete/{id}', array($this, 'deleteUser'));
    }

    private function getUserManager() {
        if ($this->userManager === null) {
            $this->userManager = new \User();
        }
        return $this->userManager;
    }

    private function requireAdmin() {
        if (!$this->auth()->hasRole('admin')) {
            http_response_code(403);
            echo $this->renderAdmin(
                t('admin-users.title'),
                '<div class="alert alert-danger alert-permanent">' . e(t('admin-users.access_denied')) . '</div>'
            );
            return false;
        }
        return true;
    }

    private function getDomain() {
        return 'admin-users';
    }

    // ========== Breadcrumbs ==========

    private function getListBreadcrumbs() {
        return array(
            array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
            array('title' => t('admin-users.title')),
        );
    }

    private function getItemBreadcrumbs($title) {
        return array(
            array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
            array('title' => t('admin-users.title'), 'url' => base_url('/admin/users')),
            array('title' => $title),
        );
    }

    // ========== CRUD ==========

    public function listUsers() {
        if (!$this->requireAdmin()) return;

        $users = $this->getUserManager()->all();
        $currentUser = $this->getUser();

        $content = $this->renderView('list', array(
            'users' => $users,
            'currentUserId' => isset($currentUser['_id']) ? $currentUser['_id'] : '',
        ));

        return $this->renderAdmin(t('admin-users.title'), $content, array(
            'breadcrumbs' => $this->getListBreadcrumbs(),
        ));
    }

    public function newUser() {
        if (!$this->requireAdmin()) return;

        $title = t('admin-users.new');

        $content = $this->renderView('edit', array(
            'user' => array(
                'username' => '',
                'email' => '',
                'password' => '',
                'role' => 'editor',
                'status' => 'active',
            ),
            'isNew' => true,
            'csrf_token' => $this->auth()->generateCsrfToken(),
        ));

        return $this->renderAdmin($title, $content, array(
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ));
    }

    public function createUser() {
        if (!$this->requireAdmin()) return;
        if (!$this->verifyCsrf()) return;

        $data = array(
            'username' => post_trimmed('username'),
            'email'    => post_trimmed('email'),
            'password' => request()->post('password', ''),
            'role'     => request()->post('role', 'editor'),
            'status'   => request()->post('status', 'active'),
        );

        $result = $this->getUserManager()->create($data);

        if ($result === false) {
            // Re-render form with error
            $data['password'] = '';
            $content = $this->renderView('edit', array(
                'user' => $data,
                'isNew' => true,
                'csrf_token' => $this->auth()->generateCsrfToken(),
                'error' => t('admin-users.create_error'),
            ));

            return $this->renderAdmin(t('admin-users.new'), $content, array(
                'breadcrumbs' => $this->getItemBreadcrumbs(t('admin-users.new')),
            ));
        }

        $this->redirectAdmin('users');
    }

    public function editUser($params) {
        if (!$this->requireAdmin()) return;

        $id = isset($params['id']) ? $params['id'] : '';
        $user = $this->getUserManager()->find($id);

        if (!$user) {
            http_response_code(404);
            return $this->renderAdmin(t('admin-users.title'),
                '<div class="alert alert-danger alert-permanent">' . e(t('admin-users.not_found')) . '</div>');
        }

        $title = t('admin-users.edit_user');

        $content = $this->renderView('edit', array(
            'user' => $user,
            'isNew' => false,
            'csrf_token' => $this->auth()->generateCsrfToken(),
        ));

        return $this->renderAdmin($title, $content, array(
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ));
    }

    public function updateUser($params) {
        if (!$this->requireAdmin()) return;
        if (!$this->verifyCsrf()) return;

        $id = isset($params['id']) ? $params['id'] : '';
        $user = $this->getUserManager()->find($id);

        if (!$user) {
            http_response_code(404);
            return $this->renderAdmin(t('admin-users.title'),
                '<div class="alert alert-danger alert-permanent">' . e(t('admin-users.not_found')) . '</div>');
        }

        $data = array(
            'email'  => post_trimmed('email'),
            'role'   => request()->post('role', 'editor'),
            'status' => request()->post('status', 'active'),
        );

        // Password: empty means keep current (User::update handles this)
        $password = request()->post('password', '');
        if ($password !== '') {
            $data['password'] = $password;
        }

        $this->getUserManager()->update($id, $data);
        $this->redirectAdmin('users');
    }

    public function deleteUser($params) {
        if (!$this->requireAdmin()) return;
        if (!$this->verifyCsrf()) return;

        $id = isset($params['id']) ? $params['id'] : '';
        $currentUser = $this->getUser();

        // Prevent self-deletion
        if (isset($currentUser['_id']) && $id === $currentUser['_id']) {
            $this->redirectAdmin('users');
            return;
        }

        // Prevent deleting last admin
        $target = $this->getUserManager()->find($id);
        if ($target && isset($target['role']) && $target['role'] === 'admin') {
            $admins = $this->getUserManager()->all(array('role' => 'admin'));
            if (count($admins) <= 1) {
                $this->redirectAdmin('users');
                return;
            }
        }

        $this->getUserManager()->delete($id);
        $this->redirectAdmin('users');
    }
}
