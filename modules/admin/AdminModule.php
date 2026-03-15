<?php
/**
 * AdminModule - Admin panel functionality
 */

class AdminModule extends Module {
    
    public function init() {
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }
    
    /**
     * Register admin routes
     */
    public function registerRoutes($data) {
        $router = $data['router'];
        
        // Admin routes with auth middleware
        $router->get('/admin', array($this, 'dashboard'))
               ->middleware(array($this, 'requireAuth'));
        
        $router->get('/admin/login', array($this, 'loginForm'));
        $router->post('/admin/login', array($this, 'loginProcess'));
        $router->get('/admin/logout', array($this, 'logout'));
        
        // Content management
        $router->get('/admin/pages', array($this, 'listPages'))
               ->middleware(array($this, 'requireAuth'));
        $router->get('/admin/pages/create', array($this, 'createPage'))
               ->middleware(array($this, 'requireAuth'));
        $router->post('/admin/pages/save', array($this, 'savePage'))
               ->middleware(array($this, 'requireAuth'));
        
        return $data;
    }
    
    /**
     * Auth middleware
     */
    public function requireAuth() {
        if (!auth()->check()) {
            redirect(base_url('/admin/login'));
            return false;
        }
        return true;
    }
    
    /**
     * Dashboard
     */
    public function dashboard() {
        $this->view('admin:dashboard', array(
            'user' => auth()->user()
        ));
    }
    
    /**
     * Login form
     */
    public function loginForm() {
        if (auth()->check()) {
            redirect(base_url('/admin'));
            return;
        }
        
        $this->view('admin:login', array());
    }
    
    /**
     * Process login
     */
    public function loginProcess() {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (auth()->login($username, $password)) {
            redirect(base_url('/admin'));
        } else {
            $this->view('admin:login', array(
                'error' => 'Invalid credentials'
            ));
        }
    }
    
    /**
     * Logout
     */
    public function logout() {
        auth()->logout();
        redirect(base_url('/admin/login'));
    }
    
    /**
     * List pages
     */
    public function listPages() {
        $db = new Database();
        $pages = $db->query('pages', array(), array('sort' => 'created_at', 'order' => 'desc'));
        
        $this->view('admin:pages', array(
            'pages' => $pages
        ));
    }
    
    /**
     * Create page form
     */
    public function createPage() {
        $this->view('admin:page-edit', array(
            'page' => null
        ));
    }
    
    /**
     * Save page
     */
    public function savePage() {
        $db = new Database();
        
        $id = isset($_POST['id']) ? $_POST['id'] : $db->generateId();
        $data = array(
            'title' => $_POST['title'],
            'slug' => slugify($_POST['title']),
            'content_html' => $_POST['content'],
            'content_type' => 'html',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'lang' => isset($_POST['lang']) ? $_POST['lang'] : 'en'
        );
        
        if ($db->write('pages', $id, $data)) {
            redirect(base_url('/admin/pages'));
        } else {
            echo 'Error saving page';
        }
    }
}
