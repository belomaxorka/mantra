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
$selectedLanguage = 'en';
if (request()->method() === 'POST') {
    $username = post_trimmed('username');
    $password = (string)request()->post('password', '');
    $siteName = post_trimmed('site_name', MANTRA_PROJECT_INFO['name']);
    $language = (string)request()->post('language', 'en');
    $selectedLanguage = $language;

    if (empty($username) || empty($password)) {
        $error = 'error_required_fields';
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

        // Create admin user
        $db = new Database();
        $auth = new Auth();

        $userData = array(
            'username' => $username,
            'password' => $auth->hashPassword($password),
            'role' => 'admin'
        );

        if ($db->write('users', $db->generateId(), $userData)) {
            $success = true;
            $adminUrl = rtrim($baseUrl, '/') . '/admin';
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
    <link href="/<?php echo basename(MANTRA_CORE); ?>/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
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
                                    <input type="text" class="form-control" id="username" name="username" required>
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
                label_password: 'Admin Password',
                button_install: 'Install',
                error_required_fields: 'Username and password are required',
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
