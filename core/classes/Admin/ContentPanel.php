<?php
/**
 * ContentPanel - CRUD scaffolding for admin panels
 *
 * Panel equivalent of ContentAdminModule. Implement the four abstract
 * methods and you get a full list/create/edit/delete admin interface.
 */

namespace Admin;

abstract class ContentPanel extends AdminPanel {

    /**
     * Singular content type name (e.g. 'Page', 'Post')
     * @return string
     */
    abstract protected function getContentType();

    /**
     * Database collection name (e.g. 'pages', 'posts')
     * @return string
     */
    abstract protected function getCollectionName();

    /**
     * Default empty item data array
     * @return array
     */
    abstract protected function getDefaultItem();

    /**
     * Extract form data from the current POST request
     * @return array
     */
    abstract protected function extractFormData();

    /**
     * Admin URL path segment (without /admin/ prefix).
     * Defaults to the collection name.
     * @return string
     */
    protected function getAdminPath() {
        return $this->getCollectionName();
    }

    /**
     * Template name for the list view (default: 'list')
     * @return string
     */
    protected function getListTemplate() {
        return 'list';
    }

    /**
     * Template name for the edit/new view (default: 'edit')
     * @return string
     */
    protected function getEditTemplate() {
        return 'edit';
    }

    // ========== Route Registration ==========

    /**
     * Register standard CRUD routes.
     * Override to add/remove routes.
     */
    public function registerRoutes($admin) {
        $path = $this->getAdminPath();

        $admin->adminRoute('GET',  $path,                   array($this, 'listItems'));
        $admin->adminRoute('GET',  $path . '/new',          array($this, 'newItem'));
        $admin->adminRoute('POST', $path . '/new',          array($this, 'createItem'));
        $admin->adminRoute('GET',  $path . '/edit/{id}',    array($this, 'editItem'));
        $admin->adminRoute('POST', $path . '/edit/{id}',    array($this, 'updateItem'));
        $admin->adminRoute('POST', $path . '/delete/{id}',  array($this, 'deleteItem'));
    }

    // ========== Helpers ==========

    protected function generateId($data) {
        $slug = $data['slug'];
        $id = $slug;
        if (app()->db()->exists($this->getCollectionName(), $id)) {
            $id = $slug . '-' . uniqid();
        }
        return $id;
    }

