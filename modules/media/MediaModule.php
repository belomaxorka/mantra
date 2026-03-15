<?php
/**
 * MediaModule - Media file management
 */

class MediaModule extends Module {
    
    private $allowedTypes = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx');
    private $maxSize = 5242880; // 5MB
    
    public function init() {
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }
    
    /**
     * Register routes
     */
    public function registerRoutes($data) {
        $router = $data['router'];
        
        $router->get('/admin/media', array($this, 'index'));
        $router->post('/admin/media/upload', array($this, 'upload'));
        $router->post('/admin/media/delete', array($this, 'delete'));
        
        return $data;
    }
    
    /**
     * Media library
     */
    public function index() {
        if (!auth()->check()) {
            redirect(base_url('/admin/login'));
            return;
        }
        
        $files = $this->getMediaFiles();
        
        $this->view('media:index', array(
            'files' => $files
        ));
    }
    
    /**
     * Upload file
     */
    public function upload() {
        if (!auth()->check()) {
            json_response(array('error' => 'Unauthorized'), 401);
            return;
        }
        
        if (!isset($_FILES['file'])) {
            json_response(array('error' => 'No file uploaded'), 400);
            return;
        }
        
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate
        if (!in_array($ext, $this->allowedTypes)) {
            json_response(array('error' => 'File type not allowed'), 400);
            return;
        }
        
        if ($file['size'] > $this->maxSize) {
            json_response(array('error' => 'File too large'), 400);
            return;
        }
        
        // Generate unique filename
        $filename = uniqid() . '.' . $ext;
        $destination = MANTRA_UPLOADS . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            json_response(array(
                'success' => true,
                'filename' => $filename,
                'url' => base_url('/uploads/' . $filename)
            ));
        } else {
            json_response(array('error' => 'Upload failed'), 500);
        }
    }
    
    /**
     * Delete file
     */
    public function delete() {
        if (!auth()->check()) {
            json_response(array('error' => 'Unauthorized'), 401);
            return;
        }
        
        $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
        $path = MANTRA_UPLOADS . '/' . basename($filename);
        
        if (file_exists($path) && unlink($path)) {
            json_response(array('success' => true));
        } else {
            json_response(array('error' => 'Delete failed'), 500);
        }
    }
    
    /**
     * Get all media files
     */
    private function getMediaFiles() {
        $files = array();
        $dir = MANTRA_UPLOADS;
        
        if (is_dir($dir)) {
            $items = scandir($dir);
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_file($dir . '/' . $item)) {
                    $files[] = array(
                        'name' => $item,
                        'url' => base_url('/uploads/' . $item),
                        'size' => filesize($dir . '/' . $item),
                        'date' => date('Y-m-d H:i:s', filemtime($dir . '/' . $item))
                    );
                }
            }
        }
        
        return $files;
    }
}
