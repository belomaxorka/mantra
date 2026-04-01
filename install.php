<?php
/**
 * Mantra CMS - Installation Script
 * Creates initial admin user and required directories
 */

require_once __DIR__ . '/core/bootstrap.php';

use Storage\FileIO;

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

// Check if already installed — redirect away instead of die()
if (file_exists(MANTRA_CONTENT . '/users')) {
    $users = glob(MANTRA_CONTENT . '/users/*.json');
    if (!empty($users)) {
        header('Location: ' . Config::detectBaseUrl() . '/', true, 302);
        exit;
    }
}

// Allowed languages whitelist
$allowedLanguages = array('en', 'ru');

// CSRF: generate token for the form
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['install_csrf'];

// Handle form submission
$selectedLanguage = 'en';
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $postedToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    if (!hash_equals($csrfToken, $postedToken)) {
        $error = 'error_csrf';
    } else {
        $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $siteName = isset($_POST['site_name']) && trim($_POST['site_name']) !== '' ? trim((string)$_POST['site_name']) : MANTRA_PROJECT_INFO['name'];
        $language = isset($_POST['language']) ? (string)$_POST['language'] : 'en';
        $selectedLanguage = in_array($language, $allowedLanguages) ? $language : 'en';

        // Validate input
        if (empty($username) || empty($password)) {
            $error = 'error_required_fields';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $username)) {
            $error = 'error_invalid_username';
        } elseif (strlen($password) < 6) {
            $error = 'error_password_too_short';
        }
    }

    if (!isset($error)) {
        // Create directories
        $dirs = array(
            MANTRA_CONTENT . '/pages',
            MANTRA_CONTENT . '/posts',
            MANTRA_CONTENT . '/users',
            MANTRA_CONTENT . '/settings',
            MANTRA_STORAGE . '/logs',
            MANTRA_UPLOADS
        );

        $dirFailed = false;
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                $dirFailed = true;
            }
        }
        if ($dirFailed) {
            $error = 'error_create_dirs';
        }
    }

    if (!isset($error)) {
        // Create configuration
        $baseUrl = Config::detectBaseUrl();
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
        FileIO::writeAtomic(MANTRA_CONTENT . '/settings/config.json', JsonCodec::encode($overrides));

        // Create admin user (use Clock format for timestamp consistency)
        $db = new Database();

        $now = date(Clock::STORAGE_FORMAT);
        $userData = array(
            'username' => $username,
            'password' => Auth::hashPasswordStatic($password),
            'email' => '',
            'role' => 'admin',
            'status' => 'active',
            'author_id' => '',
            'created_at' => $now,
            'updated_at' => $now,
        );

        if ($db->write('users', $db->generateId(), $userData)) {
            // Regenerate CSRF token after success
            unset($_SESSION['install_csrf']);
            $success = true;
            $adminUrl = $baseUrl . '/admin';
        } else {
            $error = 'error_create_user';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* Mobile optimizations */
        @media (max-width: 576px) {
            body {
                padding: 15px;
                align-items: flex-start;
                padding-top: 30px;
            }
            .install-card {
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            }
            .install-card .card-body {
                padding: 1.5rem !important;
            }
            .install-card .card-title {
                font-size: 1.5rem;
                margin-bottom: 1.5rem !important;
            }
            .form-label {
                font-size: 0.95rem;
            }
            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 0.625rem 0.75rem;
            }
            .btn-lg {
                padding: 0.75rem 1rem;
                font-size: 1.1rem;
            }
            .alert {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 400px) {
            body {
                padding: 10px;
            }
            .install-card .card-body {
                padding: 1rem !important;
            }
            .install-card .card-title {
                font-size: 1.25rem;
            }
            .mb-3 {
                margin-bottom: 0.875rem !important;
            }
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
                                <div class="d-grid">
                                    <a href="<?php echo e($adminUrl); ?>" class="btn btn-success btn-lg" data-i18n="success_button">Go to admin panel</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger" role="alert" data-i18n="<?php echo e($error); ?>">
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                                <div class="mb-3">
                                    <label for="site_name" class="form-label" data-i18n="label_site_name">Site Name</label>
                                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo e(MANTRA_PROJECT_INFO['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="language" class="form-label" data-i18n="label_language">Language</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="en"<?php echo $selectedLanguage === 'en' ? ' selected' : ''; ?>>English</option>
                                        <option value="ru"<?php echo $selectedLanguage === 'ru' ? ' selected' : ''; ?>>Русский</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="username" class="form-label" data-i18n="label_username">Admin Username</label>
                                    <input type="text" class="form-control" id="username" name="username" pattern="[a-zA-Z0-9_\-]{3,32}" minlength="3" maxlength="32" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label" data-i18n="label_password">Admin Password</label>
                                    <input type="password" class="form-control" id="password" name="password" minlength="6" required>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
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
                label_password: 'Admin Password',
                button_install: 'Install',
                error_required_fields: 'Username and password are required',
                error_invalid_username: 'Username must be 3-32 characters: letters, numbers, hyphens, underscores',
                error_password_too_short: 'Password must be at least 6 characters',
                error_create_dirs: 'Failed to create required directories. Check file permissions.',
                error_csrf: 'Security token expired. Please try again.',
                error_create_user: 'Failed to create user'
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
                label_password: 'Пароль администратора',
                button_install: 'Установить',
                error_required_fields: 'Имя пользователя и пароль обязательны',
                error_invalid_username: 'Имя пользователя: 3-32 символа (буквы, цифры, дефис, подчёркивание)',
                error_password_too_short: 'Пароль должен быть не менее 6 символов',
                error_create_dirs: 'Не удалось создать директории. Проверьте права доступа.',
                error_csrf: 'Токен безопасности истёк. Попробуйте ещё раз.',
                error_create_user: 'Не удалось создать пользователя'
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

        // Set initial language on page load
        const initialLang = '<?php echo e($selectedLanguage); ?>';
        const languageSelect = document.getElementById('language');

        if (languageSelect) {
            // Form page: add event listener and set value
            languageSelect.value = initialLang;
            languageSelect.addEventListener('change', function() {
                setLanguage(this.value);
            });
        }

        // Apply translations for current language
        setLanguage(initialLang);
    </script>
</body>
</html>
