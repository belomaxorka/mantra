<?php declare(strict_types=1);

namespace Admin;

class UsersPanel extends ContentPanel
{
    private $userManager = null;

    public function id()
    {
        return 'users';
    }

    public function init($admin): void
    {
        parent::init($admin);

        app()->db()->registerSchema('users', $this->getPath() . '/schema.php');
        $this->registerPanelHooks();
        $this->hook('permissions.register', [$this, 'registerPermissions']);
    }

    /**
     * Register user management permissions with the central registry.
     */
    public function registerPermissions($registry)
    {
        $registry->registerPermissions([
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.edit' => 'Edit users',
            'users.delete' => 'Delete users',
        ], 'Users');

        return $registry;
    }

    protected function getContentType()
    {
        return 'User';
    }

    protected function getCollectionName()
    {
        return 'users';
    }

    protected function getDefaultItem()
    {
        return [
            'username' => '',
            'email' => '',
            'password' => '',
            'role' => 'editor',
            'status' => 'active',
        ];
    }

    protected function extractFormData()
    {
        $data = [
            'email' => app()->request()->postTrimmed('email'),
            'role' => app()->request()->post('role', 'editor'),
            'status' => app()->request()->post('status', 'active'),
        ];

        // Password: empty means keep current (User::update handles this)
        $password = app()->request()->post('password', '');
        if ($password !== '') {
            $data['password'] = $password;
        }

        return $data;
    }

    // Users don't have slugs
    protected function ensureSlug($data)
    {
        return $data;
    }

    private function getUserManager()
    {
        if ($this->userManager === null) {
            $this->userManager = new \User();
        }
        return $this->userManager;
    }

    // ========== Overrides ==========

    public function listItems()
    {
        if (!$this->requirePermission('users.view')) return;

        $allUsers = $this->getUserManager()->all();
        $currentUser = $this->getUser();

        $perPage = 25;
        $page = max(1, (int)app()->request()->query('page', 1));
        $paginator = new \Paginator(count($allUsers), $perPage, $page);
        $users = array_slice($allUsers, $paginator->offset(), $paginator->perPage());

        $content = $this->renderView($this->getListTemplate(), array_merge(
            [
                'users' => $users,
                'paginator' => $paginator,
                'currentUserId' => $currentUser['_id'] ?? '',
            ],
            $this->getPermissionFlags(),
        ));

        return $this->renderAdmin(t($this->getDomain() . '.title'), $content, [
            'breadcrumbs' => $this->getListBreadcrumbs(),
        ]);
    }

    public function createItem(): void
    {
        if (!$this->requirePermission('users.create')) return;

        $data = $this->extractFormData();
        $data['username'] = app()->request()->postTrimmed('username');

        // Only admins can assign roles
        $currentUser = $this->getUser();
        $currentRole = $currentUser['role'] ?? '';
        if ($currentRole !== 'admin') {
            $data['role'] = 'viewer';
        }

        $result = $this->getUserManager()->create($data);

        if ($result === false) {
            $data['password'] = '';
            $title = t($this->getDomain() . '.new');
            $content = $this->renderView($this->getEditTemplate(), [
                'user' => $data,
                'isNew' => true,
                'csrf_token' => $this->auth()->generateCsrfToken(),
                'error' => t('admin-users.create_error'),
            ]);
            $this->renderAdmin($title, $content, [
                'breadcrumbs' => $this->getItemBreadcrumbs($title),
            ]);
            return;
        }

        $this->redirectAdmin($this->getAdminPath());
    }

    public function updateItem($params): void
    {
        if (!$this->requirePermission('users.edit')) return;

        $id = $params['id'] ?? '';
        $user = $this->getUserManager()->find($id);

        if (!$user) {
            $this->renderErrorPage(t('admin-users.not_found'), 404);
            return;
        }

        $data = $this->extractFormData();

        // Escalation protection: only admins can change roles
        $currentUser = $this->getUser();
        $currentRole = $currentUser['role'] ?? '';
        if ($currentRole !== 'admin') {
            unset($data['role']);
        }

        // Prevent non-admins from editing admin accounts
        $targetRole = $user['role'] ?? '';
        if ($currentRole !== 'admin' && $targetRole === 'admin') {
            $this->renderErrorPage(t('admin.common.access_denied'));
            return;
        }

        $this->getUserManager()->update($id, $data);
        $this->redirectAdmin($this->getAdminPath());
    }

    public function deleteItem($params): void
    {
        if (!$this->requirePermission('users.delete')) return;

        $id = $params['id'] ?? '';
        $currentUser = $this->getUser();

        // Prevent self-deletion
        if (isset($currentUser['_id']) && $id === $currentUser['_id']) {
            $this->redirectAdmin($this->getAdminPath());
            return;
        }

        // Prevent deleting last admin
        $target = $this->getUserManager()->find($id);
        if ($target && isset($target['role']) && $target['role'] === 'admin') {
            $admins = $this->getUserManager()->all(['role' => 'admin']);
            if (count($admins) <= 1) {
                $this->redirectAdmin($this->getAdminPath());
                return;
            }
        }

        $this->getUserManager()->delete($id);
        $this->redirectAdmin($this->getAdminPath());
    }
}
