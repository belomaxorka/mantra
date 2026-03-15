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
        $users = $this->db->query('users', array('username' => $username));
        
        if (empty($users)) {
            return false;
        }
        
        $user = $users[0];
        
        // Verify password
        if (!$this->verifyPassword($password, $user['password'])) {
            return false;
        }
        
        // Set session
        $_SESSION['user_id'] = $user['_id'];
        $_SESSION['user_role'] = $user['role'];
        
        $this->currentUser = $user;
        
        return true;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_role']);
        $this->currentUser = null;
        session_destroy();
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
        if (isset($_SESSION['user_id'])) {
            $this->currentUser = $this->db->read('users', $_SESSION['user_id']);
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
        // PHP 5.5+ has password_hash
        if (function_exists('password_hash')) {
            return password_hash($password, PASSWORD_DEFAULT);
        }
        
        // Fallback for older PHP
        return hash('sha256', $password);
    }
    
    /**
     * Verify password
     */
    private function verifyPassword($password, $hash) {
        // PHP 5.5+ has password_verify
        if (function_exists('password_verify')) {
            return password_verify($password, $hash);
        }
        
        // Fallback for older PHP
        return hash('sha256', $password) === $hash;
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCsrfToken() {
        $token = bin2hex(openssl_random_pseudo_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
    }
}
