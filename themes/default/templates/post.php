<article class="post">
    <?php
    // Display breadcrumbs if available (provided by SEO module)
    if (isset($breadcrumbs) && !empty($breadcrumbs)) {
        echo widget('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs));
    }
    ?>

    <h1><?php echo $this->escape($post['title']); ?></h1>

    <div class="meta text-muted mb-3">
        <?php if (isset($post['created_at'])): ?>
            <?php echo time_ago($post['created_at'], true); ?>
        <?php endif; ?>
        <?php if (isset($post['author'])): ?>
            — <?php echo $this->escape($post['author']); ?>
        <?php endif; ?>
    </div>

    <div class="content">
        <?php echo $post['content']; ?>
    </div>
</article>
