<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="admin-page-header">
                <h1 class="h3"><?php echo $isNew ? t('admin-posts.new') : t('admin-posts.edit_post'); ?></h1>
                <a href="<?php echo base_url('/admin/posts'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i><?php echo t('admin.common.back'); ?>
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { adminToast(<?php echo json_encode(e($error), JSON_HEX_TAG); ?>, 'danger'); });</script>
    <?php endif; ?>

    <form method="POST" id="postForm" action="<?php echo $isNew ? base_url('/admin/posts/new') : base_url('/admin/posts/edit/' . $post['_id']); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
        <?php if (!$isNew): ?>
            <input type="hidden" name="_preview_id" value="<?php echo e($post['_id']); ?>">
        <?php endif; ?>

        <div class="row">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">
                            <?php echo t('admin-posts.field.title'); ?> <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="title"
                               name="title"
                               value="<?php echo e($post['title']); ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="slug" class="form-label">
                            <?php echo t('admin-posts.field.slug'); ?>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="slug"
                               name="slug"
                               value="<?php echo e($post['slug']); ?>">
                        <div class="form-text">
                            <?php echo t('admin-posts.slug_help'); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="excerpt" class="form-label">
                            <?php echo t('admin-posts.field.excerpt'); ?>
                        </label>
                        <textarea class="form-control"
                                  id="excerpt"
                                  name="excerpt"
                                  rows="3"><?php echo e($post['excerpt']); ?></textarea>
                        <div class="form-text">
                            <?php echo t('admin-posts.excerpt_help'); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">
                            <?php echo t('admin-posts.field.content'); ?>
                        </label>
                        <textarea class="form-control"
                                  id="content"
                                  name="content"><?php echo e($post['content']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <?php echo t('admin-posts.publish'); ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <?php echo t('admin-posts.field.status'); ?>
                        </label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio"
                                   class="btn-check"
                                   name="status"
                                   id="status_draft"
                                   value="draft"
                                   <?php echo ($post['status'] === 'draft') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-secondary" for="status_draft">
                                <i class="bi bi-file-earmark me-1"></i><?php echo t('admin-posts.status.draft'); ?>
                            </label>

                            <input type="radio"
                                   class="btn-check"
                                   name="status"
                                   id="status_published"
                                   value="published"
                                   <?php echo ($post['status'] === 'published') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-success" for="status_published">
                                <i class="bi bi-check-circle me-1"></i><?php echo t('admin-posts.status.published'); ?>
                            </label>
                        </div>
                    </div>

                    <?php if (!$isNew && isset($post['author'])): ?>
                        <div class="mb-3">
                            <label class="form-label">
                                <?php echo t('admin-posts.field.author'); ?>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   value="<?php echo e($post['author']); ?>"
                                   readonly>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i>
                            <?php echo $isNew ? t('admin-posts.create') : t('admin-posts.update'); ?>
                        </button>
                        <button type="button" id="btnPreview" class="btn btn-outline-info">
                            <i class="bi bi-eye"></i>
                            <?php echo t('admin-posts.preview'); ?>
                        </button>
                        <?php if (!$isNew && $post['status'] === 'published'): ?>
                            <a href="<?php echo base_url('/post/' . $post['slug']); ?>"
                               class="btn btn-outline-secondary"
                               target="_blank">
                                <i class="bi bi-box-arrow-up-right"></i>
                                <?php echo t('admin-posts.view'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <?php echo t('admin-posts.featured_image'); ?>
                </div>
                <div class="card-body">
                    <input type="hidden" name="image" id="image" value="<?php echo e($post['image'] ?? ''); ?>">

                    <div id="imagePreview" class="mb-3 text-center <?php echo empty($post['image']) ? 'd-none' : ''; ?>">
                        <img src="<?php echo e($post['image'] ?? ''); ?>"
                             id="imagePreviewImg"
                             alt=""
                             class="img-fluid rounded"
                             style="max-height: 200px;"
                             onerror="this.style.display='none'">
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeImage()">
                                <i class="bi bi-trash me-1"></i><?php echo t('admin-posts.image_remove'); ?>
                            </button>
                        </div>
                    </div>

                    <div id="imageUpload" class="<?php echo !empty($post['image']) ? 'd-none' : ''; ?>">
                        <div id="imageDropZone"
                             class="border border-2 border-dashed rounded p-3 text-center"
                             style="cursor: pointer;">
                            <i class="bi bi-image text-muted fs-3"></i>
                            <p class="small text-muted mb-1"><?php echo t('admin-posts.image_drop'); ?></p>
                            <input type="file" id="imageFile" class="d-none" accept="image/*">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('imageFile').click()">
                                <i class="bi bi-upload me-1"></i><?php echo t('admin-posts.image_choose'); ?>
                            </button>
                        </div>
                        <div id="imageUploading" class="text-center py-3 d-none">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span class="ms-2 text-muted"><?php echo t('admin-posts.image_uploading'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                var elDropZone  = document.getElementById('imageDropZone');
                var elFileInput = document.getElementById('imageFile');
                var elUploading = document.getElementById('imageUploading');
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

                    Mantra.ajax('uploads.upload', form, { toast: false })
                        .done(function(data) {
                            elUploading.classList.add('d-none');
                            setImage(data.url);
                        })
                        .fail(function(errMsg) {
                            elUploading.classList.add('d-none');
                            elDropZone.classList.remove('d-none');
                            adminToast(errMsg, 'danger');
                        });
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

            <?php echo app()->hooks()->fire('admin.posts.edit.sidebar', '', $post); ?>
        </div>
    </form>
</div>

<script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/classic/ckeditor.js"></script>
<script>
(function() {
    // Custom upload adapter for CKEditor 5 (CDN build lacks SimpleUploadAdapter)
    function MantraUploadAdapter(loader) { this.loader = loader; }
    MantraUploadAdapter.prototype.upload = function() {
        var loader = this;
        return this.loader.file.then(function(file) {
            return new Promise(function(resolve, reject) {
                var form = new FormData();
                form.append('file', file);
                var xhr = new XMLHttpRequest();
                loader._xhr = xhr;
                xhr.open('POST', Mantra.baseUrl() + '/admin/ajax?action=uploads.upload', true);
                xhr.setRequestHeader('X-CSRF-Token', Mantra.csrfToken());
                xhr.onload = function() {
                    try { var resp = JSON.parse(xhr.responseText); } catch(e) { return reject('Invalid server response'); }
                    if (!resp.ok) return reject(resp.error || 'Upload failed');
                    if (!resp.data || !resp.data.url) return reject('No URL in response');
                    resolve({ default: resp.data.url });
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
                insert: {
                    integrations: ['upload', 'url']
                },
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
<script>
(function() {
    var previewUrl = '<?php echo base_url('/admin/posts/preview'); ?>';
    document.getElementById('btnPreview').addEventListener('click', function() {
        if (window.editor) {
            window.editor.updateSourceElement();
        }
        var form = document.getElementById('postForm');
        var previewForm = document.createElement('form');
        previewForm.method = 'POST';
        previewForm.action = previewUrl;
        previewForm.target = '_blank';
        previewForm.style.display = 'none';
        var elements = form.elements;
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name) continue;
            if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) continue;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = el.name;
            input.value = el.value;
            previewForm.appendChild(input);
        }
        document.body.appendChild(previewForm);
        previewForm.submit();
        document.body.removeChild(previewForm);
    });
})();
</script>
