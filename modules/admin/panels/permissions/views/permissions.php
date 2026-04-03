<div class="container-fluid">
    <div class="admin-page-header mb-4">
        <h1 class="h3"><?php echo t('admin-permissions.title'); ?></h1>
    </div>

    <?php if (!empty($notice)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo e($notice); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body py-2">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i><?php echo t('admin-permissions.admin_note'); ?>
            </small>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="min-width: 240px;"><?php echo t('admin-permissions.title'); ?></th>
                                <?php foreach ($roles as $role): ?>
                                    <th class="text-center" style="min-width: 120px;">
                                        <?php echo t('admin-permissions.role.' . $role); ?>
                                        <?php if (isset($roleData[$role]['hasOverride']) && $roleData[$role]['hasOverride']): ?>
                                            <br><span class="badge bg-warning text-dark" style="font-size: 0.65em;"><?php echo t('admin-permissions.customized'); ?></span>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grouped as $group => $permissions): ?>
                                <tr class="table-secondary">
                                    <td colspan="<?php echo 1 + count($roles); ?>" class="ps-3">
                                        <strong><?php echo e($group); ?></strong>
                                    </td>
                                </tr>
                                <?php foreach ($permissions as $permission): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <code class="small"><?php echo e($permission); ?></code>
                                            <br>
                                            <small class="text-muted"><?php echo e($labels[$permission] ?? ''); ?></small>
                                        </td>
                                        <?php foreach ($roles as $role): ?>
                                            <?php
                                                $rolePerms = $roleData[$role]['permissions'] ?? [];
                                                $checked = in_array($permission, $rolePerms, true);
                                            ?>
                                            <td class="text-center align-middle">
                                                <input type="checkbox"
                                                       class="form-check-input"
                                                       name="role_<?php echo e($role); ?>[]"
                                                       value="<?php echo e($permission); ?>"
                                                       <?php echo $checked ? 'checked' : ''; ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <?php foreach ($roles as $role): ?>
                    <?php if (isset($roleData[$role]['hasOverride']) && $roleData[$role]['hasOverride']): ?>
                        <button type="submit"
                                name="reset_role"
                                value="<?php echo e($role); ?>"
                                class="btn btn-outline-secondary btn-sm me-1"
                                onclick="return confirm('<?php echo e(t('admin-permissions.reset_confirm')); ?>')">
                            <i class="bi bi-arrow-counterclockwise me-1"></i><?php echo t('admin-permissions.reset_to_defaults'); ?>: <?php echo t('admin-permissions.role.' . $role); ?>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-2"></i><?php echo t('admin-permissions.save'); ?>
            </button>
        </div>
    </form>
</div>
