<?php

use Module\BaseAdminModule;

class CategoriesModule extends BaseAdminModule
{
    public function init()
    {
        parent::init();

        // Register schema from module directory
        app()->db()->registerSchema('categories', $this->getPath() . '/schema.php');

        // Register translations
        app()->translator()->registerDomain('categories', $this->getPath() . '/lang');

        // Admin CRUD routes
        $this->registerAdminRoute('GET',  'categories',              array($this, 'listCategories'));
        $this->registerAdminRoute('GET',  'categories/new',          array($this, 'newCategory'));
        $this->registerAdminRoute('POST', 'categories/new',          array($this, 'createCategory'));
        $this->registerAdminRoute('GET',  'categories/edit/{id}',    array($this, 'editCategory'));
        $this->registerAdminRoute('POST', 'categories/edit/{id}',    array($this, 'updateCategory'));
        $this->registerAdminRoute('POST', 'categories/delete/{id}',  array($this, 'deleteCategory'));

        // Permissions
        $this->hook('permissions.register', array($this, 'registerPermissions'));

        // Post edit form integration
        $this->hook('admin.posts.edit.sidebar', array($this, 'renderCategorySelector'));
        $this->hook('admin.posts.form_data', array($this, 'extractCategoryField'));

        // Post list integration
        $this->hook('admin.posts.list.columns.head', array($this, 'renderCategoryColumnHead'));
        $this->hook('admin.posts.list.columns.body', array($this, 'renderCategoryColumnBody'));

        // Public route
        $this->hook('routes.register', array($this, 'registerPublicRoutes'));

        // Enrich single post with category info
        $this->hook('post.single.data', array($this, 'enrichPostCategory'));
    }

    // ========== Permissions ==========

    public function registerPermissions($registry)
    {
        $registry->registerPermissions(array(
            'categories.view'   => 'View categories',
            'categories.create' => 'Create categories',
            'categories.edit'   => 'Edit categories',
            'categories.delete' => 'Delete categories',
        ), 'Categories');

        $registry->addRoleDefaults('editor', array(
            'categories.view', 'categories.create', 'categories.edit',
        ));
        $registry->addRoleDefaults('viewer', array(
            'categories.view',
        ));

        return $registry;
    }

    // ========== Admin CRUD ==========

    public function listCategories()
    {
        $categories = app()->db()->query('categories', array(), array(
            'sort' => 'order',
            'order' => 'asc',
        ));

        // Count posts per category
        $posts = app()->db()->query('posts', array(), array());
        $counts = array();
        foreach ($posts as $p) {
            $cat = isset($p['category']) ? $p['category'] : '';
            if ($cat !== '') {
                $counts[$cat] = isset($counts[$cat]) ? $counts[$cat] + 1 : 1;
            }
        }

        $content = $this->renderView('categories:admin-list', array(
            'categories' => $categories,
            'counts' => $counts,
        ));

        return $this->renderAdmin(t('categories.title'), $content, array(
            'breadcrumbs' => array(
                array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
                array('title' => t('categories.title')),
            ),
        ));
    }

    public function newCategory()
    {
        $content = $this->renderView('categories:admin-edit', array(
            'category' => array(
                'title' => '',
                'slug' => '',
                'description' => '',
                'order' => 0,
            ),
            'isNew' => true,
            'csrf_token' => app()->auth()->generateCsrfToken(),
        ));

        return $this->renderAdmin(t('categories.new_category'), $content, array(
            'breadcrumbs' => array(
                array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
                array('title' => t('categories.title'), 'url' => base_url('/admin/categories')),
                array('title' => t('categories.new_category')),
            ),
        ));
    }

    public function createCategory()
    {
        if (!$this->verifyCsrf()) return;

        $data = array(
            'title' => app()->request()->postTrimmed('title'),
            'slug' => app()->request()->postTrimmed('slug'),
            'description' => app()->request()->post('description', ''),
            'order' => (int)app()->request()->post('order', 0),
        );

        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = slugify($data['title']);
        } elseif (!empty($data['slug'])) {
            $data['slug'] = slugify($data['slug']);
        }

        $data['created_at'] = clock()->timestamp();
        $data['updated_at'] = clock()->timestamp();

        $id = $data['slug'];
        if (app()->db()->exists('categories', $id)) {
            $id = $data['slug'] . '-' . uniqid();
        }

        app()->db()->write('categories', $id, $data);

