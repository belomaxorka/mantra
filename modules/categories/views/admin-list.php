<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="admin-page-header">
                <h1 class="h3"><?php echo t('categories.title'); ?></h1>
                <a href="<?php echo base_url('/admin/categories/new'); ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i><?php echo t('categories.new_category'); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <?php if (empty($categories)): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="admin-empty-state">
                            <i class="bi bi-tag"></i>
                            <p><?php echo t('categories.no_categories'); ?></p>
                            <a href="<?php echo base_url('/admin/categories/new'); ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i><?php echo t('categories.new_category'); ?>
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
                                        <th><?php echo t('categories.field.title'); ?></th>
                                        <th><?php echo t('categories.field.slug'); ?></th>
                                        <th><?php echo t('categories.field.order'); ?></th>
                                        <th><?php echo t('categories.field.posts'); ?></th>
                                        <th class="text-end"><?php echo t('categories.field.actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td><strong><?php echo e($cat['title']); ?></strong></td>
                                            <td><code><?php echo e($cat['slug']); ?></code></td>
                                            <td><?php echo (int)$cat['order']; ?></td>
                                            <td><?php echo isset($counts[$cat['slug']]) ? (int)$counts[$cat['slug']] : 0; ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo base_url('/admin/categories/edit/' . $cat['_id']); ?>"
                                                       class="btn btn-outline-primary"
                                                       title="<?php echo t('categories.edit_category'); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="<?php echo base_url('/category/' . $cat['slug']); ?>"
                                                       class="btn btn-outline-secondary"
                                                       target="_blank"
                                                       title="<?php echo t('categories.category'); ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button type="button"
                                                            class="btn btn-outline-danger"
                                                            onclick="adminConfirmDelete('<?php echo e(base_url('/admin/categories/delete/' . $cat['_id'])); ?>', '<?php echo e(t('categories.delete_confirm')); ?>')"
                                                            title="<?php echo t('categories.delete_confirm'); ?>">
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
