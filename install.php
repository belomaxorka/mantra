<?php
/**
 * Mantra CMS - Installation Script
 * Creates initial admin user and required directories
 */

define('MANTRA_ROOT', __DIR__);
define('MANTRA_CORE', MANTRA_ROOT . '/core');
define('MANTRA_CONTENT', MANTRA_ROOT . '/content');
define('MANTRA_STORAGE', MANTRA_ROOT . '/storage');
define('MANTRA_UPLOADS', MANTRA_ROOT . '/uploads');

// Load core classes
require_once MANTRA_CORE . '/Database.php';
require_once MANTRA_CORE . '/Auth.php';

// Check if already installed
if (file_exists(MANTRA_CONTENT . '/users')) {
    $users = glob(MANTRA_CONTENT . '/users/*.json');
    if (!empty($users)) {
        die('Mantra CMS is already installed. Delete users to reinstall.');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        // Create directories
        $dirs = array(
            MANTRA_CONTENT . '/pages',
            MANTRA_CONTENT . '/posts',
            MANTRA_CONTENT . '/users',
            MANTRA_CONTENT . '/settings',
            MANTRA_STORAGE . '/cache',
            MANTRA_STORAGE . '/logs',
            MANTRA_UPLOADS
        );
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // Create admin user
        $db = new Database();
        $auth = new Auth();
        
        $userId = $db->generateId();
        $userData = array(
            'username' => $username,
            'email' => $email,
            'password' => $auth->hashPassword($password),
            'role' => 'admin'
        );
        
        if ($db->write('users', $userId, $userData)) {
            $success = true;
        } else {
            $error = 'Failed to create user';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Install Mantra CMS</title>
    <style>
        body { font-family: sans-serif; max-width: 500px; margin: 50px auto; padding: 20px; }
        input { width: 100%; padding: 10px; margin: 10px 0; }
        button { background: #2c3e50; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Install Mantra CMS</h1>
    
    <?php if (isset($success)): ?>
        <p class="success">Installation successful! <a href="/admin">Go to admin panel</a></p>
    <?php else: ?>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        
        <form method="POST">
            <label>Username:</label>
            <input type="text" name="username" required>
            
            <label>Email:</label>
            <input type="email" name="email" required>
            
            <label>Password:</label>
            <input type="password" name="password" required>
            
            <button type="submit">Install</button>
        </form>
    <?php endif; ?>
</body>
</html>
