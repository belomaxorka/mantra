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
                <h1 class="h3"><?php echo t('admin-pages.title'); ?></h1>
                <a href="<?php echo base_url('/admin/pages/new'); ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i><?php echo t('admin-pages.new'); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($pages)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                            <p class="mt-3"><?php echo t('admin-pages.no_pages'); ?></p>
                            <a href="<?php echo base_url('/admin/pages/new'); ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i><?php echo t('admin-pages.new'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo t('admin-pages.title_field'); ?></th>
                                        <th><?php echo t('admin-pages.slug_field'); ?></th>
                                        <th><?php echo t('admin-pages.status'); ?></th>
                                        <th><?php echo t('admin-pages.navigation'); ?></th>
                                        <th><?php echo t('admin-pages.updated'); ?></th>
                                        <th><?php echo t('admin-pages.actions'); ?></th>
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
                                                    <span class="badge bg-success"><?php echo t('admin-pages.published'); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo t('admin-pages.draft'); ?></span>
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
                                                    <?php echo $this->escape(date('Y-m-d H:i', strtotime($page['updated_at']))); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo base_url('/admin/pages/edit/' . $page['_id']); ?>"
                                                       class="btn btn-outline-primary" title="<?php echo t('admin-pages.edit'); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($page['status'] === 'published'): ?>
                                                        <a href="<?php echo base_url('/' . $page['slug']); ?>"
                                                           class="btn btn-outline-secondary" title="<?php echo t('admin-pages.view'); ?>" target="_blank">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button"
                                                            class="btn btn-outline-danger"
                                                            title="<?php echo t('admin-pages.delete'); ?>"
                                                            onclick="deletePage('<?php echo $this->escape($page['_id']); ?>', '<?php echo $this->escape($page['title']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo auth()->generateCsrfToken(); ?>">
</form>

<script>
function deletePage(id, title) {
    if (!confirm('<?php echo t('admin-pages.delete_confirm'); ?>')) {
        return;
    }

    var form = document.getElementById('deleteForm');
    form.action = '<?php echo base_url('/admin/pages/delete/'); ?>' + id;
    form.submit();
}
</script>
