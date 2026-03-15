<div class="home">
    <h1>Welcome to Mantra CMS</h1>
    
    <?php if (!empty($posts)): ?>
        <div class="posts">
            <?php foreach ($posts as $post): ?>
                <article class="post">
                    <h2>
                        <a href="<?php echo base_url('/post/' . $post['slug']); ?>">
                            <?php echo $this->escape($post['title']); ?>
                        </a>
                    </h2>
                    <div class="meta">
                        <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                    </div>
                    <div class="excerpt">
                        <?php echo isset($post['excerpt']) ? $this->escape($post['excerpt']) : ''; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No posts yet. <a href="<?php echo base_url('/admin'); ?>">Create your first post</a></p>
    <?php endif; ?>
</div>
