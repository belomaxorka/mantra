<?php
    $isImage = \Admin\UploadsPanel::isImage($file['mime_type'] ?? '');
    $fileUrl = $uploadsUrl . '/' . $file['path'];
    $displayName = !empty($file['original_name']) ? $file['original_name'] : $file['filename'];
?>
<div class="container-fluid">
    <div class="admin-page-header mb-4">
        <h1 class="h3"><?php echo t('admin-uploads.edit_file'); ?></h1>
        <a href="<?php echo base_url('/admin/uploads'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i><?php echo t('admin.common.back'); ?>
        </a>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if ($isImage): ?>
                        <img src="<?php echo e($fileUrl); ?>"
                             alt="<?php echo e($displayName); ?>"
                             class="img-fluid rounded"
                             style="max-height: 500px;">
                    <?php else: ?>
                        <?php
                            $mime = $file['mime_type'] ?? '';
                            $icon = 'bi-file-earmark';
                            if (str_contains($mime, 'pdf')  ) $icon = 'bi-file-earmark-pdf';
                            elseif (str_contains($mime, 'zip')  ) $icon = 'bi-file-earmark-zip';
                            elseif (str_contains($mime, 'text')  ) $icon = 'bi-file-earmark-text';
                        ?>
                        <i class="<?php echo $icon; ?> text-muted" style="font-size: 6rem;"></i>
                        <p class="text-muted mt-2"><?php echo e($file['mime_type']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><?php echo t('admin-uploads.file_url'); ?></div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="text"
                               class="form-control"
                               id="fileUrl"
                               value="<?php echo e($fileUrl); ?>"
                               readonly>
                        <button class="btn btn-outline-secondary"
                                type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('fileUrl').value)"
                                title="<?php echo t('admin-uploads.copy_url'); ?>">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <form method="POST" action="<?php echo base_url('/admin/uploads/edit/' . $file['_id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

                <div class="card mb-4">
                    <div class="card-header"><?php echo t('admin-uploads.metadata'); ?></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="original_name" class="form-label">
                                <?php echo t('admin-uploads.field.name'); ?>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="original_name"
                                   name="original_name"
                                   value="<?php echo e($displayName); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo t('admin-uploads.field.filename'); ?></label>
                            <input type="text" class="form-control" value="<?php echo e($file['filename']); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo t('admin-uploads.field.type'); ?></label>
                            <input type="text" class="form-control" value="<?php echo e($file['mime_type']); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo t('admin-uploads.field.size'); ?></label>
                            <input type="text"
                                   class="form-control"
                                   value="<?php echo \Admin\UploadsPanel::formatSize($file['size']); ?>"
                                   readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo t('admin-uploads.field.uploaded_by'); ?></label>
                            <input type="text" class="form-control" value="<?php echo e($file['author']); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo t('admin-uploads.field.date'); ?></label>
                            <input type="text"
                                   class="form-control"
                                   value="<?php echo e(!empty($file['created_at']) ? date('Y-m-d H:i', strtotime($file['created_at'])) : '-'); ?>"
                                   readonly>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i><?php echo t('admin-uploads.save'); ?>
                    </button>

                    <a href="<?php echo e($fileUrl); ?>"
                       class="btn btn-outline-secondary"
                       target="_blank">
                        <i class="bi bi-box-arrow-up-right me-2"></i><?php echo t('admin-uploads.open_file'); ?>
                    </a>

                    <?php if ($canDelete): ?>
                        <button type="button"
                                class="btn btn-outline-danger"
                                onclick="adminConfirmDelete('<?php echo e(base_url('/admin/uploads/delete/' . $file['_id'])); ?>', '<?php echo e(t('admin-uploads.delete_confirm')); ?>')">
                            <i class="bi bi-trash me-2"></i><?php echo t('admin-uploads.delete'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
