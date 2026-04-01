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
            <?php if (!empty($readingTime)): ?>
                <span>&middot;</span>
                <span><?php echo $readingTime; ?> min read</span>
            <?php endif; ?>
            <?php if (!empty($post['category'])): ?>
                <span class="post-category-badge"><?php echo $this->escape($post['category']); ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div class="article-content">
        <?php echo $post['content']; ?>
    </div>
</article>

<?php if (!empty($prevPost) || !empty($nextPost)): ?>
    <nav class="post-nav">
        <div class="post-nav-item post-nav-prev">
            <?php if (!empty($prevPost)): ?>
                <a href="<?php echo base_url('/post/' . $prevPost['slug']); ?>">
                    <span class="post-nav-label">&larr; Previous</span>
                    <span class="post-nav-title"><?php echo $this->escape($prevPost['title']); ?></span>
                </a>
            <?php endif; ?>
        </div>
        <div class="post-nav-item post-nav-next">
            <?php if (!empty($nextPost)): ?>
                <a href="<?php echo base_url('/post/' . $nextPost['slug']); ?>">
                    <span class="post-nav-label">Next &rarr;</span>
                    <span class="post-nav-title"><?php echo $this->escape($nextPost['title']); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </nav>
<?php endif; ?>
