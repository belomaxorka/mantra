<div class="container-fluid">
    <div class="admin-page-header mb-4">
        <h1 class="h3"><?php echo t('admin-uploads.title'); ?></h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($canUpload)): ?>
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST"
                      action="<?php echo base_url('/admin/uploads'); ?>"
                      enctype="multipart/form-data"
                      id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

                    <div id="dropZone" class="border border-2 border-dashed rounded p-4 text-center"
                         style="cursor: pointer; transition: background-color 0.2s;">
                        <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
                        <p class="mb-2 text-muted"><?php echo t('admin-uploads.drop_or_click'); ?></p>
                        <input type="file"
                               name="file"
                               id="fileInput"
                               class="d-none"
                               accept="image/*,.pdf,.txt,.zip">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('fileInput').click()">
                            <i class="bi bi-folder2-open me-1"></i><?php echo t('admin-uploads.choose_file'); ?>
                        </button>
                        <div id="selectedFile" class="mt-2 text-muted small"></div>
                    </div>
                </form>
            </div>
        </div>

        <script>
        (function() {
            var dropZone = document.getElementById('dropZone');
            var fileInput = document.getElementById('fileInput');
            var form = document.getElementById('uploadForm');
            var selectedFile = document.getElementById('selectedFile');

            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                dropZone.style.backgroundColor = 'var(--bs-light)';
            });
            dropZone.addEventListener('dragleave', function() {
                dropZone.style.backgroundColor = '';
            });
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                dropZone.style.backgroundColor = '';
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    selectedFile.textContent = e.dataTransfer.files[0].name;
                    form.submit();
                }
            });
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    selectedFile.textContent = fileInput.files[0].name;
                    form.submit();
                }
            });
        })();
        </script>
    <?php endif; ?>

    <?php if (empty($files)): ?>
        <div class="card">
            <div class="card-body">
                <div class="admin-empty-state">
                    <i class="bi bi-paperclip"></i>
                    <p><?php echo t('admin-uploads.no_files'); ?></p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3">
            <?php foreach ($files as $file):
                $isImage = \Admin\UploadsPanel::isImage(isset($file['mime_type']) ? $file['mime_type'] : '');
                $fileUrl = $uploadsUrl . '/' . $file['path'];
                $displayName = !empty($file['original_name']) ? $file['original_name'] : $file['filename'];
            ?>
                <div class="col">
                    <div class="card h-100">
                        <a href="<?php echo base_url('/admin/uploads/edit/' . $file['_id']); ?>" class="text-decoration-none">
                            <?php if ($isImage): ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                     style="height: 150px; overflow: hidden;">
                                    <img src="<?php echo e($fileUrl); ?>"
                                         alt="<?php echo e($displayName); ?>"
                                         style="max-width: 100%; max-height: 150px; object-fit: contain;">
                                </div>
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                     style="height: 150px;">
                                    <?php
                                        $mime = isset($file['mime_type']) ? $file['mime_type'] : '';
                                        $icon = 'bi-file-earmark';
                                        if (strpos($mime, 'pdf') !== false) $icon = 'bi-file-earmark-pdf';
                                        elseif (strpos($mime, 'zip') !== false) $icon = 'bi-file-earmark-zip';
                                        elseif (strpos($mime, 'text') !== false) $icon = 'bi-file-earmark-text';
                                    ?>
                                    <i class="<?php echo $icon; ?> text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                        </a>
                        <div class="card-body p-2">
                            <small class="d-block text-truncate" title="<?php echo e($displayName); ?>">
                                <?php echo e($displayName); ?>
                            </small>
                            <small class="text-muted">
                                <?php echo \Admin\UploadsPanel::formatSize(isset($file['size']) ? $file['size'] : 0); ?>
                            </small>
                        </div>
                        <?php if ($canDelete): ?>
                            <div class="card-footer p-1 text-end bg-transparent border-0">
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        onclick="adminConfirmDelete('<?php echo e(base_url('/admin/uploads/delete/' . $file['_id'])); ?>', '<?php echo e(t('admin-uploads.delete_confirm')); ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
