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
        if (db()->exists($this->getCollectionName(), $id)) {
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

    // ========== CRUD Actions ==========

    public function listItems() {
        $items = db()->query($this->getCollectionName(), array(), array(
            'sort' => 'updated_at',
            'order' => 'desc'
        ));

        $content = $this->renderView($this->getListTemplate(), array(
            strtolower($this->getCollectionName()) => $items
        ));

        $title = t($this->getDomain() . '.title');

        return $this->renderAdmin($title, $content, array(
            'breadcrumbs' => $this->getListBreadcrumbs(),
        ));
    }

    public function newItem() {
        $content = $this->renderView($this->getEditTemplate(), array(
            strtolower($this->getContentType()) => $this->getDefaultItem(),
            'isNew' => true,
            'csrf_token' => auth()->generateCsrfToken()
        ));

        $title = t($this->getDomain() . '.new');

        return $this->renderAdmin($title, $content, array(
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ));
    }

    public function createItem() {
        if (!$this->verifyCsrf()) {
            return;
        }

        $data = $this->extractFormData();
        $data = $this->ensureSlug($data);
        $user = $this->getUser();
        $data['author'] = isset($user['username']) ? $user['username'] : 'Unknown';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = $this->generateId($data);

        db()->write($this->getCollectionName(), $id, $data);

        $this->redirectAdmin($this->getAdminPath());
    }

    public function editItem($params) {
        $id = isset($params['id']) ? $params['id'] : '';
        $item = db()->read($this->getCollectionName(), $id);

        if (!$item) {
            http_response_code(404);
            return $this->renderAdmin('Not Found',
                '<div class="alert alert-danger alert-permanent">'
                . e($this->getContentType()) . ' not found</div>');
        }

        $content = $this->renderView($this->getEditTemplate(), array(
            strtolower($this->getContentType()) => $item,
            'isNew' => false,
            'csrf_token' => auth()->generateCsrfToken()
        ));

        $title = t($this->getDomain() . '.edit_' . strtolower($this->getContentType()));

        return $this->renderAdmin($title, $content, array(
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ));
    }

    public function updateItem($params) {
        if (!$this->verifyCsrf()) {
            return;
        }

        $id = isset($params['id']) ? $params['id'] : '';
        $item = db()->read($this->getCollectionName(), $id);

        if (!$item) {
            http_response_code(404);
            return $this->renderAdmin('Not Found',
                '<div class="alert alert-danger alert-permanent">'
                . e($this->getContentType()) . ' not found</div>');
        }

        $data = $this->extractFormData();
        $data = $this->ensureSlug($data);
        $data['updated_at'] = now();

        // Preserve original fields
        $data['author'] = isset($item['author']) ? $item['author'] : 'Unknown';
        $data['created_at'] = isset($item['created_at']) ? $item['created_at'] : now();

        db()->write($this->getCollectionName(), $id, $data);

        $this->redirectAdmin($this->getAdminPath());
    }

    public function deleteItem($params) {
        if (!$this->verifyCsrf()) {
            return;
        }

        $id = isset($params['id']) ? $params['id'] : '';

        if (db()->exists($this->getCollectionName(), $id)) {
            db()->delete($this->getCollectionName(), $id);
        }

        $this->redirectAdmin($this->getAdminPath());
    }
}
