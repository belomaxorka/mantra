<?php
/**
 * UsersModule - User management functionality
 */

class UsersModule extends Module {
    
    public function init() {
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }
    
    /**
     * Register routes
     */
    public function registerRoutes($data) {
        $router = $data['router'];
        
        // User profile
        $router->get('/profile', array($this, 'profile'));
        $router->post('/profile/update', array($this, 'updateProfile'));
        
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
