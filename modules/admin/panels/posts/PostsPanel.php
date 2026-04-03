<?php declare(strict_types=1);

namespace Admin;

class PostsPanel extends ContentPanel
{

    public function id()
    {
        return 'posts';
    }

    public function init($admin): void
    {
        parent::init($admin);

        app()->db()->registerSchema('posts', $this->getPath() . '/schema.php');
        $this->registerPanelHooks();
        $this->registerContentHooks();
        $this->hook('permissions.register', [$this, 'registerPermissions']);
    }

    private function registerContentHooks(): void
    {
        $s = 'posts';
        \HookRegistry::define('page.home.query', 'Modify query parameters for the home page post list', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('page.home.posts', 'Filter the post list on the home page', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('page.home.data', 'Modify template data for the home page', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('page.blog.query', 'Modify query parameters for the blog listing', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('page.blog.posts', 'Filter the post list on the blog page', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('page.blog.data', 'Modify template data for the blog page', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('post.single.query', 'Modify query parameters for a single post', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('post.single.loaded', 'Filter the loaded post document before rendering', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('post.single.data', 'Modify template data for a single post', 'array', 'array', ['source' => $s]);
    }

    /**
     * Register post permissions with the central registry.
     */
    public function registerPermissions($registry)
    {
        $registry->registerPermissions([
            'posts.view' => 'View posts',
            'posts.create' => 'Create posts',
            'posts.edit' => 'Edit all posts',
            'posts.edit.own' => 'Edit own posts',
            'posts.delete' => 'Delete all posts',
            'posts.delete.own' => 'Delete own posts',
        ], 'Posts');

        $registry->addRoleDefaults('editor', [
            'posts.view', 'posts.create', 'posts.edit', 'posts.delete',
        ]);
        $registry->addRoleDefaults('viewer', [
            'posts.view',
        ]);

        return $registry;
    }

    protected function getContentType()
    {
        return 'Post';
    }

    protected function getCollectionName()
    {
        return 'posts';
    }

    protected function getDefaultItem()
    {
        return [
            'title' => '',
            'slug' => '',
            'content' => '',
            'excerpt' => '',
            'status' => 'draft',
            'category' => '',
            'image' => '',
            'author' => '',
            'author_id' => '',
            'created_at' => '',
            'updated_at' => '',
        ];
    }

    protected function extractFormData()
    {
        return [
            'title' => app()->request()->postTrimmed('title'),
            'slug' => app()->request()->postTrimmed('slug'),
            'content' => app()->request()->post('content', ''),
            'excerpt' => app()->request()->post('excerpt', ''),
            'status' => app()->request()->post('status', 'draft'),
            'image' => app()->request()->postTrimmed('image'),
        ];
    }

    protected function renderPreview($data): void
    {
        $post = $data;

        $wordCount = str_word_count(strip_tags($post['content']));
        $readingTime = max(1, (int)ceil($wordCount / 200));

        $templates = [];
        if (!empty($post['template'])) {
            $templates[] = 'post-' . $post['template'];
        }
        if (!empty($post['category'])) {
            $templates[] = 'post-' . $post['category'];
        }
        if (!empty($post['slug'])) {
            $templates[] = 'post-' . $post['slug'];
        }
        $templates[] = 'post';

        $template = $this->resolveThemeTemplate($templates);

        $templateData = [
            'post' => $post,
            'readingTime' => $readingTime,
            'prevPost' => null,
            'nextPost' => null,
            'title' => $post['title'] . ' - ' . config('site.name', 'Mantra CMS'),
        ];

        $html = app()->view()->fetch($template, $templateData);
        echo $this->injectPreviewBanner($html);
    }
}
