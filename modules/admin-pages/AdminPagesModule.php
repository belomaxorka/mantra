<?php

class AdminPagesModule extends Module {

    public function init() {
        // Sidebar item
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'pages',
                'title' => array('key' => 'admin.pages.title', 'fallback' => 'Pages'),
                'icon' => 'bi-file-earmark-text',
                'group' => array('key' => 'admin.sidebar.group.content', 'fallback' => 'Content'),
                'order' => 10,
                'url' => base_url('/admin/pages'),
            );

            return $items;
        });

        // Quick action
        app()->hooks()->register('admin.quick_actions', function ($actions) {
            if (!is_array($actions)) {
                $actions = array();
            }

            $actions[] = array(
                'id' => 'new-page',
                'title' => 'New Page',
                'icon' => 'bi-file-earmark-plus',
                'url' => base_url('/admin/pages/new'),
                'order' => 20,
            );

            return $actions;
        });

        // Register routes
        app()->hooks()->register('routes.register', function ($data) {
            $admin = app()->modules()->getModule('admin');
            if ($admin && method_exists($admin, 'adminRoute')) {
                $admin->adminRoute('GET', 'pages', array($this, 'listPages'));
                $admin->adminRoute('GET', 'pages/new', array($this, 'newPage'));
                $admin->adminRoute('POST', 'pages/new', array($this, 'createPage'));
                $admin->adminRoute('GET', 'pages/edit/{id}', array($this, 'editPage'));
                $admin->adminRoute('POST', 'pages/edit/{id}', array($this, 'updatePage'));
                $admin->adminRoute('POST', 'pages/delete/{id}', array($this, 'deletePage'));
            }
        });
    }

    public function listPages() {
        $admin = app()->modules()->getModule('admin');
        $db = new Database();

        $pages = $db->query('pages', array(), array(
            'sort' => 'updated_at',
            'order' => 'desc'
        ));

        $view = new View();
        $content = $view->fetch('admin-pages:list', array(
            'pages' => $pages
        ));

        return $admin->render('Pages', $content);
    }

    public function newPage() {
        $admin = app()->modules()->getModule('admin');

        $view = new View();
        $content = $view->fetch('admin-pages:edit', array(
            'page' => array(
                'title' => '',
                'slug' => '',
                'content' => '',
                'status' => 'draft',
                'image' => '',
                'show_in_navigation' => false,
                'navigation_order' => 50
            ),
            'isNew' => true,
            'csrf_token' => auth()->generateCsrfToken()
        ));

        return $admin->render('New Page', $content);
    }

    public function createPage() {
        $token = (string)request()->post('csrf_token', '');
        if (!auth()->verifyCsrfToken($token)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $db = new Database();
        $user = auth()->user();

        $title = trim((string)request()->post('title', ''));
        $slug = trim((string)request()->post('slug', ''));
        $content = (string)request()->post('content', '');
        $status = (string)request()->post('status', 'draft');
        $image = trim((string)request()->post('image', ''));
        $showInNav = (bool)request()->post('show_in_navigation', false);
        $navOrder = (int)request()->post('navigation_order', 50);

        if (empty($title)) {
            http_response_code(400);
            echo 'Title is required';
            return;
        }

        if (empty($slug)) {
            $slug = $this->slugify($title);
        }

        $id = $slug;
        if ($db->exists('pages', $id)) {
            $id = $slug . '-' . uniqid();
        }

        $pageData = array(
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'status' => in_array($status, array('draft', 'published')) ? $status : 'draft',
            'image' => $image,
            'show_in_navigation' => $showInNav,
            'navigation_order' => $navOrder,
            'author' => isset($user['username']) ? $user['username'] : 'Unknown'
        );

        $db->write('pages', $id, $pageData);

        redirect(base_url('/admin/pages'));
    }

    public function editPage($params) {
        $admin = app()->modules()->getModule('admin');
        $db = new Database();

        $id = isset($params['id']) ? $params['id'] : '';
        $page = $db->read('pages', $id);

        if (!$page) {
            http_response_code(404);
            return $admin->render('Not Found', '<div class="alert alert-danger">Page not found</div>');
        }

        $view = new View();
        $content = $view->fetch('admin-pages:edit', array(
            'page' => $page,
            'isNew' => false,
            'csrf_token' => auth()->generateCsrfToken()
        ));

        return $admin->render('Edit Page', $content);
    }

    public function updatePage($params) {
        $token = (string)request()->post('csrf_token', '');
        if (!auth()->verifyCsrfToken($token)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $db = new Database();
        $id = isset($params['id']) ? $params['id'] : '';

        $page = $db->read('pages', $id);
        if (!$page) {
            http_response_code(404);
            echo 'Page not found';
            return;
        }

        $title = trim((string)request()->post('title', ''));
        $slug = trim((string)request()->post('slug', ''));
        $content = (string)request()->post('content', '');
        $status = (string)request()->post('status', 'draft');
        $image = trim((string)request()->post('image', ''));
        $showInNav = (bool)request()->post('show_in_navigation', false);
        $navOrder = (int)request()->post('navigation_order', 50);

        if (empty($title)) {
            http_response_code(400);
            echo 'Title is required';
            return;
        }

        if (empty($slug)) {
            $slug = $this->slugify($title);
        }

        $page['title'] = $title;
        $page['slug'] = $slug;
        $page['content'] = $content;
        $page['status'] = in_array($status, array('draft', 'published')) ? $status : 'draft';
        $page['image'] = $image;
        $page['show_in_navigation'] = $showInNav;
        $page['navigation_order'] = $navOrder;

        $db->write('pages', $id, $page);

        redirect(base_url('/admin/pages'));
    }

    public function deletePage($params) {
        $token = (string)request()->post('csrf_token', '');
        if (!auth()->verifyCsrfToken($token)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $db = new Database();
        $id = isset($params['id']) ? $params['id'] : '';

        if ($db->exists('pages', $id)) {
            $db->delete('pages', $id);
        }

        redirect(base_url('/admin/pages'));
    }

    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        if (empty($text)) {
            return 'page-' . uniqid();
        }

        return $text;
    }
}
