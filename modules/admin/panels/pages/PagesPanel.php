<?php declare(strict_types=1);

namespace Admin;

class PagesPanel extends ContentPanel
{

    public function id()
    {
        return 'pages';
    }

    protected function getContentType()
    {
        return 'Page';
    }

    protected function getCollectionName()
    {
        return 'pages';
    }

    protected function getDefaultItem()
    {
        return [
            'title' => '',
            'slug' => '',
            'content' => '',
            'status' => 'draft',
            'show_in_navigation' => false,
            'navigation_order' => 50,
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
            'status' => app()->request()->post('status', 'draft'),
            'show_in_navigation' => (bool)app()->request()->post('show_in_navigation', false),
            'navigation_order' => (int)app()->request()->post('navigation_order', 50),
        ];
    }

    public function init($admin): void
    {
        parent::init($admin);

        app()->db()->registerSchema('pages', $this->getPath() . '/schema.php');
        $this->registerPanelHooks();
        $this->registerContentHooks();
        $this->hook('permissions.register', [$this, 'registerPermissions']);
        $this->hook('theme.navigation', [$this, 'addPagesToNavigation']);
    }

    private function registerContentHooks(): void
    {
        $s = 'pages';
        \HookRegistry::define('page.single.query', 'Modify query parameters for a single page', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('page.single.loaded', 'Filter the loaded page document before rendering', 'array', 'array', ['source' => $s]);
        \HookRegistry::define('page.single.data', 'Modify template data for a single page', 'array', 'array', ['source' => $s]);
    }

    /**
     * Register page permissions with the central registry.
     */
    public function registerPermissions($registry)
    {
        $registry->registerPermissions([
            'pages.view' => 'View pages',
            'pages.create' => 'Create pages',
            'pages.edit' => 'Edit all pages',
            'pages.edit.own' => 'Edit own pages',
            'pages.delete' => 'Delete all pages',
            'pages.delete.own' => 'Delete own pages',
        ], 'Pages');

        $registry->addRoleDefaults('editor', [
            'pages.view', 'pages.create', 'pages.edit', 'pages.delete',
        ]);
        $registry->addRoleDefaults('viewer', [
            'pages.view',
        ]);

        return $registry;
    }

    protected function renderPreview($data): void
    {
        $page = $data;

        $templates = [];
        if (!empty($page['template'])) {
            $templates[] = 'page-' . $page['template'];
        }
        if (!empty($page['slug'])) {
            $templates[] = 'page-' . $page['slug'];
        }
        $templates[] = 'page';

        $template = $this->resolveThemeTemplate($templates);

        $templateData = [
            'page' => $page,
            'title' => $page['title'] . ' - ' . config('site.name', 'Mantra CMS'),
        ];

        $html = app()->view()->fetch($template, $templateData);
        echo $this->injectPreviewBanner($html);
    }

    /**
     * Add published pages with show_in_navigation to the theme navigation menu.
     */
    public function addPagesToNavigation($navItems)
    {
        if (!is_array($navItems)) {
            $navItems = [];
        }

        $pages = app()->db()->query('pages', [
            'status' => 'published',
            'show_in_navigation' => true,
        ]);

        foreach ($pages as $page) {
            $navItems[] = [
                'id' => 'page-' . $page['slug'],
                'title' => $page['title'],
                'url' => base_url('/' . $page['slug']),
                'order' => isset($page['navigation_order']) ? (int)$page['navigation_order'] : 50,
            ];
        }

        return $navItems;
    }
}
