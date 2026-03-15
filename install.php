<?php
/**
 * Mantra CMS - Installation Script
 * Creates initial admin user and required directories
 */

// Check PHP version requirement
if (version_compare(PHP_VERSION, '5.5.0', '<')) {
    die('Mantra CMS requires PHP 5.5.0 or higher. Your version: ' . PHP_VERSION);
}

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
    $siteName = isset($_POST['site_name']) ? trim($_POST['site_name']) : 'Mantra CMS';
    $language = isset($_POST['language']) ? $_POST['language'] : 'en';
    
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
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
        $baseUrl = $protocol . '://' . $host . ($scriptPath !== '/' ? $scriptPath : '');
        
        // Create configuration file
        $config = array(
            'site_name' => $siteName,
            'site_url' => $baseUrl,
            'timezone' => 'UTC',
            'default_language' => $language,
            'debug' => true,
            'cache_enabled' => true,
            'cache_lifetime' => 3600,
            'session_name' => 'mantra_session',
            'session_lifetime' => 7200,
            'content_format' => 'json',
            'posts_per_page' => 10,
            'active_theme' => 'default',
            'enabled_modules' => array('admin', 'pages', 'media', 'users', 'editor')
        );
        
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
