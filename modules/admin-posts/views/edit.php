<div class="mb-4">
    <h2>
        <?php echo $isNew ? t('admin-posts.new_post') : t('admin-posts.edit_post'); ?>
    </h2>
</div>

<form method="POST" action="<?php echo $isNew ? base_url('/admin/posts/new') : base_url('/admin/posts/edit/' . $post['_id']); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">
                            <?php echo t('admin-posts.field.title'); ?> <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="title"
                               name="title"
                               value="<?php echo e(isset($post['title']) ? $post['title'] : ''); ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="slug" class="form-label">
                            <?php echo t('admin-posts.field.slug'); ?>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="slug"
                               name="slug"
                               value="<?php echo e(isset($post['slug']) ? $post['slug'] : ''); ?>">
                        <div class="form-text">
                            <?php echo t('admin-posts.slug_help'); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="excerpt" class="form-label">
                            <?php echo t('admin-posts.field.excerpt'); ?>
                        </label>
                        <textarea class="form-control"
                                  id="excerpt"
                                  name="excerpt"
                                  rows="3"><?php echo e(isset($post['excerpt']) ? $post['excerpt'] : ''); ?></textarea>
                        <div class="form-text">
                            <?php echo t('admin-posts.excerpt_help'); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">
                            <?php echo t('admin-posts.field.content'); ?>
                        </label>
                        <textarea class="form-control"
                                  id="content"
                                  name="content"
                                  rows="15"><?php echo e(isset($post['content']) ? $post['content'] : ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <?php echo t('admin-posts.publish'); ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">
                            <?php echo t('admin-posts.field.status'); ?>
                        </label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?php echo (isset($post['status']) && $post['status'] === 'draft') ? 'selected' : ''; ?>>
                                <?php echo t('admin-posts.status_draft'); ?>
                            </option>
                            <option value="published" <?php echo (isset($post['status']) && $post['status'] === 'published') ? 'selected' : ''; ?>>
                                <?php echo t('admin-posts.status_published'); ?>
                            </option>
                        </select>
                    </div>

                    <?php if (!$isNew && isset($post['author'])): ?>
                        <div class="mb-3">
                            <label class="form-label">
                                <?php echo t('admin-posts.field.author'); ?>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   value="<?php echo e($post['author']); ?>"
                                   readonly>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i>
                            <?php echo $isNew ? t('admin-posts.create') : t('admin-posts.update'); ?>
                        </button>
                        <a href="<?php echo base_url('/admin/posts'); ?>" class="btn btn-outline-secondary">
                            <?php echo t('admin-posts.cancel'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <?php echo t('admin-posts.metadata'); ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="category" class="form-label">
                            <?php echo t('admin-posts.field.category'); ?>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="category"
                               name="category"
                               value="<?php echo e(isset($post['category']) ? $post['category'] : ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
