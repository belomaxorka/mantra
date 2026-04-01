<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="admin-page-header">
                <h1 class="h3"><?php echo $isNew ? t('admin-posts.new_post') : t('admin-posts.edit_post'); ?></h1>
                <a href="<?php echo base_url('/admin/posts'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i><?php echo t('admin.common.back'); ?>
                </a>
            </div>
        </div>
    </div>

    <form method="POST" action="<?php echo $isNew ? base_url('/admin/posts/new') : base_url('/admin/posts/edit/' . $post['_id']); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

        <div class="row">
        <div class="col-lg-8">
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

        <div class="col-lg-4">
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
                        <?php if (!$isNew && $post['status'] === 'published'): ?>
                            <a href="<?php echo base_url('/post/' . $post['slug']); ?>"
                               class="btn btn-outline-secondary"
                               target="_blank">
                                <i class="bi bi-eye"></i>
                                <?php echo t('admin-posts.view'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo base_url('/admin/posts'); ?>" class="btn btn-outline-secondary">
                            <?php echo t('admin-posts.cancel'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <?php echo t('admin-posts.metadata'); ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="category" class="form-label">
                            <?php echo t('admin-posts.field.category'); ?>
                        </label>
                        <input type="text"
                               class="form-control"
                               id="category"
                               name="category"
                               value="<?php echo e($post['category']); ?>">
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/classic/ckeditor.js"></script>
<script>
let editor;
ClassicEditor
    .create(document.querySelector('#content'), {
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
        simpleUpload: {
            uploadUrl: '<?php echo base_url('/admin/uploads/api/upload'); ?>',
            withCredentials: true
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
        editor = newEditor;
        editor.editing.view.change(function (writer) {
            writer.setStyle('min-height', '500px', editor.editing.view.document.getRoot());
        });
    })
    .catch(function (error) {
        console.error('CKEditor initialization error:', error);
    });
</script>
