<?php declare(strict_types=1);
/**
 * ContentAdminModule - Base class for content management modules
 *
 * Provides CRUD operations for content types (pages, posts, etc.)
 * Reduces code duplication in admin modules
 */

namespace Module;

abstract class ContentAdminModule extends BaseAdminModule
{

    /**
     * Get content type name (singular)
     * @return string
     */
    abstract protected function getContentType();

    /**
     * Get collection name for database
     * @return string
     */
    abstract protected function getCollectionName();

    /**
     * Get default item data
     * @return array
     */
    abstract protected function getDefaultItem();

    /**
     * Extract form data from request
     * @return array
     */
    abstract protected function extractFormData();

    /**
     * Get admin path for redirects (without /admin prefix)
     * Override this if module ID differs from route path
     * @return string
     */
    protected function getAdminPath()
    {
        return $this->getCollectionName();
    }

    /**
     * Initialize module — registers CRUD routes + manifest sidebar/quick_actions.
     * Subclasses should call parent::init() before adding custom hooks.
     */
    public function init(): void
    {
        parent::init();
        $this->registerCrudRoutes();
    }

    /**
     * Register standard CRUD routes for this content type.
     *
     * Registers: list, new (GET+POST), edit (GET+POST), delete (POST).
     * Override to add/remove routes or change patterns.
     */
    protected function registerCrudRoutes(): void
    {
        $path = $this->getAdminPath();

        $this->registerAdminRoute('GET', $path, [$this, 'listItems']);
        $this->registerAdminRoute('GET', $path . '/new', [$this, 'newItem']);
        $this->registerAdminRoute('POST', $path . '/new', [$this, 'createItem']);
        $this->registerAdminRoute('GET', $path . '/edit/{id}', [$this, 'editItem']);
        $this->registerAdminRoute('POST', $path . '/edit/{id}', [$this, 'updateItem']);
        $this->registerAdminRoute('POST', $path . '/delete/{id}', [$this, 'deleteItem']);
    }

    /**
     * Get list view template
     * @return string
     */
    protected function getListTemplate()
    {
        return $this->getId() . ':list';
    }

    /**
     * Get edit view template
     * @return string
     */
    protected function getEditTemplate()
    {
        return $this->getId() . ':edit';
    }

    /**
     * Generate ID for new item
     * @param array $data Item data (must have slug already set via ensureSlug)
     * @return string
     */
    protected function generateId($data)
    {
        $slug = $data['slug'];

        $id = $slug;
        if (app()->db()->exists($this->getCollectionName(), $id)) {
            $id = $slug . '-' . uniqid();
        }

        return $id;
    }

    /**
     * Ensure slug is set, generate from title if empty
     * @param array $data Item data
     * @return array Modified data with slug
     */
    protected function ensureSlug($data)
    {
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = slugify($data['title']);
        } elseif (!empty($data['slug'])) {
            $data['slug'] = slugify($data['slug']);
        }
        return $data;
    }

    /**
     * List all items
     */
    public function listItems()
    {
        $items = app()->db()->query($this->getCollectionName(), [], [
            'sort' => 'updated_at',
            'order' => 'desc',
        ]);

        $content = $this->renderView($this->getListTemplate(), [
            strtolower($this->getCollectionName()) => $items,
        ]);

        $titleKey = $this->getId() . '.title';
        $title = t($titleKey);

        return $this->renderAdmin($title, $content);
    }

    /**
     * Show new item form
     */
    public function newItem()
    {
        $templateData = [
            strtolower($this->getContentType()) => $this->getDefaultItem(),
            'isNew' => true,
            'csrf_token' => app()->auth()->generateCsrfToken(),
        ];
        $templateData = $this->fireHook('admin.' . $this->getCollectionName() . '.edit.data', $templateData);

        $content = $this->renderView($this->getEditTemplate(), $templateData);

        $titleKey = $this->getId() . '.new';
        $title = t($titleKey);

        return $this->renderAdmin($title, $content);
    }

    /**
     * Create new item
     */
    public function createItem(): void
    {
        if (!$this->verifyCsrf()) {
            return;
        }

        $data = $this->extractFormData();
        $data = $this->fireHook('admin.' . $this->getCollectionName() . '.form_data', $data);
        $data = $this->ensureSlug($data);
        $user = $this->getUser();
        $data['author'] = $user['username'] ?? 'Unknown';
        $data['author_id'] = $user['_id'] ?? '';
        $data['created_at'] = clock()->timestamp();
        $data['updated_at'] = clock()->timestamp();

        $id = $this->generateId($data);

        app()->db()->write($this->getCollectionName(), $id, $data);

        $this->redirectAdmin($this->getAdminPath());
    }

    /**
     * Show edit item form
     */
    public function editItem($params)
    {
        $id = $params['id'] ?? '';
        $item = app()->db()->read($this->getCollectionName(), $id);

        if (!$item) {
            http_response_code(404);
            return $this->renderAdmin('Not Found', '<div class="alert alert-danger alert-dismissible fade show alert-permanent" role="alert">' . $this->getContentType() . ' not found<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
        }

        $templateData = [
            strtolower($this->getContentType()) => $item,
            'isNew' => false,
            'csrf_token' => app()->auth()->generateCsrfToken(),
        ];
        $templateData = $this->fireHook('admin.' . $this->getCollectionName() . '.edit.data', $templateData);

        $content = $this->renderView($this->getEditTemplate(), $templateData);

        // Try module-specific edit key first (e.g., admin-posts.edit_post, admin-pages.edit_page)
        $titleKey = $this->getId() . '.edit_' . strtolower($this->getContentType());
        $title = t($titleKey);

        return $this->renderAdmin($title, $content);
    }

    /**
     * Update existing item
     */
    public function updateItem($params)
    {
        if (!$this->verifyCsrf()) {
            return;
        }

        $id = $params['id'] ?? '';
        $item = app()->db()->read($this->getCollectionName(), $id);

        if (!$item) {
            http_response_code(404);
            return $this->renderAdmin('Not Found', '<div class="alert alert-danger alert-dismissible fade show alert-permanent" role="alert">' . e($this->getContentType()) . ' not found<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
        }

        $data = $this->extractFormData();
        $data = $this->fireHook('admin.' . $this->getCollectionName() . '.form_data', $data);
        $data = $this->ensureSlug($data);
        $data['updated_at'] = clock()->timestamp();

        // Preserve original fields
        $data['author'] = $item['author'] ?? 'Unknown';
        $data['author_id'] = $item['author_id'] ?? '';
        $data['created_at'] = $item['created_at'] ?? clock()->timestamp();

        app()->db()->write($this->getCollectionName(), $id, $data);

        $this->redirectAdmin($this->getAdminPath());
    }

    /**
     * Delete item
     */
    public function deleteItem($params): void
    {
        if (!$this->verifyCsrf()) {
            return;
        }

        $id = $params['id'] ?? '';

        if (app()->db()->exists($this->getCollectionName(), $id)) {
            app()->db()->delete($this->getCollectionName(), $id);
        }

        $this->redirectAdmin($this->getAdminPath());
    }
}
