<?php
/**
 * PagesModule - Manages pages and posts
 */

class PagesModule extends Module {
    
    public function init() {
        // Register hooks
        $this->hook('routes.register', array($this, 'registerRoutes'));

        // Contribute admin sidebar item
        $this->hook('admin.sidebar', array($this, 'registerAdminSidebar'));
    }

    public function registerAdminSidebar($items) {
        if (!is_array($items)) {
            $items = array();
        }

        $items[] = array(
            'id' => 'pages',
            'title' => array('key' => 'pages.admin.title', 'fallback' => 'Pages'),
            'icon' => 'bi-file-earmark-text',
            'url' => base_url('/admin/pages'),
            'group' => array('key' => 'admin.sidebar.group.content', 'fallback' => 'Content'),
            'order' => 10,
        );

        return $items;
    }

    public function adminIndex() {
        redirect(base_url('/admin/pages'));
    }

    public function adminRoutes($router) {
        // Future: register /admin/pages/* routes owned by PagesModule.
        return;
    }
    
    /**
     * Register module routes
     */
    public function registerRoutes($data) {
        $router = $data['router'];
        
        // Public routes
        $router->get('/', array($this, 'home'));
        $router->get('/page/{slug}', array($this, 'showPage'));
        $router->get('/post/{slug}', array($this, 'showPost'));
        
        return $data;
    }
    
    /**
     * Home page
     */
    public function home() {
        $db = new Database();
        $posts = $db->query('posts', array('status' => 'published'), array(
            'sort' => 'created_at',
            'order' => 'desc',
            'limit' => 10
        ));
        
        $this->view('pages:home', array(
            'posts' => $posts
        ));
    }
    
    /**
     * Show single page
     */
    public function showPage($params) {
        $db = new Database();
        $slug = $params['slug'];
        
        $pages = $db->query('pages', array('slug' => $slug, 'status' => 'published'));
        
        if (empty($pages)) {
            http_response_code(404);
            echo '404 - Page not found';
            return;
        }
        
        $this->view('pages:single', array(
            'page' => $pages[0]
        ));
    }
    
    /**
     * Show single post
     */
    public function showPost($params) {
        $db = new Database();
        $slug = $params['slug'];
        
        $posts = $db->query('posts', array('slug' => $slug, 'status' => 'published'));
        
        if (empty($posts)) {
            http_response_code(404);
            echo '404 - Post not found';
            return;
        }
        
        $this->view('pages:post', array(
            'post' => $posts[0]
        ));
    }
}
