<article class="page">
    <?php
    // Display breadcrumbs if available (provided by SEO module)
    if (isset($breadcrumbs) && !empty($breadcrumbs)) {
        echo partial('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs));
    }
    ?>

    <h1 class="mb-4"><?php echo $this->escape($page['title']); ?></h1>

    <div class="content">
        <?php echo $page['content']; ?>
    </div>
</article>
