<?php
/**
 * ContentAdminModule - Base class for content management modules
 * 
 * Provides CRUD operations for content types (pages, posts, etc.)
 * Reduces code duplication in admin modules
 */

abstract class ContentAdminModule extends BaseAdminModule {
    
    /**
     * Get content type name (singular)
     * @return string
     */
    abstract protected function getContentType();
    
    /**
     * Get collection name for database
     * @return string
     */
    abstract protected function getCollectionName();
    
    /**
     * Get default item data
     * @return array
     */
    abstract protected function getDefaultItem();
    
    /**
     * Extract form data from request
     * @return array
     */
    abstract protected function extractFormData();
    
    /**
     * Get admin path for redirects (without /admin prefix)
     * Override this if module ID differs from route path
     * @return string
     */
    protected function getAdminPath() {
        return $this->getCollectionName();
    }
    
    /**
     * Get list view template
     * @return string
     */
    protected function getListTemplate() {
        return $this->getId() . ':list';
    }
    
    /**
     * Get edit view template
     * @return string
     */
    protected function getEditTemplate() {
        return $this->getId() . ':edit';
    }
    
    /**
     * Generate ID for new item
     * @param array $data Item data
     * @return string
     */
    protected function generateId($data) {
        $slug = $data['slug'] ?? '';
        if (empty($slug) && isset($data['title'])) {
            $slug = slugify($data['title']);
        }
        
        $id = $slug;
        if (db()->exists($this->getCollectionName(), $id)) {
            $id = $slug . '-' . uniqid();
        }
        
        return $id;
    }
    
    /**
     * Ensure slug is set, generate from title if empty
     * @param array $data Item data
     * @return array Modified data with slug
     */
    protected function ensureSlug($data) {
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = slugify($data['title']);
        }
        return $data;
    }
    
    /**
     * List all items
     */
    public function listItems() {
        $items = db()->query($this->getCollectionName(), array(), array(
            'sort' => 'updated_at',
            'order' => 'desc'
        ));
        
        $content = $this->renderView($this->getListTemplate(), array(
            strtolower($this->getCollectionName()) => $items
        ));
        
        return $this->renderAdmin($this->getName(), $content);
    }
    
    /**
     * Show new item form
     */
    public function newItem() {
        $content = $this->renderView($this->getEditTemplate(), array(
            strtolower($this->getContentType()) => $this->getDefaultItem(),
            'isNew' => true,
            'csrf_token' => auth()->generateCsrfToken()
        ));
        
        return $this->renderAdmin('New ' . $this->getContentType(), $content);
    }
    
    /**
     * Create new item
     */
    public function createItem() {
        if (!$this->verifyCsrf()) {
            return;
        }
        
        $data = $this->extractFormData();
        $data = $this->ensureSlug($data);
        $data['author'] = $this->getUser()['username'] ?? 'Unknown';
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $id = $this->generateId($data);
        
        db()->write($this->getCollectionName(), $id, $data);
        
        $this->redirectAdmin($this->getAdminPath());
    }
    
    /**
     * Show edit item form
     */
    public function editItem($params) {
        $id = $params['id'] ?? '';
        $item = db()->read($this->getCollectionName(), $id);
        
        if (!$item) {
            http_response_code(404);
            return $this->renderAdmin('Not Found', '<div class="alert alert-danger">' . $this->getContentType() . ' not found</div>');
        }
        
        $content = $this->renderView($this->getEditTemplate(), array(
            strtolower($this->getContentType()) => $item,
            'isNew' => false,
            'csrf_token' => auth()->generateCsrfToken()
        ));
        
        return $this->renderAdmin('Edit ' . $this->getContentType(), $content);
    }
    
    /**
     * Update existing item
     */
    public function updateItem($params) {
        if (!$this->verifyCsrf()) {
            return;
        }
        
        $id = $params['id'] ?? '';
        $item = db()->read($this->getCollectionName(), $id);
        
        if (!$item) {
            http_response_code(404);
            echo $this->getContentType() . ' not found';
            return;
        }
        
        $data = $this->extractFormData();
        $data = $this->ensureSlug($data);
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Preserve original fields
        $data['author'] = $item['author'] ?? 'Unknown';
        $data['created_at'] = $item['created_at'] ?? date('Y-m-d H:i:s');
        
        db()->write($this->getCollectionName(), $id, $data);
        
        $this->redirectAdmin($this->getAdminPath());
    }
    
    /**
     * Delete item
     */
    public function deleteItem($params) {
        if (!$this->verifyCsrf()) {
            return;
        }
        
        $id = $params['id'] ?? '';
        
        if (db()->exists($this->getCollectionName(), $id)) {
            db()->delete($this->getCollectionName(), $id);
        }
        
        $this->redirectAdmin($this->getAdminPath());
    }
}
