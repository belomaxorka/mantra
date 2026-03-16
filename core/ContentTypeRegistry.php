<?php
/**
 * ContentTypeRegistry - Register and manage custom content types
 * Allows modules to register new content types (products, events, etc.)
 */

class ContentTypeRegistry {
    private static $instance = null;
    private $types = array();

    private function __construct() {
        // Register default content types
        $this->registerDefaults();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register default content types
     */
    private function registerDefaults() {
        // Pages
        $this->register('page', array(
            'singular' => 'Page',
            'plural' => 'Pages',
            'route_pattern' => '/{slug}',
            'collection' => 'pages',
            'supports' => array('title', 'content', 'slug', 'status', 'template')
        ));

        // Posts
        $this->register('post', array(
            'singular' => 'Post',
            'plural' => 'Posts',
            'route_pattern' => '/post/{slug}',
            'collection' => 'posts',
            'supports' => array('title', 'content', 'slug', 'status', 'category', 'template', 'author', 'excerpt')
        ));
    }

    /**
     * Register a new content type
     *
     * @param string $type Content type identifier (e.g., 'product', 'event')
     * @param array $config Configuration array
     */
    public function register($type, $config) {
        $defaults = array(
            'singular' => ucfirst($type),
            'plural' => ucfirst($type) . 's',
            'route_pattern' => '/' . $type . '/{slug}',
            'collection' => $type . 's',
            'supports' => array('title', 'content', 'slug', 'status'),
            'controller' => null, // Optional custom controller
            'template' => $type, // Default template name
        );

        $this->types[$type] = array_merge($defaults, $config);
    }

    /**
     * Get content type configuration
     */
    public function get($type) {
        return isset($this->types[$type]) ? $this->types[$type] : null;
    }

    /**
     * Get all registered content types
     */
    public function all() {
        return $this->types;
    }

    /**
     * Check if content type exists
     */
    public function has($type) {
        return isset($this->types[$type]);
    }

    /**
     * Unregister a content type
     */
    public function unregister($type) {
        if (isset($this->types[$type])) {
            unset($this->types[$type]);
        }
    }
}
