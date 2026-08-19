<?php declare(strict_types=1);

/**
 * User - User management and permissions
 */
class User
{
    public const MIN_USERNAME_LENGTH = 3;
    public const MAX_USERNAME_LENGTH = 50;
    public const MAX_EMAIL_LENGTH = 255;
    public const MIN_PASSWORD_LENGTH = 12;
    public const USERNAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';
    public const ROLES = ['admin', 'editor', 'viewer'];
    public const STATUSES = ['active', 'inactive', 'banned'];

    private $db = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? app()->db();
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
        return $this->findByFieldCaseInsensitive('username', $username);
    }

    /**
     * Get user by email
     */
    public function findByEmail($email)
    {
        return $this->findByFieldCaseInsensitive('email', $email);
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
        if (!is_array($data)) {
            return false;
        }

        $data = $this->normalizeCoreFields(array_merge([
            'email' => '',
            'role' => 'editor',
            'status' => 'active',
        ], $data));

        if (!empty(self::validationErrors($data, true))) {
            return false;
        }

        $data['password'] = Auth::hashPasswordStatic($data['password']);

        try {
            return $this->db->create('users', $data);
        } catch (UniqueConstraintViolationException | SchemaValidationException | ValueError $e) {
            return false;
        }
    }

    /**
     * Update user
     */
    public function update($id, $data)
    {
        if (!is_array($data)) {
            return false;
        }

        $user = $this->find($id);
        if (!$user) {
            return false;
        }

        // Don't allow changing username
        unset($data['username']);

        $data = $this->normalizeCoreFields($data);
        $hasNewPassword = array_key_exists('password', $data) && $data['password'] !== '';
        if (!$hasNewPassword) {
            unset($data['password']);
        }

        $updated = array_merge($user, $data);
        if (!empty(self::validationErrors($updated, false))) {
            return false;
        }

        if ($hasNewPassword) {
            $updated['password'] = Auth::hashPasswordStatic($data['password']);
        }

        try {
            return $this->db->writeIf(
                'users',
                $id,
                $updated,
                fn($current, $users) => $this->preservesActiveAdministrator(
                    $current,
                    $updated,
                    $users,
                ),
            );
        } catch (UniqueConstraintViolationException | SchemaValidationException | ValueError $e) {
            return false;
        }
    }

    /**
     * Delete user
     */
    public function delete($id)
    {
        return $this->db->deleteIf('users', $id, function ($target, $users) {
            if (!$this->isActiveAdmin($target)) {
                return true;
            }

            $activeAdminCount = 0;
            foreach ($users as $user) {
                if ($this->isActiveAdmin($user)) {
                    $activeAdminCount++;
                }
            }
            return $activeAdminCount > 1;
        });
    }

    /**
     * Validate the public user contract before hashing or persistence.
     *
     * @return array<string, string> Errors keyed by field name
     */
    public static function validationErrors($data, $passwordRequired = true)
    {
        $errors = [];
        if (!is_array($data)) {
            return ['user' => 'User data must be an array'];
        }

        $username = $data['username'] ?? null;
        if (!is_string($username)
            || strlen($username) < self::MIN_USERNAME_LENGTH
            || strlen($username) > self::MAX_USERNAME_LENGTH
            || preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            $errors['username'] = 'Username is invalid';
        }

        $email = $data['email'] ?? '';
        if (!is_string($email)
            || strlen($email) > self::MAX_EMAIL_LENGTH
            || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = 'Email is invalid';
        }

        $role = $data['role'] ?? 'editor';
        if (!is_string($role) || !in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Role is invalid';
        }

        $status = $data['status'] ?? 'active';
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Status is invalid';
        }

        $password = $data['password'] ?? null;
        if ($passwordRequired || ($password !== null && $password !== '')) {
            if (!is_string($password)
                || strlen($password) < self::MIN_PASSWORD_LENGTH
                || str_contains($password, "\0")) {
                $errors['password'] = 'Password is invalid';
            }
        }

        return $errors;
    }

    private function normalizeCoreFields($data)
    {
        foreach (['username', 'email', 'role', 'status'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        return $data;
    }

    private function findByFieldCaseInsensitive($field, $value)
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        foreach ($this->db->read('users') as $user) {
            $candidate = $user[$field] ?? null;
            if (is_string($candidate) && strcasecmp($candidate, $value) === 0) {
                return $user;
            }
        }
        return null;
    }

    private function preservesActiveAdministrator($current, $updated, $users)
    {
        if (!$this->isActiveAdmin($current) || $this->isActiveAdmin($updated)) {
            return true;
        }

        $activeAdminCount = 0;
        foreach ($users as $user) {
            if ($this->isActiveAdmin($user)) {
                $activeAdminCount++;
            }
        }
        return $activeAdminCount > 1;
    }

    private function isActiveAdmin($user)
    {
        return is_array($user)
            && ($user['role'] ?? '') === 'admin'
            && ($user['status'] ?? '') === 'active';
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
        $authorization = app()->service('authorization');
        return $authorization instanceof Authorization
            ? $authorization->check($user, $permission)
            : false;
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
        $authorization = app()->service('authorization');
        return $authorization instanceof Authorization
            ? $authorization->owns($user, $content)
            : false;
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
