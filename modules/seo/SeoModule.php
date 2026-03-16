<?php
/**
 * SEO Module - Example of extensibility features
 *
 * Demonstrates:
 * - Hooking into page rendering
 * - Adding meta tags to <head>
 * - Modifying page data
 * - Providing widgets
 */

class SeoModule extends Module {

    public function init() {
        // Hook into theme head to add meta tags
        $this->hook('theme.head', array($this, 'addMetaTags'));

        // Hook into page data to add SEO fields
        $this->hook('page.single.data', array($this, 'addSeoData'));
        $this->hook('post.single.data', array($this, 'addSeoData'));
        $this->hook('product.single.data', array($this, 'addSeoData'));

        // Hook into widget rendering to provide breadcrumbs
        $this->hook('widget.render', array($this, 'renderWidget'));
    }

    /**
     * Add meta tags to <head>
     */
    public function addMetaTags($content) {
        $request = request();
        $path = $request->path();

        // Get current page/post data from request
        $title = config('site.name', 'Mantra CMS');
        $description = 'A powerful flat-file CMS';
        $image = base_url('/themes/default/assets/images/og-image.jpg');

        // Build meta tags
        $meta = array();

        // Open Graph
        $meta[] = '<meta property="og:title" content="' . e($title) . '">';
        $meta[] = '<meta property="og:description" content="' . e($description) . '">';
        $meta[] = '<meta property="og:image" content="' . e($image) . '">';
        $meta[] = '<meta property="og:url" content="' . e(base_url($path)) . '">';
        $meta[] = '<meta property="og:type" content="website">';

        // Twitter Card
        $meta[] = '<meta name="twitter:card" content="summary_large_image">';
        $meta[] = '<meta name="twitter:title" content="' . e($title) . '">';
        $meta[] = '<meta name="twitter:description" content="' . e($description) . '">';
        $meta[] = '<meta name="twitter:image" content="' . e($image) . '">';

        // SEO meta
        $meta[] = '<meta name="description" content="' . e($description) . '">';
        $meta[] = '<link rel="canonical" href="' . e(base_url($path)) . '">';

        return $content . "\n    " . implode("\n    ", $meta);
    }

    /**
     * Add SEO data to page/post/product view
     */
    public function addSeoData($data) {
        // Add breadcrumbs data
        $breadcrumbs = array(
            array('title' => 'Home', 'url' => base_url())
        );

        if (isset($data['page'])) {
            $breadcrumbs[] = array(
                'title' => $data['page']['title'],
                'url' => null
            );
        } elseif (isset($data['post'])) {
            $breadcrumbs[] = array('title' => 'Blog', 'url' => base_url('/blog'));
            $breadcrumbs[] = array(
                'title' => $data['post']['title'],
                'url' => null
            );
        } elseif (isset($data['product'])) {
            $breadcrumbs[] = array('title' => 'Products', 'url' => base_url('/products'));
            if (isset($data['product']['category'])) {
                $breadcrumbs[] = array(
                    'title' => ucfirst($data['product']['category']),
                    'url' => base_url('/products/category/' . $data['product']['category'])
                );
            }
            $breadcrumbs[] = array(
                'title' => $data['product']['title'],
                'url' => null
            );
        }

        $data['breadcrumbs'] = $breadcrumbs;
        return $data;
    }

    /**
     * Render widgets provided by this module
     */
    public function renderWidget($widgetData) {
        $name = $widgetData['name'];

        // Handle breadcrumbs widget
        if ($name === 'seo:breadcrumbs') {
            $params = $widgetData['params'];
            $breadcrumbs = isset($params['breadcrumbs']) ? $params['breadcrumbs'] : array();

            if (empty($breadcrumbs)) {
                return $widgetData;
            }

            ob_start();
            ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbs as $item): ?>
                        <?php if ($item['url']): ?>
                            <li class="breadcrumb-item">
                                <a href="<?php echo e($item['url']); ?>">
                                    <?php echo e($item['title']); ?>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="breadcrumb-item active" aria-current="page">
                                <?php echo e($item['title']); ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php
            $widgetData['output'] = ob_get_clean();
        }

        return $widgetData;
    }
}
