<article class="page">
    <h1><?php echo $this->escape($page['title']); ?></h1>

    <div class="meta text-muted mb-3">
        <?php if (isset($page['created_at'])): ?>
            Published: <?php echo date('F j, Y', strtotime($page['created_at'])); ?>
        <?php endif; ?>
    </div>

    <div class="content">
        <?php echo isset($page['content']) ? $page['content'] : ''; ?>
    </div>
</article>
