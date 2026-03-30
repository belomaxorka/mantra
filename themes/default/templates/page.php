<article class="page">
    <?php
    // Display breadcrumbs if available (provided by SEO module)
    if (isset($breadcrumbs) && !empty($breadcrumbs)) {
        echo partial('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs));
    }
    ?>

    <h1><?php echo $this->escape($page['title']); ?></h1>

    <div class="meta text-muted mb-3">
        <?php if (isset($page['created_at'])): ?>
            Published: <?php echo date('F j, Y', strtotime($page['created_at'])); ?>
        <?php endif; ?>
    </div>

    <div class="content">
        <?php echo $page['content']; ?>
    </div>
</article>
