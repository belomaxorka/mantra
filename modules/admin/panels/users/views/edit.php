<div class="container-fluid">
    <div class="admin-page-header mb-4">
        <h1 class="h3"><?php echo $isNew ? t('admin-users.new') : t('admin-users.edit_user'); ?></h1>
        <a href="<?php echo base_url('/admin/users'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i><?php echo t('admin.common.back'); ?>
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="userForm">
        <input type="hidden" name="csrf_token" value="<?php echo $this->escape($csrf_token); ?>">

        <div class="row">
            <div class="col-xl-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <?php echo t('admin-users.field.username'); ?> <span class="text-danger">*</span>
                            </label>
                            <?php if ($isNew): ?>
                                <input type="text"
                                       class="form-control"
                                       id="username"
                                       name="username"
                                       value="<?php echo $this->escape($user['username']); ?>"
                                       required
                                       pattern="[a-zA-Z0-9_-]+"
                                       minlength="3"
                                       maxlength="50">
                            <?php else: ?>
                                <input type="text"
                                       class="form-control"
                                       value="<?php echo $this->escape($user['username']); ?>"
                                       disabled>
                                <div class="form-text"><?php echo t('admin-users.username_help'); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <?php echo t('admin-users.field.email'); ?>
                            </label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   value="<?php echo $this->escape(isset($user['email']) ? $user['email'] : ''); ?>"
                                   maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <?php echo t('admin-users.field.password'); ?>
                                <?php if ($isNew): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="password"
                                   class="form-control"
                                   id="password"
                                   name="password"
                                   <?php echo $isNew ? 'required minlength="6"' : ''; ?>>
                            <div class="form-text">
                                <?php echo $isNew ? t('admin-users.password_required') : t('admin-users.password_help'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <?php echo t('admin-users.field.role'); ?>
                    </div>
                    <div class="card-body">
                        <div class="btn-group w-100" role="group">
                            <?php
                                $roles = array('admin', 'editor', 'viewer');
                                $roleColors = array('admin' => 'primary', 'editor' => 'success', 'viewer' => 'secondary');
                                $currentRole = isset($user['role']) ? $user['role'] : 'editor';
                            ?>
                            <?php foreach ($roles as $r): ?>
                                <input type="radio"
                                       class="btn-check"
                                       name="role"
                                       id="role_<?php echo $r; ?>"
                                       value="<?php echo $r; ?>"
                                       <?php echo ($currentRole === $r) ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-<?php echo $roleColors[$r]; ?>" for="role_<?php echo $r; ?>">
                                    <?php echo t('admin-users.role.' . $r); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <?php echo t('admin-users.field.status'); ?>
                    </div>
                    <div class="card-body">
                        <div class="btn-group w-100" role="group">
                            <?php
                                $statuses = array('active', 'inactive', 'banned');
                                $statusColors = array('active' => 'success', 'inactive' => 'secondary', 'banned' => 'danger');
                                $currentStatus = isset($user['status']) ? $user['status'] : 'active';
                            ?>
                            <?php foreach ($statuses as $s): ?>
                                <input type="radio"
                                       class="btn-check"
                                       name="status"
                                       id="status_<?php echo $s; ?>"
                                       value="<?php echo $s; ?>"
                                       <?php echo ($currentStatus === $s) ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-<?php echo $statusColors[$s]; ?>" for="status_<?php echo $s; ?>">
                                    <?php echo t('admin-users.status.' . $s); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php echo app()->hooks()->fire('admin.users.edit.sidebar', '', $user); ?>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i><?php echo $isNew ? t('admin-users.create') : t('admin-users.update'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
