<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="admin-page-header">
                <h1 class="h3"><?php echo $isNew ? t('admin-pages.new') : t('admin-pages.edit_page'); ?></h1>
                <a href="<?php echo base_url('/admin/pages'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i><?php echo t('admin.common.back'); ?>
                </a>
            </div>
        </div>
    </div>

    <form method="POST" id="pageForm">
        <input type="hidden" name="csrf_token" value="<?php echo $this->escape($csrf_token); ?>">

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label"><?php echo t('admin-pages.field.title'); ?> <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="title"
                                   name="title"
                                   value="<?php echo $this->escape($page['title']); ?>"
                                   required
                                   placeholder="<?php echo t('admin-pages.title_placeholder'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label"><?php echo t('admin-pages.field.slug'); ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="slug"
                                   name="slug"
                                   value="<?php echo $this->escape($page['slug']); ?>"
                                   placeholder="<?php echo t('admin-pages.slug_placeholder'); ?>">
                            <div class="form-text"><?php echo t('admin-pages.slug_help'); ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label"><?php echo t('admin-pages.field.content'); ?></label>
                            <textarea id="content" name="content" class="form-control"><?php echo $this->escape($page['content']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <?php echo t('admin-pages.publish'); ?>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('admin-pages.field.status'); ?></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio"
                                       class="btn-check"
                                       name="status"
                                       id="status_draft"
                                       value="draft"
                                       <?php echo ($page['status'] === 'draft') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-secondary" for="status_draft">
                                    <i class="bi bi-file-earmark me-1"></i><?php echo t('admin-pages.status.draft'); ?>
                                </label>

                                <input type="radio"
                                       class="btn-check"
                                       name="status"
                                       id="status_published"
                                       value="published"
                                       <?php echo ($page['status'] === 'published') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-success" for="status_published">
                                    <i class="bi bi-check-circle me-1"></i><?php echo t('admin-pages.status.published'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i><?php echo $isNew ? t('admin-pages.create') : t('admin-pages.update'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <?php echo t('admin-pages.featured_image'); ?>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="image" id="image" value="<?php echo $this->escape($page['image']); ?>">

                        <div id="imagePreview" class="mb-3 text-center <?php echo empty($page['image']) ? 'd-none' : ''; ?>">
                            <img src="<?php echo $this->escape($page['image']); ?>"
                                 id="imagePreviewImg"
                                 alt="<?php echo t('admin-pages.image_preview'); ?>"
                                 class="img-fluid rounded"
                                 style="max-height: 200px;"
                                 onerror="this.style.display='none'">
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeImage()">
                                    <i class="bi bi-trash me-1"></i><?php echo t('admin-pages.image_remove'); ?>
                                </button>
                            </div>
                        </div>

                        <div id="imageUpload" class="<?php echo !empty($page['image']) ? 'd-none' : ''; ?>">
                            <div id="imageDropZone"
                                 class="border border-2 border-dashed rounded p-3 text-center"
                                 style="cursor: pointer;">
                                <i class="bi bi-image text-muted fs-3"></i>
                                <p class="small text-muted mb-1"><?php echo t('admin-pages.image_drop'); ?></p>
                                <input type="file" id="imageFile" class="d-none" accept="image/*">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('imageFile').click()">
                                    <i class="bi bi-upload me-1"></i><?php echo t('admin-pages.image_choose'); ?>
                                </button>
                            </div>
                            <div id="imageUploading" class="text-center py-3 d-none">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2 text-muted"><?php echo t('admin-pages.image_uploading'); ?></span>
                            </div>
                            <div id="imageError" class="alert alert-danger mt-2 d-none small"></div>
                        </div>
                    </div>
                </div>

                <script>
                (function() {
                    var uploadUrl = '<?php echo base_url("/admin/uploads/api/upload"); ?>';
                    var elDropZone  = document.getElementById('imageDropZone');
                    var elFileInput = document.getElementById('imageFile');
                    var elUploading = document.getElementById('imageUploading');
                    var elError     = document.getElementById('imageError');
                    var elPreview   = document.getElementById('imagePreview');
                    var elPreviewImg = document.getElementById('imagePreviewImg');
                    var elUpload    = document.getElementById('imageUpload');
                    var elInput     = document.getElementById('image');

                    if (!elDropZone || !elFileInput) return;

                    function uploadImage(file) {
                        var form = new FormData();
                        form.append('file', file);

                        elDropZone.classList.add('d-none');
                        elUploading.classList.remove('d-none');
                        elError.classList.add('d-none');

                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', uploadUrl, true);
                        xhr.withCredentials = true;
                        xhr.onload = function() {
                            elUploading.classList.add('d-none');
                            if (xhr.status === 200) {
                                try {
                                    var data = JSON.parse(xhr.responseText);
                                    if (data.url) { setImage(data.url); return; }
                                } catch(e) {}
                            }
                            var errMsg = 'Upload failed';
                            try { errMsg = JSON.parse(xhr.responseText).error.message; } catch(e) {}
                            elError.textContent = errMsg;
                            elError.classList.remove('d-none');
                            elDropZone.classList.remove('d-none');
                        };
                        xhr.onerror = function() {
                            elUploading.classList.add('d-none');
                            elDropZone.classList.remove('d-none');
                            elError.textContent = 'Network error';
                            elError.classList.remove('d-none');
                        };
                        xhr.send(form);
                    }

                    function setImage(url) {
                        elInput.value = url;
                        elPreviewImg.src = url;
                        elPreviewImg.style.display = '';
                        elPreview.classList.remove('d-none');
                        elUpload.classList.add('d-none');
                    }

                    window.removeImage = function() {
                        elInput.value = '';
                        elPreview.classList.add('d-none');
                        elUpload.classList.remove('d-none');
                        elDropZone.classList.remove('d-none');
                    };

                    elDropZone.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        elDropZone.style.backgroundColor = 'var(--bs-light)';
                    });
                    elDropZone.addEventListener('dragleave', function() {
                        elDropZone.style.backgroundColor = '';
                    });
                    elDropZone.addEventListener('drop', function(e) {
                        e.preventDefault();
                        elDropZone.style.backgroundColor = '';
                        if (e.dataTransfer.files.length > 0 && e.dataTransfer.files[0].type.indexOf('image/') === 0) {
                            uploadImage(e.dataTransfer.files[0]);
                        }
                    });
                    elFileInput.addEventListener('change', function() {
                        if (elFileInput.files.length > 0) {
                            uploadImage(elFileInput.files[0]);
                        }
                    });
                })();
                </script>

                <div class="card">
                    <div class="card-header">
                        <?php echo t('admin-pages.field.navigation'); ?>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="show_in_navigation"
                                       name="show_in_navigation"
                                       value="1"
                                       <?php echo !empty($page['show_in_navigation']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_in_navigation">
                                    <?php echo t('admin-pages.show_in_nav'); ?>
                                </label>
                            </div>
                            <div class="form-text"><?php echo t('admin-pages.show_in_nav_help'); ?></div>
                        </div>

                        <div class="mb-0">
                            <label for="navigation_order" class="form-label"><?php echo t('admin-pages.nav_order'); ?></label>
                            <input type="number"
                                   class="form-control"
                                   id="navigation_order"
                                   name="navigation_order"
                                   value="<?php echo isset($page['navigation_order']) ? (int)$page['navigation_order'] : 50; ?>"
                                   min="0"
                                   step="1">
                            <div class="form-text"><?php echo t('admin-pages.nav_order_help'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/classic/ckeditor.js"></script>
<script>
(function() {
    var UPLOAD_URL = '<?php echo base_url("/admin/uploads/api/upload"); ?>';

    function MantraUploadAdapter(loader) { this.loader = loader; }
    MantraUploadAdapter.prototype.upload = function() {
        var loader = this;
        return this.loader.file.then(function(file) {
            return new Promise(function(resolve, reject) {
                var form = new FormData();
                form.append('file', file);
                var xhr = new XMLHttpRequest();
                loader._xhr = xhr;
                xhr.open('POST', UPLOAD_URL, true);
                xhr.withCredentials = true;
                xhr.onload = function() {
                    if (xhr.status < 200 || xhr.status >= 300) {
                        var msg = 'Upload failed';
                        try { msg = JSON.parse(xhr.responseText).error.message; } catch(e) {}
                        return reject(msg);
                    }
                    var data = JSON.parse(xhr.responseText);
                    if (!data.url) return reject('No URL in response');
                    resolve({ default: data.url });
                };
                xhr.onerror = function() { reject('Network error'); };
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) loader.loader.uploadTotal = e.total, loader.loader.uploaded = e.loaded;
                };
                xhr.send(form);
            });
        });
    };
    MantraUploadAdapter.prototype.abort = function() { if (this._xhr) this._xhr.abort(); };

    function MantraUploadPlugin(editor) {
        editor.plugins.get('FileRepository').createUploadAdapter = function(loader) {
            return new MantraUploadAdapter(loader);
        };
    }

    ClassicEditor
        .create(document.querySelector('#content'), {
            extraPlugins: [MantraUploadPlugin],
            toolbar: {
                items: [
                    'undo', 'redo',
                    '|', 'heading',
                    '|', 'bold', 'italic', 'underline', 'strikethrough',
                    '|', 'link', 'insertImage', 'insertTable', 'mediaEmbed',
                    '|', 'bulletedList', 'numberedList', 'outdent', 'indent',
                    '|', 'alignment',
                    '|', 'blockQuote', 'codeBlock',
                    '|', 'sourceEditing'
                ],
                shouldNotGroupWhenFull: true
            },
            heading: {
                options: [
                    { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                    { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                    { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                    { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                    { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
                ]
            },
            link: {
                defaultProtocol: 'https://',
                decorators: {
                    openInNewTab: {
                        mode: 'manual',
                        label: 'Open in a new tab',
                        defaultValue: true,
                        attributes: {
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }
                    }
                }
            },
            image: {
                toolbar: [
                    'imageTextAlternative', 'toggleImageCaption', 'imageStyle:inline',
                    'imageStyle:block', 'imageStyle:side', 'linkImage'
                ]
            },
            table: {
                contentToolbar: [
                    'tableColumn', 'tableRow', 'mergeTableCells',
                    'tableCellProperties', 'tableProperties'
                ]
            }
        })
        .then(function (newEditor) {
            window.editor = newEditor;
            newEditor.editing.view.change(function (writer) {
                writer.setStyle('min-height', '500px', newEditor.editing.view.document.getRoot());
            });
        })
        .catch(function (error) {
            console.error('CKEditor initialization error:', error);
        });
})();
</script>
