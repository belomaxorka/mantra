<h1 class="section-title mb-4"><?php echo $this->escape($category['title']); ?></h1>

<?php if (!empty($category['description'])): ?>
    <p class="text-muted mb-4"><?php echo $this->escape($category['description']); ?></p>
<?php endif; ?>

<?php if (!empty($posts)): ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($posts as $post): ?>
            <article class="post-card">
                <h2 class="post-title">
                    <a href="<?php echo base_url('/post/' . $post['slug']); ?>">
                        <?php echo $this->escape($post['title']); ?>
                    </a>
                </h2>
                <div class="post-meta">
                    <span><?php echo clock()->formatDate($post['created_at']); ?></span>
                    <?php if (!empty($post['author'])): ?>
                        <span class="meta-dot">&middot;</span>
                        <span><?php echo $this->escape($post['author']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($post['excerpt'])): ?>
                    <p class="post-excerpt mb-0"><?php echo $this->escape($post['excerpt']); ?></p>
                <?php endif; ?>
                <a href="<?php echo base_url('/post/' . $post['slug']); ?>" class="post-read-more">Read more &rarr;</a>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (isset($paginator)): ?>
        <div class="mt-4">
            <?php echo partial('pagination', ['paginator' => $paginator]); ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="empty-state">
        <p>No posts in this category yet.</p>
    </div>
<?php endif; ?>
