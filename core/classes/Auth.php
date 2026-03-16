<?php
/**
 * Auth - Authentication and authorization system
 */

class Auth {
    private $db = null;
    private $currentUser = null;
    
    public function __construct() {
        $this->db = new Database();
        $this->loadCurrentUser();
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        logger()->info('Login attempt', array('username' => $username));
        
        $users = $this->db->query('users', array('username' => $username));
        
        if (empty($users)) {
            logger()->warning('Login failed: user not found', array('username' => $username));
            return false;
        }
        
        $user = $users[0];
        
        // Verify password
        if (!$this->verifyPassword($password, $user['password'])) {
            logger()->warning('Login failed: invalid password', array('username' => $username));
            return false;
        }
        
        // Regenerate session ID to prevent session fixation
        session()->regenerate(true);

        // Set session
        session()->set('user_id', $user['_id']);
        session()->set('user_role', $user['role']);
        
        $this->currentUser = $user;
        
        logger()->info('Login successful', array(
            'username' => $username,
            'user_id' => $user['_id'],
            'role' => $user['role']
        ));
        
        return true;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if ($this->currentUser) {
            logger()->info('User logged out', array(
                'username' => isset($this->currentUser['username']) ? $this->currentUser['username'] : 'unknown',
                'user_id' => isset($this->currentUser['_id']) ? $this->currentUser['_id'] : 'unknown'
            ));
        }

        session()->delete('user_id');
        session()->delete('user_role');
        $this->currentUser = null;
        session()->destroy();
    }
    
    /**
     * Check if user is logged in
     */
    public function check() {
        return $this->currentUser !== null;
    }
    
    /**
     * Get current user
     */
    public function user() {
        return $this->currentUser;
    }
    
    /**
     * Load current user from session
     */
    private function loadCurrentUser() {
        if (session()->has('user_id')) {
            $this->currentUser = $this->db->read('users', session()->get('user_id'));
        }
    }
    
    /**
     * Check if user has role
     */
    public function hasRole($role) {
        if (!$this->check()) {
            return false;
        }
        
        return $this->currentUser['role'] === $role;
    }
    
    /**
     * Hash password
     */
    public function hashPassword($password) {
        $algo = config('security.password_hash_algo', 'PASSWORD_DEFAULT');

        // Config stores algorithm as a string identifier.
        if (!is_string($algo) || $algo === '') {
            $algo = 'PASSWORD_DEFAULT';
        }

        switch ($algo) {
            case 'PASSWORD_BCRYPT':
                return password_hash($password, PASSWORD_BCRYPT);
            case 'PASSWORD_ARGON2I':
                return defined('PASSWORD_ARGON2I') ? password_hash($password, PASSWORD_ARGON2I) : password_hash($password, PASSWORD_DEFAULT);
            case 'PASSWORD_ARGON2ID':
                return defined('PASSWORD_ARGON2ID') ? password_hash($password, PASSWORD_ARGON2ID) : password_hash($password, PASSWORD_DEFAULT);
            case 'PASSWORD_DEFAULT':
            default:
                return password_hash($password, PASSWORD_DEFAULT);
        }
    }

    /**
     * Verify password
     */
    private function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken() {
        // Reuse the existing token if present so that a browser refresh (POST resubmit)
        // doesn't immediately invalidate the previous request.
        if (session()->has('csrf_token')) {
            $existing = session()->get('csrf_token');
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
        }

        $token = bin2hex(openssl_random_pseudo_bytes(32));
        session()->set('csrf_token', $token);
        return $token;
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token) {
        if (!session()->has('csrf_token')) {
            return false;
        }
        // Use hash_equals to prevent timing attacks
        return hash_equals(session()->get('csrf_token'), $token);
    }
}
