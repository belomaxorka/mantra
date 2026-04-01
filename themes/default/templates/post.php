<article>
    <?php
    if (isset($breadcrumbs) && !empty($breadcrumbs)) {
        echo partial('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs));
    }
    ?>

    <header class="article-header">
        <h1><?php echo $this->escape($post['title']); ?></h1>
        <div class="article-meta">
            <span><?php echo clock()->formatDate($post['created_at']); ?></span>
            <?php if (!empty($post['author'])): ?>
                <span>&middot;</span>
                <span><?php echo $this->escape($post['author']); ?></span>
            <?php endif; ?>
            <?php if (!empty($post['category'])): ?>
                <span>&middot;</span>
                <span><?php echo $this->escape($post['category']); ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div class="article-content">
        <?php echo $post['content']; ?>
    </div>
</article>
