<?php
/**
 * UsersModule - User management functionality
 */

class UsersModule extends Module {
    
    public function init() {
        $this->hook('routes.register', array($this, 'registerRoutes'));

        $this->hook('admin.sidebar', array($this, 'registerAdminSidebar'));
    }

    public function registerAdminSidebar($items) {
        if (!is_array($items)) {
            $items = array();
        }

        $items[] = array(
            'id' => 'users',
            'title' => array('key' => 'users.admin.title', 'fallback' => 'Users'),
            'icon' => 'bi-people',
            'url' => base_url('/admin/users'),
            'group' => array('key' => 'admin.sidebar.group.system', 'fallback' => 'System'),
            'order' => 10,
        );

        return $items;
    }

    public function adminIndex() {
        redirect(base_url('/admin/users'));
    }

    public function adminRoutes($router) {
        // Already registered in registerRoutes() for now.
        return;
    }
    
    /**
     * Register routes
     */
    public function registerRoutes($data) {
        $router = $data['router'];
        
        // User profile
        $router->get('/profile', array($this, 'profile'));
        $router->post('/profile/update', array($this, 'updateProfile'));

        // Admin entry (placeholder: user profile)
        $router->get('/admin/users', array($this, 'profile'));
        $router->post('/admin/users/update', array($this, 'updateProfile'));
        
        return $data;
    }
    
    /**
     * User profile page
     */
    public function profile() {
        if (!auth()->check()) {
            redirect(base_url('/admin/login'));
            return;
        }
        
        $this->view('users:profile', array(
            'user' => auth()->user()
        ));
    }
    
    /**
     * Update profile
     */
    public function updateProfile() {
        if (!auth()->check()) {
            json_response(array('error' => 'Unauthorized'), 401);
            return;
        }
        
        $user = auth()->user();
        $db = new Database();
        
        // Update user data
        $userData = array(
            'username' => $user['username'],
            'email' => (string)request()->post('email', $user['email']),
            'password' => $user['password'],
            'role' => $user['role']
        );
        
        if ($db->write('users', $user['_id'], $userData)) {
            json_response(array('success' => true));
        } else {
            json_response(array('error' => 'Failed to update'), 500);
        }
    }
}
