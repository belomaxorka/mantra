<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?php echo t('admin.posts.list_title'); ?></h2>
    <a href="<?php echo base_url('/admin/posts/new'); ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> <?php echo t('admin.posts.new_post'); ?>
    </a>
</div>

<?php if (empty($posts)): ?>
    <div class="alert alert-info">
        <?php echo t('admin.posts.no_posts'); ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th><?php echo t('admin.posts.field.title'); ?></th>
                        <th><?php echo t('admin.posts.field.author'); ?></th>
                        <th><?php echo t('admin.posts.field.category'); ?></th>
                        <th><?php echo t('admin.posts.field.status'); ?></th>
                        <th><?php echo t('admin.posts.field.updated'); ?></th>
                        <th class="text-end"><?php echo t('admin.posts.field.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td>
                                <strong><?php echo e(isset($post['title']) ? $post['title'] : 'Untitled'); ?></strong>
                            </td>
                            <td>
                                <?php echo e(isset($post['author']) ? $post['author'] : 'Unknown'); ?>
                            </td>
                            <td>
                                <?php
                                $category = isset($post['category']) && $post['category'] !== '' ? $post['category'] : '-';
                                echo e($category);
                                ?>
                            </td>
                            <td>
                                <?php
                                $status = isset($post['status']) ? $post['status'] : 'draft';
                                $badgeClass = $status === 'published' ? 'bg-success' : 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo e(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if (isset($post['updated_at'])) {
                                    echo e(date('Y-m-d H:i', strtotime($post['updated_at'])));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo base_url('/admin/posts/edit/' . $post['_id']); ?>"
                                       class="btn btn-outline-primary"
                                       title="<?php echo t('admin.posts.edit'); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-outline-danger"
                                            onclick="deletePost('<?php echo e($post['_id']); ?>')"
                                            title="<?php echo t('admin.posts.delete'); ?>">
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
<?php endif; ?>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo auth()->generateCsrfToken(); ?>">
</form>

<script>
function deletePost(id) {
    if (!confirm('<?php echo t('admin.posts.delete_confirm'); ?>')) {
        return;
    }

    const form = document.getElementById('deleteForm');
    form.action = '<?php echo base_url('/admin/posts/delete/'); ?>' + id;
    form.submit();
}
</script>
