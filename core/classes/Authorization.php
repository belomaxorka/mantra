<?php declare(strict_types=1);

/**
 * Single gateway for permission and resource-ownership decisions.
 */
final class Authorization
{
    private PermissionRegistry $permissions;

    public function __construct(PermissionRegistry $permissions)
    {
        $this->permissions = $permissions;
    }

    /** @return bool|string true, false, or the ownership sentinel "own" */
    public function check($user, $permission)
    {
        if (!is_array($user) || !isset($user['role'])) {
            return false;
        }

        $role = (string)$user['role'];
        if ($role === 'admin') {
            return true;
        }

        return $this->permissions->hasPermission($role, (string)$permission);
    }

    public function owns($user, $resource): bool
    {
        if (!is_array($user) || !is_array($resource)) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }

        $userId = (string)($user['_id'] ?? '');
        $authorId = (string)($resource['author_id'] ?? '');
        if ($userId !== '' && $authorId !== '') {
            return hash_equals($userId, $authorId);
        }

        $username = (string)($user['username'] ?? '');
        $author = (string)($resource['author'] ?? '');
        return $username !== '' && $author !== '' && hash_equals($username, $author);
    }
}
