<?php declare(strict_types=1);
/**
 * ContentPanel - CRUD scaffolding for admin panels
 *
 * Panel equivalent of ContentAdminModule. Implement the four abstract
 * methods and you get a full list/create/edit/delete admin interface.
 */

namespace Admin;

abstract class ContentPanel extends AdminPanel
{
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
    protected function getAdminPath()
    {
        return $this->getCollectionName();
    }

    /**
     * Template name for the list view (default: 'list')
     * @return string
     */
    protected function getListTemplate()
    {
        return 'list';
    }

    /**
     * Template name for the edit/new view (default: 'edit')
     * @return string
     */
    protected function getEditTemplate()
    {
        return 'edit';
    }

    // ========== Hook Registration ==========

    /**
     * Register standard content panel hooks in HookRegistry.
     * Call from init() so hooks appear on the /admin/hooks page.
     */
    protected function registerPanelHooks(): void
    {
        $c = $this->getCollectionName();
        $type = ucfirst($this->getContentType());
        $source = $this->id();

        \HookRegistry::define('admin.' . $c . '.edit.data',
            'Modify template data for the ' . $c . ' edit form',
            'array', 'array', ['source' => $source]);

        \HookRegistry::define('admin.' . $c . '.form_data',
            'Modify extracted form data before saving ' . strtolower($type),
            'array', 'array', ['source' => $source]);

        \HookRegistry::define('admin.' . $c . '.edit.sidebar',
            'Inject HTML into the ' . $c . ' edit form sidebar',
            'string', 'string', ['source' => $source, 'context' => 'array (the ' . strtolower($type) . ' item)']);

        \HookRegistry::define('admin.' . $c . '.list.columns.head',
            'Inject <th> elements into the ' . $c . ' list table header',
            'string', 'string', ['source' => $source]);

        \HookRegistry::define('admin.' . $c . '.list.columns.body',
            'Inject <td> elements into the ' . $c . ' list table row',
            'string', 'string', ['source' => $source, 'context' => 'array (the ' . strtolower($type) . ' item)']);
    }

    // ========== Route Registration ==========

    /**
     * Register standard CRUD routes.
     * Override to add/remove routes.
     */
    public function registerRoutes($admin): void
    {
        $path = $this->getAdminPath();

        $admin->adminRoute('GET', $path, [$this, 'listItems']);
        $admin->adminRoute('GET', $path . '/new', [$this, 'newItem']);
        $admin->adminRoute('POST', $path . '/new', [$this, 'createItem']);
        $admin->adminRoute('GET', $path . '/edit/{id}', [$this, 'editItem']);
        $admin->adminRoute('POST', $path . '/edit/{id}', [$this, 'updateItem']);
        $admin->adminRoute('POST', $path . '/delete/{id}', [$this, 'deleteItem']);
        $admin->adminRoute('POST', $path . '/preview', [$this, 'previewItem']);
    }

    // ========== Helpers ==========

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
     * Check whether the given slug is unique within the collection.
     *
     * @param string $slug      Slug to check
     * @param string|null $excludeId  Document ID to exclude (for updates)
     * @return bool
     */
    protected function isSlugUnique($slug, $excludeId = null)
    {
        $existing = app()->db()->query($this->getCollectionName(), ['slug' => $slug]);

        foreach ($existing as $item) {
            if ($excludeId !== null && ($item['_id'] ?? '') === $excludeId) {
                continue;
            }
            return false;
        }

        return true;
    }

    /**
     * Re-render the edit form with an error message.
     *
     * @param array  $data  Form data to pre-fill
     * @param string $error Error message
     * @param bool   $isNew Whether this is a create (true) or edit (false) form
     */
    protected function renderFormWithError($data, $error, $isNew = true): void
    {
        $templateData = [
            strtolower($this->getContentType()) => $data,
            'isNew' => $isNew,
            'csrf_token' => app()->auth()->generateCsrfToken(),
            'error' => $error,
        ];
        $templateData = $this->fireHook('admin.' . $this->getCollectionName() . '.edit.data', $templateData);

        $content = $this->renderView($this->getEditTemplate(), $templateData);

        $title = $isNew
            ? t($this->getDomain() . '.new')
            : t($this->getDomain() . '.edit_' . strtolower($this->getContentType()));

        echo $this->renderAdmin($title, $content, [
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ]);
    }

    /**
     * Translation domain for this panel (default: "admin-{id}")
     * @return string
     */
    protected function getDomain()
    {
        return 'admin-' . $this->id();
    }

    // ========== Breadcrumbs ==========

    protected function getListBreadcrumbs()
    {
        return [
            ['title' => t('admin-dashboard.title'), 'url' => base_url('/admin')],
            ['title' => t($this->getDomain() . '.title')],
        ];
    }

    protected function getItemBreadcrumbs($itemTitle)
    {
        return [
            ['title' => t('admin-dashboard.title'), 'url' => base_url('/admin')],
            ['title' => t($this->getDomain() . '.title'), 'url' => base_url('/admin/' . $this->getAdminPath())],
            ['title' => $itemTitle],
        ];
    }

    // ========== Ownership Check ==========

