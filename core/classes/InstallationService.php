<?php declare(strict_types=1);

/** Coordinates installation-specific operations through core domain services. */
final class InstallationService
{
    private $users;

    public function __construct(?User $users = null)
    {
        $this->users = $users ?? new User();
    }

    /** Return the installer's translation key for invalid credentials. */
    public static function validateAdminCredentials($username, $password)
    {
        if (!is_string($username) || $username === '' || !is_string($password) || $password === '') {
            return 'error_required_fields';
        }

        $errors = User::validationErrors([
            'username' => trim($username),
            'password' => $password,
            'email' => '',
            'role' => 'admin',
            'status' => 'active',
        ], true);

        if (isset($errors['username'])) {
            return 'error_invalid_username';
        }
        if (isset($errors['password'])) {
            return 'error_password_too_short';
        }
        return null;
    }

    /** Create the initial administrator using the same contract as admin UI. */
    public function createInitialAdmin($username, $password)
    {
        return $this->users->create([
            'username' => is_string($username) ? trim($username) : $username,
            'password' => $password,
            'email' => '',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
