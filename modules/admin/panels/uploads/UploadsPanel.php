<?php declare(strict_types=1);
/**
 * UploadsPanel - File upload and management
 *
 * Handles file uploads, metadata storage, and file browsing.
 * Files are stored in MANTRA_UPLOADS (public directory).
 * Metadata is stored in the 'uploads' collection (JSON).
 */

namespace Admin;

class UploadsPanel extends AdminPanel
{

    /** @var int Maximum upload size in bytes (10 MB) */
    public const MAX_UPLOAD_SIZE = 10485760;

    /** @var array Allowed MIME types */
    private static $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
    ];

    public function id()
    {
        return 'uploads';
    }

    public function init($admin): void
    {
        parent::init($admin);

        app()->db()->registerSchema('uploads', $this->getPath() . '/schema.php');
        $this->hook('permissions.register', [$this, 'registerPermissions']);

        $this->ajaxAction('uploads.upload', [$this, 'handleAjaxUpload'], [
            'permission' => 'uploads.upload',
        ]);
    }

    /**
     * Register upload permissions.
     */
    public function registerPermissions($registry)
    {
        $registry->registerPermissions([
            'uploads.view' => 'View uploads',
            'uploads.upload' => 'Upload files',
            'uploads.delete' => 'Delete any file',
            'uploads.delete.own' => 'Delete own files',
        ], 'Uploads');

        $registry->addRoleDefaults('editor', [
            'uploads.view', 'uploads.upload', 'uploads.delete',
        ]);
        $registry->addRoleDefaults('viewer', [
            'uploads.view',
        ]);

        return $registry;
    }

    public function registerRoutes($admin): void
    {
        $admin->adminRoute('GET', 'uploads', [$this, 'listFiles']);
        $admin->adminRoute('POST', 'uploads', [$this, 'uploadFile']);
        $admin->adminRoute('GET', 'uploads/edit/{id}', [$this, 'editFile']);
        $admin->adminRoute('POST', 'uploads/edit/{id}', [$this, 'updateFile']);
        $admin->adminRoute('POST', 'uploads/delete/{id}', [$this, 'deleteFile']);
        $admin->adminRoute('POST', 'uploads/api/upload', [$this, 'apiUpload']);
    }

    // ========== Actions ==========

    /**
     * List uploaded files.
     */
    public function listFiles()
    {
        if (!$this->requirePermission('uploads.view')) return;

        $files = app()->db()->query('uploads', [], [
            'sort' => 'created_at',
            'order' => 'desc',
        ]);

        $userManager = new \User();
        $user = $this->getUser();

        $content = $this->renderView('list', [
            'files' => $files,
            'canUpload' => (bool)$userManager->hasPermission($user, 'uploads.upload'),
            'canDelete' => $userManager->hasPermission($user, 'uploads.delete'),
            'csrf_token' => $this->auth()->generateCsrfToken(),
            'uploadsUrl' => $this->getUploadsBaseUrl(),
        ]);

        return $this->renderAdmin(t('admin-uploads.title'), $content, [
            'breadcrumbs' => [
                ['title' => t('admin-dashboard.title'), 'url' => base_url('/admin')],
                ['title' => t('admin-uploads.title')],
            ],
        ]);
    }

    /**
     * Handle file upload from form.
     */
    public function uploadFile()
    {
        if (!$this->requirePermission('uploads.upload')) return;
        if (!$this->verifyCsrf()) return;

        $error = $this->processUpload();

        if ($error !== null) {
            $files = app()->db()->query('uploads', [], [
                'sort' => 'created_at',
                'order' => 'desc',
            ]);

            $userManager = new \User();
            $user = $this->getUser();

            $content = $this->renderView('list', [
                'files' => $files,
                'canUpload' => true,
                'canDelete' => $userManager->hasPermission($user, 'uploads.delete'),
                'csrf_token' => $this->auth()->generateCsrfToken(),
                'uploadsUrl' => $this->getUploadsBaseUrl(),
                'error' => $error,
            ]);

            return $this->renderAdmin(t('admin-uploads.title'), $content, [
                'breadcrumbs' => [
                    ['title' => t('admin-dashboard.title'), 'url' => base_url('/admin')],
                    ['title' => t('admin-uploads.title')],
                ],
            ]);
        }

        $this->redirectAdmin('uploads');
    }

    /**
     * API endpoint for CKEditor / AJAX uploads.
     * Returns JSON response.
     */
    public function apiUpload(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userManager = new \User();
        $user = $this->getUser();
        if (!$user || !$userManager->hasPermission($user, 'uploads.upload')) {
            http_response_code(403);
            echo json_encode(['error' => ['message' => 'Permission denied']]);
            return;
        }

        $error = $this->processUpload();
        if ($error !== null) {
            http_response_code(400);
            echo json_encode(['error' => ['message' => $error]]);
            return;
        }

        // Return the URL of the last uploaded file
        $files = app()->db()->query('uploads', [], [
            'sort' => 'created_at',
            'order' => 'desc',
            'limit' => 1,
        ]);

        if (!empty($files)) {
            $url = $this->getUploadsBaseUrl() . '/' . $files[0]['path'];
            echo json_encode(['url' => $url]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => ['message' => 'Upload failed']]);
        }
    }

    /**
     * AJAX action handler for uploads (new unified endpoint).
     * Used via: Mantra.ajax('uploads.upload', formData)
     */
    public function handleAjaxUpload($request, $access)
    {
        $error = $this->processUpload();
        if ($error !== null) {
            throw new \Ajax\AjaxException($error, 400);
        }

        $files = app()->db()->query('uploads', [], [
            'sort' => 'created_at',
            'order' => 'desc',
            'limit' => 1,
        ]);

        if (empty($files)) {
            throw new \Ajax\AjaxException('Upload failed', 500);
        }

        return ['url' => $this->getUploadsBaseUrl() . '/' . $files[0]['path']];
    }

    /**
     * Show file detail / edit form.
     */
    public function editFile($params)
    {
        if (!$this->requirePermission('uploads.view')) return;

        $id = $params['id'] ?? '';
        $file = app()->db()->read('uploads', $id);

        if (!$file) {
            $this->renderErrorPage(t('admin-uploads.not_found'), 404);
            return;
        }

        $userManager = new \User();
        $user = $this->getUser();

        $content = $this->renderView('edit', [
            'file' => $file,
            'canDelete' => $userManager->hasPermission($user, 'uploads.delete'),
            'csrf_token' => $this->auth()->generateCsrfToken(),
            'uploadsUrl' => $this->getUploadsBaseUrl(),
        ]);

        $title = t('admin-uploads.edit_file');
        return $this->renderAdmin($title, $content, [
            'breadcrumbs' => [
                ['title' => t('admin-dashboard.title'), 'url' => base_url('/admin')],
                ['title' => t('admin-uploads.title'), 'url' => base_url('/admin/uploads')],
                ['title' => $title],
            ],
        ]);
    }

    /**
     * Update file metadata (original_name only).
     */
    public function updateFile($params): void
    {
        if (!$this->requirePermission('uploads.upload')) return;
        if (!$this->verifyCsrf()) return;

        $id = $params['id'] ?? '';
        $file = app()->db()->read('uploads', $id);

        if (!$file) {
            $this->renderErrorPage(t('admin-uploads.not_found'), 404);
            return;
        }

        $file['original_name'] = app()->request()->postTrimmed('original_name');
        unset($file['_id']);

        app()->db()->write('uploads', $id, $file);

        $this->redirectAdmin('uploads/edit/' . $id);
    }

    /**
     * Delete a file (physical + metadata).
     */
    public function deleteFile($params): void
    {
        $access = $this->requirePermission('uploads.delete');
        if ($access === false) return;
        if (!$this->verifyCsrf()) return;

        $id = $params['id'] ?? '';
        $file = app()->db()->read('uploads', $id);

        if (!$file) {
            $this->redirectAdmin('uploads');
            return;
        }

        // Ownership check when access is 'own'
        if ($access === 'own') {
            $userManager = new \User();
            if (!$userManager->canEdit($this->getUser(), $file)) {
                $this->renderErrorPage(t('admin.common.access_denied'));
                return;
            }
        }

        // Delete physical file
        $filePath = MANTRA_UPLOADS . '/' . $file['path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // Delete metadata
        app()->db()->delete('uploads', $id);

        $this->redirectAdmin('uploads');
    }

    // ========== Upload Processing ==========

    /**
     * Process file upload. Returns error message or null on success.
     *
     * @return string|null Error message, or null on success
     */
    private function processUpload()
    {
        $fileData = app()->request()->file('file');

        if (!$fileData || !is_array($fileData) || $fileData['error'] !== UPLOAD_ERR_OK) {
            $code = is_array($fileData) ? $fileData['error'] : -1;
            return $this->getUploadErrorMessage($code);
        }

        // Validate size
        if ($fileData['size'] > self::MAX_UPLOAD_SIZE) {
            return t('admin-uploads.error_too_large');
        }

        // Validate MIME type
        $mime = $this->detectMimeType($fileData['tmp_name'], $fileData['name']);
        if (!in_array($mime, self::$allowedMimes, true)) {
            return t('admin-uploads.error_type_not_allowed');
        }

        // Sanitize filename
        $filename = $this->sanitizeFilename($fileData['name']);
        if ($filename === '') {
            return t('admin-uploads.error_invalid_filename');
        }

        // Build date-based path
        $subdir = date('Y/m');
        $targetDir = MANTRA_UPLOADS . '/' . $subdir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0o755, true);
        }

        // Ensure unique filename
        $filename = $this->ensureUniqueFilename($targetDir, $filename);
        $relativePath = $subdir . '/' . $filename;
        $targetPath = $targetDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($fileData['tmp_name'], $targetPath)) {
            return t('admin-uploads.error_move_failed');
        }

        // Save metadata
        $user = $this->getUser();
        $id = app()->db()->generateId();
        $metadata = [
            'filename' => $filename,
            'original_name' => $fileData['name'],
            'mime_type' => $mime,
            'size' => (int)$fileData['size'],
            'path' => $relativePath,
            'author' => $user['username'] ?? 'Unknown',
            'author_id' => $user['_id'] ?? '',
            'created_at' => clock()->timestamp(),
        ];

        app()->db()->write('uploads', $id, $metadata);

        return null;
    }

    /**
     * Detect MIME type using finfo, with extension fallback.
     */
    private function detectMimeType($tmpPath, $originalName)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        if ($mime && $mime !== 'application/octet-stream') {
            return $mime;
        }

        // Fallback to extension-based detection
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf', 'txt' => 'text/plain',
            'zip' => 'application/zip',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * Sanitize a filename: ASCII-safe, no special chars.
     */
    private function sanitizeFilename($name)
    {
        $info = pathinfo($name);
        $ext = isset($info['extension']) ? strtolower($info['extension']) : '';
        $base = $info['filename'] ?? '';

        // Transliterate common chars, strip anything non-alphanumeric
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '-', $base);
        $base = preg_replace('/-+/', '-', $base);
        $base = trim($base, '-');

        if ($base === '') {
            $base = 'file-' . date('His');
        }

        // Limit length
        if (strlen($base) > 100) {
            $base = substr($base, 0, 100);
        }

        return $ext !== '' ? $base . '.' . $ext : $base;
    }

    /**
     * Ensure filename is unique in the target directory.
     */
    private function ensureUniqueFilename($dir, $filename)
    {
        if (!file_exists($dir . '/' . $filename)) {
            return $filename;
        }

        $info = pathinfo($filename);
        $base = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $counter = 1;

        while (file_exists($dir . '/' . $base . '-' . $counter . $ext)) {
            $counter++;
        }

        return $base . '-' . $counter . $ext;
    }

    /**
     * Get human-readable upload error message.
     */
    private function getUploadErrorMessage($code)
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return t('admin-uploads.error_too_large');
            case UPLOAD_ERR_NO_FILE:
                return t('admin-uploads.error_no_file');
            case UPLOAD_ERR_PARTIAL:
                return t('admin-uploads.error_partial');
            default:
                return t('admin-uploads.error_upload_failed');
        }
    }

    // ========== Helpers ==========

    /**
     * Get the public base URL for uploaded files.
     */
    private function getUploadsBaseUrl()
    {
        return base_url('/uploads');
    }

    /**
     * Check if a MIME type is an image.
     */
    public static function isImage($mime)
    {
        return str_starts_with($mime, 'image/');
    }

    /**
     * Format file size for display.
     */
    public static function formatSize($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
