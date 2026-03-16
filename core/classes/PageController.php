<?php
/**
 * PageController - Handles public page rendering
 * Core controller for site pages (not admin)
 */

class PageController {

    /**
     * Render home page
     */
    public function home() {
        $app = Application::getInstance();

        // Hook: allow modules to modify query parameters
        $queryParams = $app->hooks()->fire('page.home.query', array(
            'collection' => 'posts',
            'filter' => array('status' => 'published'),
            'options' => array(
                'sort' => 'created_at',
                'order' => 'desc',
                'limit' => 10
            )
        ));

        $posts = db()->query(
            $queryParams['collection'],
            $queryParams['filter'],
            $queryParams['options']
        );

        // Hook: allow modules to modify posts data
        $posts = $app->hooks()->fire('page.home.posts', $posts);

        // Prepare view data
        $data = array(
            'posts' => $posts,
            'title' => config('site.name', 'Mantra CMS')
        );

        // Hook: allow modules to add data to view
        $data = $app->hooks()->fire('page.home.data', $data);

        view('home', $data);
    }

    /**
     * Render blog listing page
     */
    public function blog() {
        $app = Application::getInstance();

        // Hook: allow modules to modify query parameters
        $queryParams = $app->hooks()->fire('page.blog.query', array(
            'collection' => 'posts',
            'filter' => array('status' => 'published'),
            'options' => array(
                'sort' => 'created_at',
                'order' => 'desc',
                'limit' => 20
            )
        ));

        $posts = db()->query(
            $queryParams['collection'],
            $queryParams['filter'],
            $queryParams['options']
        );

        // Hook: allow modules to modify posts data
        $posts = $app->hooks()->fire('page.blog.posts', $posts);

        // Prepare view data
        $data = array(
            'posts' => $posts,
            'title' => 'Blog - ' . config('site.name', 'Mantra CMS')
        );

        // Hook: allow modules to add data to view
        $data = $app->hooks()->fire('page.blog.data', $data);

        view('blog', $data);
    }

    /**
     * Render single page
     */
    public function page($params) {
        $app = Application::getInstance();
        $slug = isset($params['slug']) ? $params['slug'] : '';

        // Hook: allow modules to modify query
        $queryParams = $app->hooks()->fire('page.single.query', array(
            'collection' => 'pages',
            'filter' => array('slug' => $slug, 'status' => 'published'),
            'slug' => $slug
        ));

        $pages = db()->query($queryParams['collection'], $queryParams['filter']);

        if (empty($pages)) {
            $this->notFound();
            return;
        }

        $page = $pages[0];

        // Hook: allow modules to modify page data
        $page = $app->hooks()->fire('page.single.loaded', $page);

        // Prepare view data
        $data = array(
            'page' => $page,
            'title' => $page['title'] . ' - ' . config('site.name', 'Mantra CMS')
        );

        // Hook: allow modules to add data to view
        $data = $app->hooks()->fire('page.single.data', $data);

        // Determine template (support template hierarchy)
        $template = $this->getPageTemplate($page);

        view($template, $data);
    }

    /**
     * Render single post
     */
    public function post($params) {
        $app = Application::getInstance();
        $slug = isset($params['slug']) ? $params['slug'] : '';

        // Hook: allow modules to modify query
        $queryParams = $app->hooks()->fire('post.single.query', array(
            'collection' => 'posts',
            'filter' => array('slug' => $slug, 'status' => 'published'),
            'slug' => $slug
        ));

        $posts = db()->query($queryParams['collection'], $queryParams['filter']);

        if (empty($posts)) {
            $this->notFound();
            return;
        }

        $post = $posts[0];

        // Hook: allow modules to modify post data
        $post = $app->hooks()->fire('post.single.loaded', $post);

        // Prepare view data
        $data = array(
            'post' => $post,
            'title' => $post['title'] . ' - ' . config('site.name', 'Mantra CMS')
        );

        // Hook: allow modules to add data to view
        $data = $app->hooks()->fire('post.single.data', $data);

        // Determine template (support template hierarchy)
        $template = $this->getPostTemplate($post);

        view($template, $data);
    }

    /**
     * Get template for page (supports hierarchy)
     */
    private function getPageTemplate($page) {
        $templates = array();

        // Custom template from page meta
        if (isset($page['template']) && !empty($page['template'])) {
            $templates[] = 'page-' . $page['template'];
        }

        // Template by slug
        $templates[] = 'page-' . $page['slug'];

        // Default page template
        $templates[] = 'page';

        // Return first existing template
        return $this->findTemplate($templates);
    }

    /**
     * Get template for post (supports hierarchy)
     */
    private function getPostTemplate($post) {
        $templates = array();

        // Custom template from post meta
        if (isset($post['template']) && !empty($post['template'])) {
            $templates[] = 'post-' . $post['template'];
        }

        // Template by category
        if (isset($post['category']) && !empty($post['category'])) {
            $templates[] = 'post-' . $post['category'];
        }

        // Template by slug
        $templates[] = 'post-' . $post['slug'];

        // Default post template
        $templates[] = 'post';

        // Return first existing template
        return $this->findTemplate($templates);
    }

    /**
     * Find first existing template from list
     */
    private function findTemplate($templates) {
        $app = Application::getInstance();
        $theme = $app->config('theme.active', 'default');
        $themePath = MANTRA_THEMES . '/' . $theme;

        foreach ($templates as $template) {
            $path = $themePath . '/templates/' . $template . '.php';
            if (file_exists($path)) {
                return $template;
            }
        }

        // Return last template as fallback
        return end($templates);
    }

    /**
     * 404 Not Found
     */
    private function notFound() {
        http_response_code(404);
        try {
            view('404', array('title' => '404 - Page Not Found'));
        } catch (Exception $e) {
            echo '<h1>404 - Page Not Found</h1>';
        }
    }
}
