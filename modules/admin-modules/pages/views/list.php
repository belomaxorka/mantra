<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3">Pages</h1>
                <a href="<?php echo base_url('/admin/pages/new'); ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>New Page
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
                            <p class="mt-3">No pages yet. Create your first page!</p>
                            <a href="<?php echo base_url('/admin/pages/new'); ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>New Page
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Slug</th>
                                        <th>Status</th>
                                        <th>Navigation</th>
                                        <th>Updated</th>
                                        <th>Actions</th>
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
                                                    <span class="badge bg-success">Published</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Draft</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($page['show_in_navigation'])): ?>
                                                    <i class="bi bi-check-circle-fill text-success" title="Shown in navigation"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-dash-circle text-muted" title="Hidden from navigation"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php
                                                    if (!empty($page['updated_at'])) {
                                                        echo $this->escape(date('Y-m-d H:i', strtotime($page['updated_at'])));
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo base_url('/admin/pages/edit/' . $page['_id']); ?>"
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="<?php echo base_url('/' . $page['slug']); ?>"
                                                       class="btn btn-outline-secondary" title="View" target="_blank">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button type="button"
                                                            class="btn btn-outline-danger"
                                                            title="Delete"
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
    if (!confirm('Are you sure you want to delete "' + title + '"?')) {
        return;
    }

    var form = document.getElementById('deleteForm');
    form.action = '<?php echo base_url('/admin/pages/delete/'); ?>' + id;
    form.submit();
}
</script>
