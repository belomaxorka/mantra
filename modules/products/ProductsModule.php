<?php
/**
 * Products Module - Example of custom content type
 *
 * Demonstrates:
 * - Registering custom content type via ContentTypeRegistry
 * - Adding custom routes for new content type
 * - Custom template for products
 * - Modifying query to add custom fields
 */

class ProductsModule extends Module {

    public function init() {
        // Register custom content type
        $this->registerProductType();

        // Register routes
        $this->hook('routes.register', array($this, 'registerRoutes'));

        // Add product-specific data to view
        $this->hook('product.single.data', array($this, 'addProductData'));
    }

    /**
     * Register product content type
     */
    private function registerProductType() {
        content_types()->register('product', array(
            'singular' => 'Product',
            'plural' => 'Products',
            'route_pattern' => '/product/{slug}',
            'collection' => 'products',
            'supports' => array(
                'title',
                'content',
                'slug',
                'status',
                'price',
                'sku',
                'stock',
                'images',
                'category'
            )
        ));
    }

    /**
     * Register routes
     */
    public function registerRoutes($data) {
        $router = $data['router'];

        // Products listing
        $router->get('/products', array($this, 'listProducts'));

        // Single product
        $router->get('/product/{slug}', array($this, 'showProduct'));

        // Products by category
        $router->get('/products/category/{category}', array($this, 'productsByCategory'));

        return $data;
    }

    /**
     * List all products
     */
    public function listProducts() {
        $products = db()->query('products', array('status' => 'published'), array(
            'sort' => 'created_at',
            'order' => 'desc'
        ));

        view('products', array(
            'products' => $products,
            'title' => 'Products - ' . config('site.name', 'Mantra CMS'),
            '_module' => 'products'
        ));
    }

    /**
     * Show single product
     */
    public function showProduct($params) {
        $app = Application::getInstance();
        $slug = isset($params['slug']) ? $params['slug'] : '';

        // Hook: allow modules to modify query
        $queryParams = $app->hooks()->fire('product.single.query', array(
            'collection' => 'products',
            'filter' => array('slug' => $slug, 'status' => 'published'),
            'slug' => $slug
        ));

        $products = db()->query($queryParams['collection'], $queryParams['filter']);

        if (empty($products)) {
            http_response_code(404);
            view('404', array('title' => '404 - Product Not Found'));
            return;
        }

        $product = $products[0];

        // Hook: allow modules to modify product data
        $product = $app->hooks()->fire('product.single.loaded', $product);

        // Prepare view data
        $data = array(
            'product' => $product,
            'title' => $product['title'] . ' - ' . config('site.name', 'Mantra CMS')
        );

        // Hook: allow modules to add data to view
        $data = $app->hooks()->fire('product.single.data', $data);

        view('product', array_merge($data, array(
            'product' => $product,
            '_module' => 'products'
        )));
    }

    /**
     * Products by category
     */
    public function productsByCategory($params) {
        $category = isset($params['category']) ? $params['category'] : '';

        $products = db()->query('products', array(
            'status' => 'published',
            'category' => $category
        ), array(
            'sort' => 'created_at',
            'order' => 'desc'
        ));

        view('products', array(
            'products' => $products,
            'category' => $category,
            'title' => ucfirst($category) . ' Products - ' . config('site.name', 'Mantra CMS'),
            '_module' => 'products'
        ));
    }

    /**
     * Add product-specific data
     */
    public function addProductData($data) {
        // Add formatted price
        if (isset($data['product']['price'])) {
            $data['formatted_price'] = '$' . number_format($data['product']['price'], 2);
        }

        // Add stock status
        if (isset($data['product']['stock'])) {
            $stock = (int)$data['product']['stock'];
            $data['stock_status'] = $stock > 0 ? 'In Stock' : 'Out of Stock';
            $data['stock_class'] = $stock > 0 ? 'text-success' : 'text-danger';
        }

        return $data;
    }
}
