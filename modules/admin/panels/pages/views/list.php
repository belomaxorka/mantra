<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="admin-page-header">
                <h1 class="h3"><?php echo t('admin-pages.title'); ?></h1>
                <?php if (!empty($canCreate)): ?>
                    <a href="<?php echo base_url('/admin/pages/new'); ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i><?php echo t('admin-pages.new'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($pages)): ?>
                        <div class="admin-empty-state">
                            <i class="bi bi-file-earmark-text"></i>
                            <p><?php echo t('admin-pages.no_pages'); ?></p>
                            <?php if (!empty($canCreate)): ?>
                                <a href="<?php echo base_url('/admin/pages/new'); ?>" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i><?php echo t('admin-pages.new'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo t('admin-pages.field.title'); ?></th>
                                        <th><?php echo t('admin-pages.field.slug'); ?></th>
                                        <th><?php echo t('admin-pages.field.status'); ?></th>
                                        <th><?php echo t('admin-pages.field.navigation'); ?></th>
                                        <th><?php echo t('admin-pages.field.updated'); ?></th>
                                        <th><?php echo t('admin-pages.field.actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pages as $page): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $this->escape($page['title']); ?></strong>
                                            </td>
                                            <td>
                                                <code><?php echo $this->escape($page['slug']); ?></code>
                                            </td>
                                            <td>
                                                <?php if ($page['status'] === 'published'): ?>
                                                    <span class="badge bg-success"><?php echo t('admin-pages.status.published'); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo t('admin-pages.status.draft'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($page['show_in_navigation'])): ?>
                                                    <i class="bi bi-check-circle-fill text-success" title="<?php echo t('admin-pages.shown_in_nav'); ?>"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-dash-circle text-muted" title="<?php echo t('admin-pages.hidden_from_nav'); ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo e(clock()->formatDatetime($page['updated_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if (!empty($canEdit)): ?>
                                                        <a href="<?php echo base_url('/admin/pages/edit/' . $page['_id']); ?>"
                                                           class="btn btn-outline-primary" title="<?php echo t('admin-pages.edit'); ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($page['status'] === 'published'): ?>
                                                        <a href="<?php echo base_url('/' . $page['slug']); ?>"
                                                           class="btn btn-outline-secondary" title="<?php echo t('admin-pages.view'); ?>" target="_blank">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($canDelete)): ?>
                                                        <button type="button"
                                                                class="btn btn-outline-danger"
                                                                title="<?php echo t('admin-pages.delete'); ?>"
                                                                onclick="adminConfirmDelete('<?php echo e(base_url('/admin/pages/delete/' . $page['_id'])); ?>', '<?php echo e(t('admin-pages.delete_confirm')); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

