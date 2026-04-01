<div class="container-fluid">
    <div class="admin-page-header mb-4">
        <h1 class="h3"><?php echo t('admin-users.title'); ?></h1>
        <a href="<?php echo base_url('/admin/users/new'); ?>" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i><?php echo t('admin-users.new'); ?>
        </a>
    </div>

    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="admin-empty-state">
                            <i class="bi bi-people"></i>
                            <p><?php echo t('admin-users.no_users'); ?></p>
                            <a href="<?php echo base_url('/admin/users/new'); ?>" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i><?php echo t('admin-users.new'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo t('admin-users.field.username'); ?></th>
                                        <th><?php echo t('admin-users.field.email'); ?></th>
                                        <th><?php echo t('admin-users.field.role'); ?></th>
                                        <th><?php echo t('admin-users.field.status'); ?></th>
                                        <th><?php echo t('admin-users.field.updated'); ?></th>
                                        <th class="text-end"><?php echo t('admin-users.field.actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u):
                                        $isSelf = (isset($currentUserId) && isset($u['_id']) && $u['_id'] === $currentUserId);
                                        $role = isset($u['role']) ? $u['role'] : 'editor';
                                        $status = isset($u['status']) ? $u['status'] : 'active';

                                        $roleBadge = 'secondary';
                                        if ($role === 'admin') $roleBadge = 'primary';
                                        elseif ($role === 'editor') $roleBadge = 'success';
                                        elseif ($role === 'author') $roleBadge = 'info';

                                        $statusBadge = 'secondary';
                                        if ($status === 'active') $statusBadge = 'success';
                                        elseif ($status === 'banned') $statusBadge = 'danger';
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($u['username']); ?></strong>
                                                <?php if ($isSelf): ?>
                                                    <span class="badge bg-primary ms-1"><?php echo t('admin-users.is_you'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo e(!empty($u['email']) ? $u['email'] : '-'); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $roleBadge; ?>">
                                                    <?php echo t('admin-users.role.' . $role); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $statusBadge; ?>">
                                                    <?php echo t('admin-users.status.' . $status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo e(!empty($u['updated_at']) ? date('Y-m-d H:i', strtotime($u['updated_at'])) : '-'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo base_url('/admin/users/edit/' . $u['_id']); ?>"
                                                       class="btn btn-outline-primary"
                                                       title="<?php echo t('admin.common.edit'); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if (!$isSelf): ?>
                                                        <button type="button"
                                                                class="btn btn-outline-danger"
                                                                onclick="adminConfirmDelete('<?php echo e(base_url('/admin/users/delete/' . $u['_id'])); ?>', '<?php echo e(t('admin-users.delete_confirm')); ?>')"
                                                                title="<?php echo t('admin.common.delete'); ?>">
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