    /**
     * Verify that the current user owns the given content item.
     * Renders 403 and returns false if not.
     *
     * @param array $item Content item with 'author' field
     * @return bool
     */
    protected function checkOwnership($item)
    {
        $userManager = new \User();
        if ($userManager->canEdit($this->getUser(), $item)) {
            return true;
        }
        $this->renderErrorPage(t('admin.common.access_denied'));
        return false;
    }

    // ========== Permission Helpers ==========

    /**
     * Permission prefix for this panel (e.g. 'posts', 'pages').
     */
    protected function getPermissionPrefix()
    {
        return $this->getCollectionName();
    }

    /**
     * Check permission flags for list views.
     */
    protected function getPermissionFlags()
    {
        $userManager = new \User();
        $user = $this->getUser();
        $prefix = $this->getPermissionPrefix();
        return [
            'canCreate' => $userManager->hasPermission($user, $prefix . '.create'),
            'canEdit' => $userManager->hasPermission($user, $prefix . '.edit'),
            'canDelete' => $userManager->hasPermission($user, $prefix . '.delete'),
        ];
    }

    // ========== CRUD Actions ==========

    public function listItems()
    {
        $prefix = $this->getPermissionPrefix();
        if (!$this->requirePermission($prefix . '.view')) return;

        $perPage = 25;
        $page = max(1, (int)app()->request()->query('page', 1));
        $total = app()->db()->count($this->getCollectionName());
        $paginator = new \Paginator($total, $perPage, $page);

        $items = app()->db()->query($this->getCollectionName(), [], [
            'sort' => 'updated_at',
            'order' => 'desc',
            'limit' => $paginator->perPage(),
            'offset' => $paginator->offset(),
        ]);

        $content = $this->renderView($this->getListTemplate(), array_merge(
            [
                strtolower($this->getCollectionName()) => $items,
                'paginator' => $paginator,
            ],
            $this->getPermissionFlags(),
        ));

        $title = t($this->getDomain() . '.title');

        return $this->renderAdmin($title, $content, [
            'breadcrumbs' => $this->getListBreadcrumbs(),
        ]);
    }

    public function newItem()
    {
        $prefix = $this->getPermissionPrefix();
        if (!$this->requirePermission($prefix . '.create')) return;

        $templateData = [
            strtolower($this->getContentType()) => $this->getDefaultItem(),
            'isNew' => true,
            'csrf_token' => app()->auth()->generateCsrfToken(),
        ];
        $templateData = $this->fireHook('admin.' . $this->getCollectionName() . '.edit.data', $templateData);

        $content = $this->renderView($this->getEditTemplate(), $templateData);

        $title = t($this->getDomain() . '.new');

        return $this->renderAdmin($title, $content, [
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ]);
    }

    public function createItem(): void
    {
        $prefix = $this->getPermissionPrefix();
        if (!$this->requirePermission($prefix . '.create')) return;

        $data = $this->extractFormData();
        $data = $this->fireHook('admin.' . $this->getCollectionName() . '.form_data', $data);
        $data = $this->ensureSlug($data);

        if (!empty($data['slug']) && !$this->isSlugUnique($data['slug'])) {
            $this->renderFormWithError($data, t('admin.common.slug_exists'), true);
            return;
        }

        $user = $this->getUser();
        $data['author'] = $user['username'];
        $data['author_id'] = $user['_id'];
        $data['created_at'] = clock()->timestamp();
        $data['updated_at'] = clock()->timestamp();

        app()->db()->write($this->getCollectionName(), $data['slug'], $data);

        app()->hooks()->fire('content.saved', [
            'collection' => $this->getCollectionName(),
            'id' => $data['slug'],
            'action' => 'create',
        ]);

        app()->session()->flash('success', t('admin.common.created'));
        $this->redirectAdmin($this->getAdminPath());
    }

    public function editItem($params)
    {
        $prefix = $this->getPermissionPrefix();
        $access = $this->requirePermission($prefix . '.edit');
        if ($access === false) return;

        $id = $params['id'] ?? '';
        $item = app()->db()->read($this->getCollectionName(), $id);

        if (!$item) {
            $this->renderErrorPage(e($this->getContentType()) . ' not found', 404);
            return;
        }

        // Ownership check when access is 'own'
        if ($access === 'own' && !$this->checkOwnership($item)) {
            return;
        }

        $templateData = [
            strtolower($this->getContentType()) => $item,
            'isNew' => false,
            'csrf_token' => app()->auth()->generateCsrfToken(),
        ];
        $templateData = $this->fireHook('admin.' . $this->getCollectionName() . '.edit.data', $templateData);

        $content = $this->renderView($this->getEditTemplate(), $templateData);

        $title = t($this->getDomain() . '.edit_' . strtolower($this->getContentType()));

        return $this->renderAdmin($title, $content, [
            'breadcrumbs' => $this->getItemBreadcrumbs($title),
        ]);
    }