    protected function ensureSlug($data) {
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = slugify($data['title']);
        } elseif (!empty($data['slug'])) {
            $data['slug'] = slugify($data['slug']);
        }
        return $data;
    }

    /**
     * Translation domain for this panel (default: "admin-{id}")
     * @return string
     */
    protected function getDomain() {
        return 'admin-' . $this->id();
    }

    // ========== Breadcrumbs ==========

    protected function getListBreadcrumbs() {
        return array(
            array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
            array('title' => t($this->getDomain() . '.title')),
        );
    }

    protected function getItemBreadcrumbs($itemTitle) {
        return array(
            array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
            array('title' => t($this->getDomain() . '.title'), 'url' => base_url('/admin/' . $this->getAdminPath())),
            array('title' => $itemTitle),
        );
    }

    // ========== Ownership Check ==========

    /**
     * Verify that the current user owns the given content item.
     * Renders 403 and returns false if not.
     *
     * @param array $item Content item with 'author' field
     * @return bool
     */
    protected function checkOwnership($item) {
        $userManager = new \User();
        if ($userManager->canEdit($this->getUser(), $item)) {
            return true;
        }
        http_response_code(403);
        echo $this->renderAdmin(
            t('admin.common.access_denied'),
            '<div class="alert alert-danger alert-permanent">' . e(t('admin.common.access_denied')) . '</div>'
        );
        return false;
    }

    // ========== Permission Helpers ==========

    /**
     * Permission prefix for this panel (e.g. 'posts', 'pages').
     */
    protected function getPermissionPrefix() {
        return $this->getCollectionName();
    }

    /**
     * Check permission flags for list views.
     */
    protected function getPermissionFlags() {
        $userManager = new \User();
        $user = $this->getUser();
        $prefix = $this->getPermissionPrefix();
        return array(
            'canCreate' => $userManager->hasPermission($user, $prefix . '.create'),
            'canEdit'   => $userManager->hasPermission($user, $prefix . '.edit'),
            'canDelete' => $userManager->hasPermission($user, $prefix . '.delete'),
        );
    }

    // ========== CRUD Actions ==========

    public function listItems() {
        $prefix = $this->getPermissionPrefix();
        if (!$this->requirePermission($prefix . '.view')) return;

        $items = app()->db()->query($this->getCollectionName(), array(), array(
            'sort' => 'updated_at',
            'order' => 'desc'
        ));

        $content = $this->renderView($this->getListTemplate(), array_merge(
            array(strtolower($this->getCollectionName()) => $items),
            $this->getPermissionFlags()
        ));

        $title = t($this->getDomain() . '.title');

        return $this->renderAdmin($title, $content, array(
            'breadcrumbs' => $this->getListBreadcrumbs(),
        ));
    }

    public function newItem() {
        $prefix = $this->getPermissionPrefix();
        if (!$this->requirePermission($prefix . '.create')) return;

        $content = $this->renderView($this->getEditTemplate(), array(
            strtolower($this->getContentType()) => $this->getDefaultItem(),
            'isNew' => true,
            'csrf_token' => app()->auth()->generateCsrfToken()
        ));

        $title = t($this->getDomain() . '.new');

        return $this->renderAdmin($title, $content, array(
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ));
    }

    public function createItem() {
        $prefix = $this->getPermissionPrefix();
        if (!$this->requirePermission($prefix . '.create')) return;
        if (!$this->verifyCsrf()) {
            return;
        }

        $data = $this->extractFormData();
        $data = $this->ensureSlug($data);
        $user = $this->getUser();
        $data['author'] = $user['username'];
        $data['author_id'] = $user['_id'];
        $data['created_at'] = date(DATETIME_FORMAT);
        $data['updated_at'] = date(DATETIME_FORMAT);

        $id = $this->generateId($data);

        app()->db()->write($this->getCollectionName(), $id, $data);

        $this->redirectAdmin($this->getAdminPath());
    }

    public function editItem($params) {
        $prefix = $this->getPermissionPrefix();
        $access = $this->requirePermission($prefix . '.edit');
        if ($access === false) return;

        $id = isset($params['id']) ? $params['id'] : '';
        $item = app()->db()->read($this->getCollectionName(), $id);

        if (!$item) {
            http_response_code(404);
            return $this->renderAdmin('Not Found',
                '<div class="alert alert-danger alert-permanent">'
                . e($this->getContentType()) . ' not found</div>');
        }

        // Ownership check when access is 'own'
        if ($access === 'own' && !$this->checkOwnership($item)) {
            return;
        }

        $content = $this->renderView($this->getEditTemplate(), array(
            strtolower($this->getContentType()) => $item,
            'isNew' => false,
            'csrf_token' => app()->auth()->generateCsrfToken()
        ));

        $title = t($this->getDomain() . '.edit_' . strtolower($this->getContentType()));

        return $this->renderAdmin($title, $content, array(
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ));
    }

    public function updateItem($params) {
        $prefix = $this->getPermissionPrefix();
        $access = $this->requirePermission($prefix . '.edit');
        if ($access === false) return;
        if (!$this->verifyCsrf()) {
            return;
        }

        $id = isset($params['id']) ? $params['id'] : '';
        $item = app()->db()->read($this->getCollectionName(), $id);

        if (!$item) {
            http_response_code(404);
            return $this->renderAdmin('Not Found',
                '<div class="alert alert-danger alert-permanent">'
                . e($this->getContentType()) . ' not found</div>');
        }

        // Ownership check when access is 'own'
        if ($access === 'own' && !$this->checkOwnership($item)) {
            return;
        }

        $data = $this->extractFormData();
        $data = $this->ensureSlug($data);
        $data['updated_at'] = date(DATETIME_FORMAT);

        // Preserve original fields
        $data['author'] = $item['author'];
        $data['author_id'] = $item['author_id'];
        $data['created_at'] = $item['created_at'];

        app()->db()->write($this->getCollectionName(), $id, $data);

        $this->redirectAdmin($this->getAdminPath());
    }

    public function deleteItem($params) {
        $prefix = $this->getPermissionPrefix();
        $access = $this->requirePermission($prefix . '.delete');
        if ($access === false) return;
        if (!$this->verifyCsrf()) {
            return;
        }

        $id = isset($params['id']) ? $params['id'] : '';
        $item = app()->db()->read($this->getCollectionName(), $id);

        if (!$item) {
            $this->redirectAdmin($this->getAdminPath());
            return;
        }

        // Ownership check when access is 'own'
        if ($access === 'own' && !$this->checkOwnership($item)) {
            return;
        }

        app()->db()->delete($this->getCollectionName(), $id);

        $this->redirectAdmin($this->getAdminPath());
    }
}
