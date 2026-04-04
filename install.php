<?php
/**
 * Mantra CMS - Installation Script
 * Creates initial admin user and required directories
 */

require_once __DIR__ . '/core/bootstrap.php';

use Storage\FileIO;

// Check if already installed — redirect away instead of die()
if (file_exists(MANTRA_CONTENT . '/users')) {
    $users = glob(MANTRA_CONTENT . '/users/*.json');
    if (!empty($users)) {
        header('Location: ' . Config::detectBaseUrl() . '/', true, 302);
        exit;
    }
}

// System requirements checks
$requirements = [];

// PHP version
$requirements[] = [
    'name' => 'PHP >= 8.1.0',
    'name_ru' => 'PHP >= 8.1.0',
    'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'detail' => PHP_VERSION,
];

// Required extensions
$requiredExtensions = ['json', 'session', 'openssl'];
foreach ($requiredExtensions as $ext) {
    $requirements[] = [
        'name' => $ext . ' extension',
        'name_ru' => 'Расширение ' . $ext,
        'ok' => extension_loaded($ext),
        'detail' => extension_loaded($ext) ? '' : 'missing',
    ];
}

// Writable directories
$writableDirs = [
    MANTRA_CONTENT => 'content/',
    MANTRA_STORAGE => 'storage/',
    MANTRA_UPLOADS => 'uploads/',
];
foreach ($writableDirs as $path => $label) {
    $writable = is_dir($path) ? is_writable($path) : is_writable(dirname($path));
    $requirements[] = [
        'name' => $label . ' writable',
        'name_ru' => $label . ' доступен для записи',
        'ok' => $writable,
        'detail' => $writable ? '' : 'not writable',
    ];
}

$allRequirementsMet = true;
foreach ($requirements as $req) {
    if (!$req['ok']) {
        $allRequirementsMet = false;
        break;
    }
}