    public function updateItem($params): void
    {
        $prefix = $this->getPermissionPrefix();
        $access = $this->requirePermission($prefix . '.edit');
        if ($access === false) return;

        $id = $params['id'] ?? '';
        $item = app()->db()->read($this->getCollectionName(), $id);

        if (!$item) {
            $this->renderErrorPage(e($this->getContentType()) . ' not found', 404);
            return;
        }

        // Ownership check when access is 'own'
        if ($access === 'own' && !$this->checkOwnership($item)) {
            return;
        }

        $data = $this->extractFormData();
        $data = $this->fireHook('admin.' . $this->getCollectionName() . '.form_data', $data);
        $data = $this->ensureSlug($data);

        if (!empty($data['slug']) && !$this->isSlugUnique($data['slug'], $id)) {
            $data['_id'] = $id;
            $data['author'] = $item['author'];
            $data['author_id'] = $item['author_id'];
            $data['created_at'] = $item['created_at'];
            $this->renderFormWithError($data, t('admin.common.slug_exists'), false);
            return;
        }

        $data['updated_at'] = clock()->timestamp();

        // Preserve original fields
        $data['author'] = $item['author'];
        $data['author_id'] = $item['author_id'];
        $data['created_at'] = $item['created_at'];

        app()->db()->write($this->getCollectionName(), $id, $data);

        app()->hooks()->fire('content.saved', [
            'collection' => $this->getCollectionName(),
            'id' => $id,
            'action' => 'update',
        ]);

        app()->session()->flash('success', t('admin.common.updated'));
        $this->redirectAdmin($this->getAdminPath());
    }

    public function deleteItem($params): void
    {
        $prefix = $this->getPermissionPrefix();
        $access = $this->requirePermission($prefix . '.delete');
        if ($access === false) return;

        $id = $params['id'] ?? '';
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

        app()->hooks()->fire('content.deleted', [
            'collection' => $this->getCollectionName(),
            'id' => $id,
        ]);

        app()->session()->flash('success', t('admin.common.deleted'));
        $this->redirectAdmin($this->getAdminPath());
    }

    // ========== Preview ==========

    public function previewItem($params = []): void
    {
        $prefix = $this->getPermissionPrefix();
        if (!$this->requirePermission($prefix . '.view')) return;

        $data = $this->extractFormData();
        $data = $this->fireHook('admin.' . $this->getCollectionName() . '.form_data', $data);
        $data = $this->ensureSlug($data);

        // Merge with existing item data when editing (for author, timestamps, etc.)
        $previewId = app()->request()->post('_preview_id', '');
        if ($previewId !== '') {
            $existing = app()->db()->read($this->getCollectionName(), $previewId);
            if ($existing) {
                $data = array_merge($existing, $data);
            }
        }

        // Fill defaults for missing fields
        $defaults = $this->getDefaultItem();
        foreach ($defaults as $key => $value) {
            if (!isset($data[$key]) || $data[$key] === '') {
                $data[$key] = $value;
            }
        }

        // Ensure author info
        if (empty($data['author'])) {
            $user = $this->getUser();
            $data['author'] = $user['username'];
            $data['author_id'] = $user['_id'];
        }
        if (empty($data['created_at'])) {
            $data['created_at'] = clock()->timestamp();
        }

        $this->renderPreview($data);
    }

    /**
     * Render a public-facing preview of the content item.
     * Override in subclass to provide actual rendering.
     */
    protected function renderPreview($data): void
    {
        abort(404);
    }

    /**
     * Find the first existing theme template from a list of candidates.
     */
    protected function resolveThemeTemplate($candidates)
    {
        $theme = config('theme.active', 'default');
        $themePath = MANTRA_THEMES . '/' . $theme;

        foreach ($candidates as $template) {
            if (file_exists($themePath . '/templates/' . $template . '.php')) {
                return $template;
            }
        }

        return end($candidates);
    }

    /**
     * Inject a preview banner into the rendered HTML output.
     */
    protected function injectPreviewBanner($html)
    {
        $banner = '<div id="mantra-preview-banner" style="position:fixed;top:0;left:0;right:0;z-index:999999;'
            . 'background:linear-gradient(135deg,#f0ad4e,#ec971f);color:#000;text-align:center;'
            . 'padding:12px 20px;font-family:-apple-system,BlinkMacSystemFont,sans-serif;font-size:14px;'
            . 'box-shadow:0 2px 8px rgba(0,0,0,.15);display:flex;align-items:center;justify-content:center;gap:12px;">'
            . '<svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">'
            . '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13 13 0 011.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0114.828 8a13 13 0 01-1.66 2.043C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 011.172 8z"/>'
            . '<path d="M8 5.5a2.5 2.5 0 100 5 2.5 2.5 0 000-5zM4.5 8a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z"/>'
            . '</svg>'
            . '<span>' . e(t('admin.common.preview_banner')) . '</span>'
            . '<button onclick="window.close()" style="background:#000;color:#fff;border:none;'
            . 'padding:6px 16px;border-radius:4px;cursor:pointer;font-size:13px;font-weight:500;">'
            . e(t('admin.common.close_preview')) . '</button>'
            . '</div>';
        $spacer = '<div style="height:48px;"></div>';

        return preg_replace('/(<body[^>]*>)/i', '$1' . $banner . $spacer, $html, 1);
    }
}
