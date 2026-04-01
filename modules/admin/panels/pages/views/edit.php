<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="admin-page-header">
                <h1 class="h3"><?php echo $isNew ? t('admin-pages.new') : t('admin-pages.edit'); ?></h1>
                <a href="<?php echo base_url('/admin/pages'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i><?php echo t('admin-pages.back_to_list'); ?>
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
                        <strong><?php echo t('admin-pages.publish'); ?></strong>
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
                        <strong><?php echo t('admin-pages.featured_image'); ?></strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="image" class="form-label"><?php echo t('admin-pages.image_field'); ?></label>
                            <input type="text"
                                   class="form-control"
                                   id="image"
                                   name="image"
                                   value="<?php echo $this->escape($page['image']); ?>"
                                   placeholder="https://example.com/image.jpg">
                            <div class="form-text"><?php echo t('admin-pages.image_help'); ?></div>
                        </div>

                        <?php if (!empty($page['image'])): ?>
                            <div class="mt-2">
                                <img src="<?php echo $this->escape($page['image']); ?>"
                                     alt="<?php echo t('admin-pages.image_preview'); ?>"
                                     class="img-fluid rounded"
                                     style="max-height: 200px;"
                                     onerror="this.style.display='none'">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <strong><?php echo t('admin-pages.field.navigation'); ?></strong>
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
    .then(newEditor => {
        editor = newEditor;
        // Set minimum height
        editor.editing.view.change(writer => {
            writer.setStyle('min-height', '500px', editor.editing.view.document.getRoot());
        });
    })
    .catch(error => {
        console.error('CKEditor initialization error:', error);
    });
</script>