// Allowed languages whitelist
$allowedLanguages = ['en', 'ru'];

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
        $dirs = [
            MANTRA_CONTENT . '/pages',
            MANTRA_CONTENT . '/posts',
            MANTRA_CONTENT . '/users',
            MANTRA_CONTENT . '/settings',
            MANTRA_STORAGE . '/logs',
            MANTRA_UPLOADS,
        ];

        $dirFailed = false;
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
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
        $userData = [
            'username' => $username,
            'password' => Auth::hashPasswordStatic($password),
            'email' => '',
            'role' => 'admin',
            'status' => 'active',
            'author_id' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($db->write('users', $db->generateId(), $userData)) {
            // Regenerate CSRF token after success
            unset($_SESSION['install_csrf']);
            $success = true;
            $adminUrl = $baseUrl . '/admin';
            $siteUrl = $baseUrl . '/';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Design tokens (from admin.css) */
        :root {
            --mn-font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --mn-primary: #6366f1;
            --mn-primary-hover: #4f46e5;
            --mn-primary-soft: rgba(99, 102, 241, .08);
            --mn-bg: #f8fafc;
            --mn-card-bg: #fff;
            --mn-card-radius: 0.75rem;
            --mn-text: #0f172a;
            --mn-text-secondary: #475569;
            --mn-text-muted: #94a3b8;
            --mn-border: #e2e8f0;
            --mn-success: #10b981;
            --mn-success-soft: rgba(16, 185, 129, .1);
            --mn-danger: #ef4444;
            --mn-danger-soft: rgba(239, 68, 68, .1);
            --mn-transition: .15s ease;
        }

        /* Reset & base */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: var(--mn-font);
            color: var(--mn-text);
            background: var(--mn-bg);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Card (login-page style) */
        .install-wrap {
            max-width: 460px;
            width: 100%;
        }
        .install-card {
            background: var(--mn-card-bg);
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
            padding: 2.5rem;
        }

        /* Brand */
        .install-brand {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -.03em;
            color: var(--mn-text);
            margin: 0 0 .25rem;
            text-align: center;
        }
        .install-subtitle {
            color: var(--mn-text-muted);
            font-size: .9375rem;
            text-align: center;
            margin: 0 0 2rem;
        }

        /* Requirements */
        .req-list {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem;
        }
        .req-item {
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: .5rem 0;
            font-size: .875rem;
            color: var(--mn-text-secondary);
            border-bottom: 1px solid var(--mn-border);
        }
        .req-item:last-child { border-bottom: none; }
        .req-icon {
            width: 1.375rem;
            height: 1.375rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .req-icon svg { width: .75rem; height: .75rem; }
        .req-ok .req-icon { background: var(--mn-success-soft); color: var(--mn-success); }
        .req-fail .req-icon { background: var(--mn-danger-soft); color: var(--mn-danger); }
        .req-fail { color: var(--mn-danger); font-weight: 500; }
        .req-detail {
            margin-left: auto;
            font-size: .75rem;
            color: var(--mn-text-muted);
            font-weight: 400;
        }
        .req-heading {
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--mn-text-muted);
            margin: 0 0 .5rem;
        }
        .req-blocked {
            text-align: center;
            padding: 1rem 0 0;
            font-size: .875rem;
            color: var(--mn-danger);
            font-weight: 500;
        }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-label {
            display: block;
            font-weight: 500;
            font-size: .875rem;
            color: var(--mn-text-secondary);
            margin-bottom: .375rem;
        }
        .form-control,
        .form-select {
            display: block;
            width: 100%;
            font-family: var(--mn-font);
            font-size: .875rem;
            padding: .5rem .75rem;
            border: 1px solid var(--mn-border);
            border-radius: .5rem;
            background: var(--mn-card-bg);
            color: var(--mn-text);
            transition: border-color var(--mn-transition), box-shadow var(--mn-transition);
            outline: none;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--mn-primary);
            box-shadow: 0 0 0 3px var(--mn-primary-soft);
        }
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 8.825a.5.5 0 0 1-.354-.146l-4-4a.5.5 0 1 1 .708-.708L6 7.617l3.646-3.646a.5.5 0 1 1 .708.708l-4 4A.5.5 0 0 1 6 8.825z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            padding-right: 2.25rem;
        }

        /* Password wrapper */
        .password-wrap {
            position: relative;
        }
        .password-wrap .form-control { padding-right: 2.75rem; }
        .password-toggle {
            position: absolute;
            right: .5rem;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            padding: .25rem;
            cursor: pointer;
            color: var(--mn-text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: .25rem;
            transition: color var(--mn-transition);
        }
        .password-toggle:hover { color: var(--mn-text-secondary); }
        .password-toggle svg { width: 1.125rem; height: 1.125rem; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            font-family: var(--mn-font);
            font-weight: 500;
            font-size: .875rem;
            border-radius: .5rem;
            padding: .625rem 1.25rem;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: background var(--mn-transition), border-color var(--mn-transition), color var(--mn-transition), box-shadow var(--mn-transition);
        }
        .btn-primary {
            background: var(--mn-primary);
            border-color: var(--mn-primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--mn-primary-hover);
            border-color: var(--mn-primary-hover);
        }
        .btn-outline {
            background: transparent;
            border-color: var(--mn-border);
            color: var(--mn-text-secondary);
        }
        .btn-outline:hover {
            border-color: var(--mn-text-secondary);
            color: var(--mn-text);
        }
        .btn-block { width: 100%; }
        .btn-lg {
            padding: .75rem 1.5rem;
            font-size: 1rem;
        }

        /* Error alert (same as .login-error) */
        .install-error {
            background: #f8d7da;
            color: #58151c;
            border-radius: var(--mn-card-radius);
            padding: .75rem 1rem;
            font-size: .875rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .install-error svg { flex-shrink: 0; }

        /* Success screen */
        .success-screen { text-align: center; }
        .success-icon {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1.25rem;
            border-radius: 50%;
            background: var(--mn-success-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--mn-success);
            animation: success-pop .4s cubic-bezier(.175, .885, .32, 1.275) both;
        }
        .success-icon svg { width: 2rem; height: 2rem; }
        @keyframes success-pop {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .success-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--mn-text);
            margin: 0 0 .375rem;
        }
        .success-message {
            color: var(--mn-text-muted);
            font-size: .9375rem;
            margin: 0 0 1.75rem;
        }
        .success-actions {
            display: flex;
            flex-direction: column;
            gap: .625rem;
        }
        .success-warning {
            background: var(--mn-danger-soft);
            color: var(--mn-danger);
            border-radius: .5rem;
            padding: .625rem .875rem;
            font-size: .8125rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .success-warning strong { font-weight: 600; }

        /* Footer */
        .install-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .75rem;
            color: var(--mn-text-muted);
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 1px solid var(--mn-border);
            margin: 1.5rem 0;
        }

        /* Mobile */
        @media (max-width: 575.98px) {
            body { padding: .75rem; align-items: flex-start; padding-top: 2rem; }
            .install-card { padding: 1.75rem; border-radius: .75rem; }
            .install-brand { font-size: 1.5rem; }
            .install-subtitle { font-size: .875rem; margin-bottom: 1.5rem; }
            .form-control, .form-select {
                font-size: 16px; /* prevent iOS zoom */
                padding: .625rem .75rem;
            }
            .password-wrap .form-control { padding-right: 2.75rem; }
            .btn-lg { padding: .75rem 1rem; font-size: 1rem; }
        }
        @media (max-width: 400px) {
            body { padding: .5rem; }
            .install-card { padding: 1.25rem; }
            .install-brand { font-size: 1.25rem; }
            .form-group { margin-bottom: .875rem; }
        }
    </style>
</head>
<body>
    <div class="install-wrap">
        <div class="install-card">
            <?php if (isset($success)): ?>

                <div class="success-screen">
                    <div class="success-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h1 class="success-title" data-i18n="success_heading">Installation successful!</h1>
                    <p class="success-message" data-i18n="success_message">Your CMS is ready to use.</p>
                    <div class="success-warning"><span data-i18n="success_warning">For security, delete the</span> <strong>install.php</strong> <span data-i18n="success_warning_suffix">file from your server.</span></div>
                    <div class="success-actions">
                        <a href="<?php echo e($adminUrl); ?>" class="btn btn-primary btn-block btn-lg" data-i18n="success_button_admin">Go to admin panel</a>
                        <a href="<?php echo e($siteUrl); ?>" class="btn btn-outline btn-block" data-i18n="success_button_site">Go to site</a>
                    </div>
                </div>

            <?php else: ?>

                <h1 class="install-brand"><?php echo e(MANTRA_PROJECT_INFO['name']); ?></h1>
                <p class="install-subtitle" data-i18n="subtitle">Installation</p>

                <?php if (!$allRequirementsMet): ?>

                    <p class="req-heading" data-i18n="req_heading">System requirements</p>
                    <ul class="req-list">
                        <?php foreach ($requirements as $req): ?>
                            <li class="req-item <?php echo $req['ok'] ? 'req-ok' : 'req-fail'; ?>">
                                <span class="req-icon">
                                    <?php if ($req['ok']): ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    <?php endif; ?>
                                </span>
                                <span data-i18n-req="<?php echo e($req['name']); ?>"><?php echo e($req['name']); ?></span>
                                <?php if ($req['detail']): ?>
                                    <span class="req-detail"><?php echo e($req['detail']); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="req-blocked" data-i18n="req_blocked">Please fix the issues above and reload the page.</p>

                <?php else: ?>

                    <?php if (isset($error)): ?>
                        <div class="install-error" data-i18n="<?php echo e($error); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

                        <div class="form-group">
                            <label for="site_name" class="form-label" data-i18n="label_site_name">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo e(MANTRA_PROJECT_INFO['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="language" class="form-label" data-i18n="label_language">Language</label>
                            <select class="form-select" id="language" name="language">
                                <option value="en"<?php echo $selectedLanguage === 'en' ? ' selected' : ''; ?>>English</option>
                                <option value="ru"<?php echo $selectedLanguage === 'ru' ? ' selected' : ''; ?>>Русский</option>
                            </select>
                        </div>

                        <hr class="divider">

                        <div class="form-group">
                            <label for="username" class="form-label" data-i18n="label_username">Admin Username</label>
                            <input type="text" class="form-control" id="username" name="username" pattern="[a-zA-Z0-9_\-]{3,32}" minlength="3" maxlength="32" required>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label" data-i18n="label_password">Admin Password</label>
                            <div class="password-wrap">
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                <button type="button" class="password-toggle" id="pw-toggle" aria-label="Toggle password visibility">
                                    <svg id="pw-icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg id="pw-icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>

                        <div style="margin-top:1.5rem">
                            <button type="submit" class="btn btn-primary btn-block btn-lg" data-i18n="button_install">Install</button>
                        </div>
                    </form>

                <?php endif; ?>

            <?php endif; ?>
        </div>

        <div class="install-footer">
            <?php echo e(MANTRA_PROJECT_INFO['name']); ?> v<?php echo e(MANTRA_PROJECT_INFO['version']); ?>
        </div>
    </div>

    <script>
        var translations = {
            en: {
                page_title: 'Install <?php echo e(MANTRA_PROJECT_INFO['name']); ?>',
                subtitle: 'Installation',
                success_heading: 'Installation successful!',
                success_message: 'Your CMS is ready to use.',
                success_warning: 'For security, delete the',
                success_warning_suffix: 'file from your server.',
                success_button_admin: 'Go to admin panel',
                success_button_site: 'Go to site',
                label_site_name: 'Site Name',
                label_language: 'Language',
                label_username: 'Admin Username',
                label_password: 'Admin Password',
                button_install: 'Install',
                req_heading: 'System requirements',
                req_blocked: 'Please fix the issues above and reload the page.',
                error_required_fields: 'Username and password are required.',
                error_invalid_username: 'Username must be 3-32 characters: letters, numbers, hyphens, underscores.',
                error_password_too_short: 'Password must be at least 6 characters.',
                error_create_dirs: 'Failed to create required directories. Check file permissions.',
                error_csrf: 'Security token expired. Please try again.',
                error_create_user: 'Failed to create user.'
            },
            ru: {
                page_title: 'Установка <?php echo e(MANTRA_PROJECT_INFO['name']); ?>',
                subtitle: 'Установка',
                success_heading: 'Установка завершена!',
                success_message: 'Ваша CMS готова к использованию.',
                success_warning: 'В целях безопасности удалите файл',
                success_warning_suffix: 'с вашего сервера.',
                success_button_admin: 'Перейти в админ-панель',
                success_button_site: 'Перейти на сайт',
                label_site_name: 'Название сайта',
                label_language: 'Язык',
                label_username: 'Имя администратора',
                label_password: 'Пароль администратора',
                button_install: 'Установить',
                req_heading: 'Системные требования',
                req_blocked: 'Исправьте проблемы выше и перезагрузите страницу.',
                error_required_fields: 'Имя пользователя и пароль обязательны.',
                error_invalid_username: 'Имя пользователя: 3-32 символа (буквы, цифры, дефис, подчёркивание).',
                error_password_too_short: 'Пароль должен быть не менее 6 символов.',
                error_create_dirs: 'Не удалось создать директории. Проверьте права доступа.',
                error_csrf: 'Токен безопасности истёк. Попробуйте ещё раз.',
                error_create_user: 'Не удалось создать пользователя.'
            }
        };

        function setLanguage(lang) {
            document.querySelectorAll('[data-i18n]').forEach(function(el) {
                var key = el.getAttribute('data-i18n');
                if (translations[lang] && translations[lang][key]) {
                    if (el.classList.contains('install-error')) {
                        el.querySelector('span').textContent = translations[lang][key];
                    } else {
                        el.textContent = translations[lang][key];
                    }
                }
            });
            document.getElementById('html-root').setAttribute('lang', lang);
        }

        // Language switch
        var initialLang = '<?php echo e($selectedLanguage); ?>';
        var langSelect = document.getElementById('language');

        if (langSelect) {
            langSelect.value = initialLang;
            langSelect.addEventListener('change', function() {
                setLanguage(this.value);
            });
        }

        setLanguage(initialLang);

        // Password toggle
        var pwToggle = document.getElementById('pw-toggle');
        if (pwToggle) {
            pwToggle.addEventListener('click', function() {
                var input = document.getElementById('password');
                var iconOff = document.getElementById('pw-icon-off');
                var iconOn = document.getElementById('pw-icon-on');
                if (input.type === 'password') {
                    input.type = 'text';
                    iconOff.style.display = 'none';
                    iconOn.style.display = 'block';
                } else {
                    input.type = 'password';
                    iconOff.style.display = 'block';
                    iconOn.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
