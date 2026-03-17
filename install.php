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

// Check if already installed
if (file_exists(MANTRA_CONTENT . '/users')) {
    $users = glob(MANTRA_CONTENT . '/users/*.json');
    if (!empty($users)) {
        die(MANTRA_PROJECT_INFO['name'] . ' is already installed. Delete users to reinstall.');
    }
}

// Handle form submission
if (request()->method() === 'POST') {
    $username = trim((string)request()->post('username', ''));
    $password = (string)request()->post('password', '');
    $email = trim((string)request()->post('email', ''));
    $siteName = trim((string)request()->post('site_name', MANTRA_PROJECT_INFO['name']));
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
        
        // Create configuration
        $config = Config::buildInstallConfig($siteName, $language, $baseUrl);
        $defaults = Config::defaults();
        $overrides = Config::diffOverrides($defaults, $config);
        
        // Add schema version
        $schemaPath = MANTRA_CORE . '/config.settings.schema.php';
        if (file_exists($schemaPath)) {
            $schema = require $schemaPath;
            if (is_array($schema) && isset($schema['version'])) {
                $overrides['schema_version'] = (int)$schema['version'];
            }
        }
        
        // Save configuration
        JsonFile::write(MANTRA_CONTENT . '/settings/config.json', $overrides);
        
        // Create admin user
        $db = new Database();
        $auth = new Auth();
        
        $userData = array(
            'username' => $username,
            'email' => $email,
            'password' => $auth->hashPassword($password),
            'role' => 'admin'
        );
        
        if ($db->write('users', $db->generateId(), $userData)) {
            $success = true;
            $adminUrl = rtrim($baseUrl, '/') . '/admin';
        } else {
            $error = 'Failed to create user';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="page_title">Install <?php echo e(MANTRA_PROJECT_INFO['name']); ?></title>
    <link href="/core/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .install-card {
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card install-card">
                    <div class="card-body p-4">
                        <h1 class="card-title text-center mb-4" data-i18n="title">Install <?php echo e(MANTRA_PROJECT_INFO['name']); ?></h1>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <h5 class="alert-heading" data-i18n="success_heading">Installation successful!</h5>
                                <p class="mb-0" data-i18n="success_message">Your CMS is ready to use.</p>
                                <hr>
                                <a href="<?php echo e($adminUrl); ?>" class="btn btn-success" data-i18n="success_button">Go to admin panel</a>
                            </div>
                        <?php else: ?>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo e($error); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="site_name" class="form-label" data-i18n="label_site_name">Site Name</label>
                                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo e(MANTRA_PROJECT_INFO['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="language" class="form-label" data-i18n="label_language">Language</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="en">English</option>
                                        <option value="ru">Русский</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="username" class="form-label" data-i18n="label_username">Admin Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label" data-i18n="label_email">Admin Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label" data-i18n="label_password">Admin Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" data-i18n="button_install">Install</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/core/assets/bootstrap/bootstrap.min.js"></script>
    <script>
        const translations = {
            en: {
                page_title: 'Install <?php echo e(MANTRA_PROJECT_INFO['name']); ?>',
                title: 'Install <?php echo e(MANTRA_PROJECT_INFO['name']); ?>',
                success_heading: 'Installation successful!',
                success_message: 'Your CMS is ready to use.',
                success_button: 'Go to admin panel',
                label_site_name: 'Site Name',
                label_language: 'Language',
                label_username: 'Admin Username',
                label_email: 'Admin Email',
                label_password: 'Admin Password',
                button_install: 'Install'
            },
            ru: {
                page_title: 'Установка <?php echo e(MANTRA_PROJECT_INFO['name']); ?>',
                title: 'Установка <?php echo e(MANTRA_PROJECT_INFO['name']); ?>',
                success_heading: 'Установка завершена!',
                success_message: 'Ваша CMS готова к использованию.',
                success_button: 'Перейти в админ-панель',
                label_site_name: 'Название сайта',
                label_language: 'Язык',
                label_username: 'Имя администратора',
                label_email: 'Email администратора',
                label_password: 'Пароль администратора',
                button_install: 'Установить'
            }
        };

        function setLanguage(lang) {
            document.querySelectorAll('[data-i18n]').forEach(function(el) {
                const key = el.getAttribute('data-i18n');
                if (translations[lang] && translations[lang][key]) {
                    if (el.tagName === 'TITLE') {
                        el.textContent = translations[lang][key];
                    } else {
                        el.textContent = translations[lang][key];
                    }
                }
            });
            document.getElementById('html-root').setAttribute('lang', lang);
        }

        document.getElementById('language').addEventListener('change', function() {
            setLanguage(this.value);
        });

        // Set initial language on page load
        const initialLang = document.getElementById('language').value || 'en';
        setLanguage(initialLang);
    </script>
</body>
</html>
