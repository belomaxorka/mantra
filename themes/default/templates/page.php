<article class="page">
    <?php
    // Display breadcrumbs if available (provided by SEO module)
    if (isset($breadcrumbs) && !empty($breadcrumbs)) {
        echo widget('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs));
    }
    ?>

    <h1><?php echo $this->escape($page['title']); ?></h1>

    <div class="content">
        <?php echo $page['content']; ?>
    </div>
</article>
