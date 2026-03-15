<?php
/**
 * Mantra CMS - Installation Script
 * Creates initial admin user and required directories
 */

require_once __DIR__ . '/core/bootstrap.php';

// Check required extensions
$requiredExtensions = array('json', 'session', 'openssl');
$missingExtensions = array();

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die('Missing required PHP extensions: ' . implode(', ', $missingExtensions));
}

// Load core classes (explicit for installer clarity)
require_once MANTRA_CORE . '/Database.php';
require_once MANTRA_CORE . '/Auth.php';
require_once MANTRA_CORE . '/Config.php';

// Check if already installed
if (file_exists(MANTRA_CONTENT . '/users')) {
    $users = glob(MANTRA_CONTENT . '/users/*.json');
    if (!empty($users)) {
        die('Mantra CMS is already installed. Delete users to reinstall.');
    }
}

// Handle form submission
if (request()->method() === 'POST') {
    $username = trim((string)request()->post('username', ''));
    $password = (string)request()->post('password', '');
    $email = trim((string)request()->post('email', ''));
    $siteName = trim((string)request()->post('site_name', 'Mantra CMS'));
    $language = (string)request()->post('language', 'en');
    
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
        
        // Auto-detect base URL
        $protocol = is_https() ? 'https' : 'http';
        $host = (string)request()->header('Host', 'localhost');
        $scriptPath = dirname((string)request()->server('SCRIPT_NAME', ''));
        $baseUrl = $protocol . '://' . $host . ($scriptPath !== '/' ? $scriptPath : '');
        
        // Create configuration file (single source of truth)
        $config = Config::buildInstallConfig($siteName, $language, $baseUrl);

        $configPath = MANTRA_CONTENT . '/settings/config.json';
        $configJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($configPath, $configJson);
        
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
            $adminUrl = rtrim($baseUrl, '/') . '/admin';
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
        <p class="success">Installation successful! <a href="<?php echo isset($adminUrl) ? $adminUrl : '/admin'; ?>">Go to admin panel</a></p>
    <?php else: ?>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        
        <form method="POST">
            <label>Site Name:</label>
            <input type="text" name="site_name" value="Mantra CMS" required>
            
            <label>Language:</label>
            <select name="language" style="width: 100%; padding: 10px; margin: 10px 0;">
                <option value="en">English</option>
                <option value="ru">Русский</option>
            </select>
            
            <label>Admin Username:</label>
            <input type="text" name="username" required>
            
            <label>Admin Email:</label>
            <input type="email" name="email" required>
            
            <label>Admin Password:</label>
            <input type="password" name="password" required>
            
            <button type="submit">Install</button>
        </form>
    <?php endif; ?>
</body>
</html>
