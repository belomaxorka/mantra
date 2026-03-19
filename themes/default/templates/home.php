<div class="home">
    <h1>Welcome to <?php echo $this->escape(config('site.name', MANTRA_PROJECT_INFO['name'])); ?></h1>

    <?php if (!empty($posts)): ?>
        <div class="posts">
            <?php foreach ($posts as $post): ?>
                <article class="post mb-4">
                    <h2>
                        <a href="<?php echo base_url('/post/' . $post['slug']); ?>">
                            <?php echo $this->escape($post['title']); ?>
                        </a>
                    </h2>
                    <div class="meta text-muted mb-2">
                        <?php echo time_ago($post['created_at'], true); ?>
                    </div>
                    <?php if (isset($post['excerpt'])): ?>
                        <div class="excerpt">
                            <?php echo $this->escape($post['excerpt']); ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <p class="mb-0">No posts yet. <a href="<?php echo base_url('/admin'); ?>" class="alert-link">Create your first post</a></p>
        </div>
    <?php endif; ?>
</div>
