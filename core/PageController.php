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
        $db = new Database();
        $posts = $db->query('posts', array('status' => 'published'), array(
            'sort' => 'created_at',
            'order' => 'desc',
            'limit' => 10
        ));

        $view = new View();
        $view->render('home', array(
            'posts' => $posts,
            'title' => config('site.name', 'Mantra CMS')
        ));
    }

    /**
     * Render single page
     */
    public function page($params) {
        $db = new Database();
        $slug = isset($params['slug']) ? $params['slug'] : '';

        $pages = $db->query('pages', array('slug' => $slug, 'status' => 'published'));

        if (empty($pages)) {
            $this->notFound();
            return;
        }

        $page = $pages[0];

        $view = new View();
        $view->render('page', array(
            'page' => $page,
            'title' => $page['title'] . ' - ' . config('site.name', 'Mantra CMS')
        ));
    }

    /**
     * Render single post
     */
    public function post($params) {
        $db = new Database();
        $slug = isset($params['slug']) ? $params['slug'] : '';

        $posts = $db->query('posts', array('slug' => $slug, 'status' => 'published'));

        if (empty($posts)) {
            $this->notFound();
            return;
        }

        $post = $posts[0];

        $view = new View();
        $view->render('post', array(
            'post' => $post,
            'title' => $post['title'] . ' - ' . config('site.name', 'Mantra CMS')
        ));
    }

    /**
     * 404 Not Found
     */
    private function notFound() {
        http_response_code(404);
        $view = new View();
        try {
            $view->render('404', array('title' => '404 - Page Not Found'));
        } catch (Exception $e) {
            echo '<h1>404 - Page Not Found</h1>';
        }
    }
}
