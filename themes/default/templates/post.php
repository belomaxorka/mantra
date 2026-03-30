<article class="post">
    <?php
    // Display breadcrumbs if available (provided by SEO module)
    if (isset($breadcrumbs) && !empty($breadcrumbs)) {
        echo partial('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs));
    }
    ?>

    <h1><?php echo $this->escape($post['title']); ?></h1>

    <div class="meta text-muted mb-3">
        <?php if (isset($post['created_at'])): ?>
            Published: <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
        <?php endif; ?>
        <?php if (isset($post['author'])): ?>
            by <?php echo $this->escape($post['author']); ?>
        <?php endif; ?>
    </div>

    <div class="content">
        <?php echo $post['content']; ?>
    </div>
</article>
