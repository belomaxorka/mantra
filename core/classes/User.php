<?php declare(strict_types=1);

/**
 * User - User management and permissions
 */
class User
{
    private $db = null;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Get user by ID
     */
    public function find($id)
    {
        return $this->db->read('users', $id);
    }

    /**
     * Get user by username
     */
    public function findByUsername($username)
    {
        $users = $this->db->query('users', ['username' => $username]);
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Get user by email
     */
    public function findByEmail($email)
    {
        $users = $this->db->query('users', ['email' => $email]);
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Get all users
     */
    public function all($filters = [])
    {
        return $this->db->query('users', $filters, [
            'sort' => 'created_at',
            'order' => 'desc',
        ]);
    }

    /**
     * Create new user
     */
    public function create($data)
    {
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
    public function update($id, $data)
    {
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
    public function delete($id)
    {
        return $this->db->delete('users', $id);
    }

    /**
     * Check if user has permission.
     *
     * Returns:
     *   true   - full access
     *   'own'  - access only to own content (ownership check needed)
     *   false  - no access
     *
     * @param array $user
     * @param string $permission
     * @return bool|string
     */
    public function hasPermission($user, $permission)
    {
        if (!is_array($user) || !isset($user['role'])) {
            return false;
        }

        $role = $user['role'];

        if ($role === 'admin') {
            return true;
        }

        $registry = app()->service('permissions');
        if (!$registry) {
            return false;
        }

        return $registry->hasPermission($role, $permission);
    }

    /**
     * Check if user owns the given content item.
     * Used for ownership-gated permissions (.own suffix).
     *
     * Compares by author_id (stable). Falls back to author (username)
     * for content created before the author_id migration.
     *
     * @param array $user User data with '_id' and 'username'
     * @param array $content Content item with 'author_id' or 'author'
     * @return bool
     */
    public function canEdit($user, $content)
    {
        if (!is_array($user) || !is_array($content)) {
            return false;
        }

        // Admin bypasses ownership checks
        if (isset($user['role']) && $user['role'] === 'admin') {
            return true;
        }

        // Primary: compare by author_id (stable identifier)
        $authorId = $content['author_id'] ?? '';
        $userId = $user['_id'] ?? '';
        if ($authorId !== '' && $userId !== '') {
            return $authorId === $userId;
        }

        // Fallback: compare by username (pre-migration content)
        $contentAuthor = $content['author'] ?? '';
        $username = $user['username'] ?? '';

        return $contentAuthor !== '' && $contentAuthor === $username;
    }

    /**
     * Get user display name
     */
    public function getDisplayName($user)
    {
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
