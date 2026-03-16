<?php

class AdminPostsModule extends Module {

    public function init() {
        // Sidebar item
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'posts',
                'title' => array('key' => 'admin.posts.title', 'fallback' => 'Posts'),
                'icon' => 'bi-file-earmark-text',
                'group' => array('key' => 'admin.sidebar.group.content', 'fallback' => 'Content'),
                'order' => 15,
                'url' => base_url('/admin/posts'),
            );

            return $items;
        });

        // Quick action
        app()->hooks()->register('admin.quick_actions', function ($actions) {
            if (!is_array($actions)) {
                $actions = array();
            }

            $actions[] = array(
                'id' => 'new-post',
                'title' => 'New Post',
                'icon' => 'bi-file-earmark-plus',
                'url' => base_url('/admin/posts/new'),
                'order' => 25,
            );

            return $actions;
        });

        // Register routes
        app()->hooks()->register('routes.register', function ($data) {
            $admin = app()->modules()->getModule('admin');
            if ($admin && method_exists($admin, 'adminRoute')) {
                $admin->adminRoute('GET', 'posts', array($this, 'listPosts'));
                $admin->adminRoute('GET', 'posts/new', array($this, 'newPost'));
                $admin->adminRoute('POST', 'posts/new', array($this, 'createPost'));
                $admin->adminRoute('GET', 'posts/edit/{id}', array($this, 'editPost'));
                $admin->adminRoute('POST', 'posts/edit/{id}', array($this, 'updatePost'));
                $admin->adminRoute('POST', 'posts/delete/{id}', array($this, 'deletePost'));
            }
        });
    }

    public function listPosts() {
        $admin = app()->modules()->getModule('admin');
        $db = new Database();

        $posts = $db->query('posts', array(), array(
            'sort' => 'updated_at',
            'order' => 'desc'
        ));

        $view = new View();
        $content = $view->fetch('admin-posts:list', array(
            'posts' => $posts
        ));

        return $admin->render('Posts', $content);
    }

    public function newPost() {
        $admin = app()->modules()->getModule('admin');

        $view = new View();
        $content = $view->fetch('admin-posts:edit', array(
            'post' => array(
                'title' => '',
                'slug' => '',
                'content' => '',
                'excerpt' => '',
                'status' => 'draft',
                'category' => '',
                'author' => ''
            ),
            'isNew' => true,
            'csrf_token' => auth()->generateCsrfToken()
        ));

        return $admin->render('New Post', $content);
    }

    public function createPost() {
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
        $excerpt = trim((string)request()->post('excerpt', ''));
        $status = (string)request()->post('status', 'draft');
        $category = trim((string)request()->post('category', ''));

        if (empty($title)) {
            http_response_code(400);
            echo 'Title is required';
            return;
        }

        if (empty($slug)) {
            $slug = $this->slugify($title);
        }

        $id = $slug;
        if ($db->exists('posts', $id)) {
            $id = $slug . '-' . uniqid();
        }

        $postData = array(
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'status' => in_array($status, array('draft', 'published')) ? $status : 'draft',
            'category' => $category,
            'author' => isset($user['username']) ? $user['username'] : 'Unknown'
        );

        $db->write('posts', $id, $postData);

        redirect(base_url('/admin/posts'));
    }

    public function editPost($params) {
        $admin = app()->modules()->getModule('admin');
        $db = new Database();

        $id = isset($params['id']) ? $params['id'] : '';
        $post = $db->read('posts', $id);

        if (!$post) {
            http_response_code(404);
            return $admin->render('Not Found', '<div class="alert alert-danger">Post not found</div>');
        }

        $view = new View();
        $content = $view->fetch('admin-posts:edit', array(
            'post' => $post,
            'isNew' => false,
            'csrf_token' => auth()->generateCsrfToken()
        ));

        return $admin->render('Edit Post', $content);
    }

    public function updatePost($params) {
        $token = (string)request()->post('csrf_token', '');
        if (!auth()->verifyCsrfToken($token)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $db = new Database();
        $id = isset($params['id']) ? $params['id'] : '';

        $post = $db->read('posts', $id);
        if (!$post) {
            http_response_code(404);
            echo 'Post not found';
            return;
        }

        $title = trim((string)request()->post('title', ''));
        $slug = trim((string)request()->post('slug', ''));
        $content = (string)request()->post('content', '');
        $excerpt = trim((string)request()->post('excerpt', ''));
        $status = (string)request()->post('status', 'draft');
        $category = trim((string)request()->post('category', ''));

        if (empty($title)) {
            http_response_code(400);
            echo 'Title is required';
            return;
        }

        if (empty($slug)) {
            $slug = $this->slugify($title);
        }

        $post['title'] = $title;
        $post['slug'] = $slug;
        $post['content'] = $content;
        $post['excerpt'] = $excerpt;
        $post['status'] = in_array($status, array('draft', 'published')) ? $status : 'draft';
        $post['category'] = $category;

        $db->write('posts', $id, $post);

        redirect(base_url('/admin/posts'));
    }

    public function deletePost($params) {
        $token = (string)request()->post('csrf_token', '');
        if (!auth()->verifyCsrfToken($token)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $db = new Database();
        $id = isset($params['id']) ? $params['id'] : '';

        if ($db->exists('posts', $id)) {
            $db->delete('posts', $id);
        }

        redirect(base_url('/admin/posts'));
    }

    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        if (empty($text)) {
            return 'post-' . uniqid();
        }

        return $text;
    }
}
