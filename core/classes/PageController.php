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
        $perPage = (int)config('content.posts_per_page', 10);
        $page = max(1, (int)app()->request()->query('page', 1));

        $filter = array('status' => 'published');

        // Hook: allow modules to modify query parameters
        $queryParams = $app->hooks()->fire('page.home.query', array(
            'collection' => 'posts',
            'filter' => $filter,
            'options' => array(
                'sort' => 'created_at',
                'order' => 'desc',
            )
        ));

        $total = app()->db()->count($queryParams['collection'], $queryParams['filter']);
        $paginator = new Paginator($total, $perPage, $page);

        $queryParams['options']['limit'] = $paginator->perPage();
        $queryParams['options']['offset'] = $paginator->offset();

        $posts = app()->db()->query(
            $queryParams['collection'],
            $queryParams['filter'],
            $queryParams['options']
        );

        // Hook: allow modules to modify posts data
        $posts = $app->hooks()->fire('page.home.posts', $posts);

        // Prepare view data
        $data = array(
            'posts' => $posts,
            'paginator' => $paginator,
            'title' => config('site.name', 'Mantra CMS')
        );

        // Hook: allow modules to add data to view
        $data = $app->hooks()->fire('page.home.data', $data);

        app()->view()->render('home', $data);
    }

    /**
     * Render blog listing page
     */
    public function blog() {
        $app = Application::getInstance();
        $perPage = (int)config('content.posts_per_page', 10);
        $page = max(1, (int)app()->request()->query('page', 1));

        $filter = array('status' => 'published');

        // Hook: allow modules to modify query parameters
        $queryParams = $app->hooks()->fire('page.blog.query', array(
            'collection' => 'posts',
            'filter' => $filter,
            'options' => array(
                'sort' => 'created_at',
                'order' => 'desc',
            )
        ));

        $total = app()->db()->count($queryParams['collection'], $queryParams['filter']);
        $paginator = new Paginator($total, $perPage, $page);

        $queryParams['options']['limit'] = $paginator->perPage();
        $queryParams['options']['offset'] = $paginator->offset();

        $posts = app()->db()->query(
            $queryParams['collection'],
            $queryParams['filter'],
            $queryParams['options']
        );

        // Hook: allow modules to modify posts data
        $posts = $app->hooks()->fire('page.blog.posts', $posts);

        // Prepare view data
        $data = array(
            'posts' => $posts,
            'paginator' => $paginator,
            'title' => 'Blog - ' . config('site.name', 'Mantra CMS')
        );

        // Hook: allow modules to add data to view
        $data = $app->hooks()->fire('page.blog.data', $data);

        app()->view()->render('blog', $data);
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

        $pages = app()->db()->query($queryParams['collection'], $queryParams['filter']);

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

        app()->view()->render($template, $data);
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

        $posts = app()->db()->query($queryParams['collection'], $queryParams['filter']);

        if (empty($posts)) {
            $this->notFound();
            return;
        }

        $post = $posts[0];

        // Hook: allow modules to modify post data
        $post = $app->hooks()->fire('post.single.loaded', $post);

        // Find adjacent posts for prev/next navigation
        $adjacent = $this->getAdjacentPosts($post);

        // Estimate reading time (~200 words per minute)
        $wordCount = str_word_count(strip_tags($post['content']));
        $readingTime = max(1, (int)ceil($wordCount / 200));

        // Prepare view data
        $data = array(
            'post' => $post,
            'readingTime' => $readingTime,
            'prevPost' => $adjacent['prev'],
            'nextPost' => $adjacent['next'],
            'title' => $post['title'] . ' - ' . config('site.name', 'Mantra CMS')
        );

        // Hook: allow modules to add data to view
        $data = $app->hooks()->fire('post.single.data', $data);

        // Determine template (support template hierarchy)
        $template = $this->getPostTemplate($post);

        app()->view()->render($template, $data);
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
     * Get previous (older) and next (newer) published posts.
     *
     * @return array ['prev' => array|null, 'next' => array|null]
     */
    private function getAdjacentPosts($currentPost) {
        $allPosts = app()->db()->query('posts', array('status' => 'published'), array(
            'sort' => 'created_at',
            'order' => 'desc',
        ));

        $prev = null;
        $next = null;
        foreach ($allPosts as $i => $p) {
            if ($p['_id'] === $currentPost['_id']) {
                $next = isset($allPosts[$i - 1]) ? $allPosts[$i - 1] : null;
                $prev = isset($allPosts[$i + 1]) ? $allPosts[$i + 1] : null;
                break;
            }
        }

        return array('prev' => $prev, 'next' => $next);
    }

    /**
     * Find first existing template from list
     */
    private function findTemplate($templates) {
        $app = Application::getInstance();
        $theme = config('theme.active', 'default');
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
        abort(404);
    }
}
