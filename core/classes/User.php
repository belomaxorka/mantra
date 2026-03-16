<?php
/**
 * User - User management and permissions
 */

class User {
    private $db = null;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Get user by ID
     */
    public function find($id) {
        return $this->db->read('users', $id);
    }

    /**
     * Get user by username
     */
    public function findByUsername($username) {
        $users = $this->db->query('users', array('username' => $username));
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Get user by email
     */
    public function findByEmail($email) {
        $users = $this->db->query('users', array('email' => $email));
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Get all users
     */
    public function all($filters = array()) {
        return $this->db->query('users', $filters, array(
            'sort' => 'created_at',
            'order' => 'desc'
        ));
    }

    /**
     * Create new user
     */
    public function create($data) {
        if (empty($data['username']) || empty($data['password'])) {
            return false;
        }

        // Check if username already exists
        if ($this->findByUsername($data['username'])) {
            return false;
        }

        // Hash password
        $auth = new Auth();
        $data['password'] = $auth->hashPassword($data['password']);

        // Set defaults
        if (!isset($data['role'])) {
            $data['role'] = 'editor';
        }
        if (!isset($data['status'])) {
            $data['status'] = 'active';
        }

        $id = $this->db->generateId();
        return $this->db->write('users', $id, $data) ? $id : false;
    }

    /**
     * Update user
     */
    public function update($id, $data) {
        $user = $this->find($id);
        if (!$user) {
            return false;
        }

        // Don't allow changing username
        unset($data['username']);

        // Hash password if provided
        if (!empty($data['password'])) {
            $auth = new Auth();
            $data['password'] = $auth->hashPassword($data['password']);
        } else {
            unset($data['password']);
        }

        $updated = array_merge($user, $data);
        return $this->db->write('users', $id, $updated);
    }

    /**
     * Delete user
     */
    public function delete($id) {
        return $this->db->delete('users', $id);
    }

    /**
     * Check if user has permission
     */
    public function hasPermission($user, $permission) {
        if (!is_array($user) || !isset($user['role'])) {
            return false;
        }

        $role = $user['role'];

        // Admin has all permissions
        if ($role === 'admin') {
            return true;
        }

        // Define role permissions
        $permissions = array(
            'editor' => array(
                'pages.view', 'pages.create', 'pages.edit', 'pages.delete',
                'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
                'uploads.view', 'uploads.upload'
            ),
            'author' => array(
                'pages.view', 'pages.create', 'pages.edit.own',
                'posts.view', 'posts.create', 'posts.edit.own',
                'uploads.view', 'uploads.upload'
            ),
            'viewer' => array(
                'pages.view', 'posts.view'
            )
        );

        if (!isset($permissions[$role])) {
            return false;
        }

        return in_array($permission, $permissions[$role]);
    }

    /**
     * Check if user can edit content
     */
    public function canEdit($user, $content) {
        if (!is_array($user) || !is_array($content)) {
            return false;
        }

        $role = isset($user['role']) ? $user['role'] : '';

        // Admin can edit everything
        if ($role === 'admin') {
            return true;
        }

        // Editor can edit everything
        if ($role === 'editor') {
            return true;
        }

        // Author can only edit own content
        if ($role === 'author') {
            $contentAuthor = isset($content['author']) ? $content['author'] : '';
            $username = isset($user['username']) ? $user['username'] : '';
            return $contentAuthor === $username;
        }

        return false;
    }

    /**
     * Get user display name
     */
    public function getDisplayName($user) {
        if (!is_array($user)) {
            return 'Unknown';
        }

        if (!empty($user['display_name'])) {
            return $user['display_name'];
        }

        if (!empty($user['username'])) {
            return $user['username'];
        }

        return 'Unknown';
    }
}
