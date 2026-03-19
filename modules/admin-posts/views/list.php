<style>
    @media (max-width: 575.98px) {
        .list-header-actions {
            flex-direction: column;
            align-items: stretch !important;
            gap: 0.75rem;
        }
        .list-header-actions h1 {
            font-size: 1.5rem;
        }
        .list-header-actions .btn {
            width: 100%;
        }
        .table-responsive table {
            font-size: 0.875rem;
        }
        .table-responsive .btn-group {
            flex-direction: column;
        }
        .table-responsive .btn-group .btn {
            border-radius: 0.25rem !important;
            margin-bottom: 0.25rem;
        }
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center list-header-actions">
                <h1 class="h3"><?php echo t('admin-posts.title'); ?></h1>
                <a href="<?php echo base_url('/admin/posts/new'); ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i><?php echo t('admin-posts.new_post'); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <?php if (empty($posts)): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                            <p class="mt-3"><?php echo t('admin-posts.no_posts'); ?></p>
                            <a href="<?php echo base_url('/admin/posts/new'); ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i><?php echo t('admin-posts.new_post'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?php echo t('admin-posts.field.title'); ?></th>
                        <th><?php echo t('admin-posts.field.author'); ?></th>
                        <th><?php echo t('admin-posts.field.category'); ?></th>
                        <th><?php echo t('admin-posts.field.status'); ?></th>
                        <th><?php echo t('admin-posts.field.updated'); ?></th>
                        <th class="text-end"><?php echo t('admin-posts.field.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td>
                                <strong><?php echo e($post['title']); ?></strong>
                            </td>
                            <td>
                                <?php echo e($post['author']); ?>
                            </td>
                            <td>
                                <?php echo e($post['category'] !== '' ? $post['category'] : '-'); ?>
                            </td>
                            <td>
                                <?php if ($post['status'] === 'published'): ?>
                                    <span class="badge bg-success"><?php echo t('admin-posts.status_published'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo t('admin-posts.status_draft'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo e(format_date($post['updated_at'], 'Y-m-d H:i')); ?>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo base_url('/admin/posts/edit/' . $post['_id']); ?>"
                                       class="btn btn-outline-primary"
                                       title="<?php echo t('admin-posts.edit'); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($post['status'] === 'published'): ?>
                                        <a href="<?php echo base_url('/post/' . $post['slug']); ?>"
                                           class="btn btn-outline-secondary"
                                           title="<?php echo t('admin-posts.view'); ?>"
                                           target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button"
                                            class="btn btn-outline-danger"
                                            onclick="deletePost('<?php echo e($post['_id']); ?>')"
                                            title="<?php echo t('admin-posts.delete'); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo auth()->generateCsrfToken(); ?>">
</form>

<script>
function deletePost(id) {
    if (!confirm('<?php echo t('admin-posts.delete_confirm'); ?>')) {
        return;
    }

    const form = document.getElementById('deleteForm');
    form.action = '<?php echo base_url('/admin/posts/delete/'); ?>' + id;
    form.submit();
}
</script>