        $this->redirectAdmin('categories');
    }

    public function editCategory($params)
    {
        $id = isset($params['id']) ? $params['id'] : '';
        $category = app()->db()->read('categories', $id);

        if (!$category) {
            http_response_code(404);
            return $this->renderAdmin('Not Found',
                '<div class="alert alert-danger alert-permanent">Category not found</div>');
        }

        $content = $this->renderView('categories:admin-edit', array(
            'category' => $category,
            'isNew' => false,
            'csrf_token' => app()->auth()->generateCsrfToken(),
        ));

        return $this->renderAdmin(t('categories.edit_category'), $content, array(
            'breadcrumbs' => array(
                array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
                array('title' => t('categories.title'), 'url' => base_url('/admin/categories')),
                array('title' => t('categories.edit_category')),
            ),
        ));
    }

    public function updateCategory($params)
    {
        if (!$this->verifyCsrf()) return;

        $id = isset($params['id']) ? $params['id'] : '';
        $existing = app()->db()->read('categories', $id);

        if (!$existing) {
            $this->redirectAdmin('categories');
            return;
        }

        $data = array(
            'title' => app()->request()->postTrimmed('title'),
            'slug' => app()->request()->postTrimmed('slug'),
            'description' => app()->request()->post('description', ''),
            'order' => (int)app()->request()->post('order', 0),
        );

        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = slugify($data['title']);
        } elseif (!empty($data['slug'])) {
            $data['slug'] = slugify($data['slug']);
        }

        $data['created_at'] = $existing['created_at'];
        $data['updated_at'] = clock()->timestamp();

        app()->db()->write('categories', $id, $data);

        $this->redirectAdmin('categories');
    }

    public function deleteCategory($params)
    {
        if (!$this->verifyCsrf()) return;

        $id = isset($params['id']) ? $params['id'] : '';
        app()->db()->delete('categories', $id);

        $this->redirectAdmin('categories');
    }

    // ========== Post Edit Integration ==========

    public function renderCategorySelector($html, $post)
    {
        $currentCategory = isset($post['category']) ? $post['category'] : '';

        $categories = app()->db()->query('categories', array(), array(
            'sort' => 'order',
            'order' => 'asc',
        ));

        return $html . partial('categories:category-selector', array(
            'categories' => $categories,
            'currentCategory' => $currentCategory,
        ));
    }

    public function extractCategoryField($data)
    {
        $data['category'] = app()->request()->post('category', '');
        return $data;
    }

    // ========== Post List Integration ==========

    public function renderCategoryColumnHead($html)
    {
        return $html . '<th>' . t('categories.category') . '</th>';
    }

    public function renderCategoryColumnBody($html, $post)
    {
        $cat = isset($post['category']) && $post['category'] !== '' ? e($post['category']) : '-';
        return $html . '<td>' . $cat . '</td>';
    }

    // ========== Public Route ==========

    public function registerPublicRoutes($data)
    {
        $this->route('GET', '/category/{slug}', array($this, 'categoryPage'));
        return $data;
    }

    public function categoryPage($params)
    {
        $slug = isset($params['slug']) ? $params['slug'] : '';

        $categories = app()->db()->query('categories', array('slug' => $slug));
        if (empty($categories)) {
            abort(404);
            return;
        }
        $category = $categories[0];

        $perPage = (int)config('content.posts_per_page', 10);
        $page = max(1, (int)app()->request()->query('page', 1));

        $total = app()->db()->count('posts', array(
            'status' => 'published',
            'category' => $slug,
        ));
        $paginator = new Paginator($total, $perPage, $page);

        $posts = app()->db()->query('posts', array(
            'status' => 'published',
            'category' => $slug,
        ), array(
            'sort' => 'created_at',
            'order' => 'desc',
            'limit' => $paginator->perPage(),
            'offset' => $paginator->offset(),
        ));

        $data = array(
            'category' => $category,
            'posts' => $posts,
            'paginator' => $paginator,
            'title' => $category['title'] . ' - ' . config('site.name', 'Mantra CMS'),
        );

        // Template fallback: theme category.php → module template (with site layout)
        $theme = config('theme.active', 'default');
        $themePath = MANTRA_THEMES . '/' . $theme . '/templates';

        if (file_exists($themePath . '/category-' . $slug . '.php')) {
            app()->view()->render('category-' . $slug, $data);
        } elseif (file_exists($themePath . '/category.php')) {
            app()->view()->render('category', $data);
        } else {
            app()->view()->render('categories:category', $data, array('layout' => true));
        }
    }

    // ========== Post Enrichment ==========

    public function enrichPostCategory($data)
    {
        if (!isset($data['post']['category']) || $data['post']['category'] === '') {
            return $data;
        }

        $slug = $data['post']['category'];
        $categories = app()->db()->query('categories', array('slug' => $slug));
        if (!empty($categories)) {
            $data['categoryInfo'] = $categories[0];
        }

        return $data;
    }
}
