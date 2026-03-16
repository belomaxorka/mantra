<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3"><?php echo $isNew ? 'New Page' : 'Edit Page'; ?></h1>
                <a href="<?php echo base_url('/admin/pages'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Pages
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
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="title"
                                   name="title"
                                   value="<?php echo $this->escape($page['title']); ?>"
                                   required
                                   placeholder="Enter page title">
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug</label>
                            <input type="text"
                                   class="form-control"
                                   id="slug"
                                   name="slug"
                                   value="<?php echo $this->escape($page['slug']); ?>"
                                   placeholder="page-url (auto-generated if empty)">
                            <div class="form-text">URL-friendly version of the title. Leave empty to auto-generate.</div>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea id="content" name="content" class="form-control"><?php echo $this->escape($page['content']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Publish</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio"
                                       class="btn-check"
                                       name="status"
                                       id="status_draft"
                                       value="draft"
                                       <?php echo ($page['status'] === 'draft') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-secondary" for="status_draft">
                                    <i class="bi bi-file-earmark me-1"></i>Draft
                                </label>

                                <input type="radio"
                                       class="btn-check"
                                       name="status"
                                       id="status_published"
                                       value="published"
                                       <?php echo ($page['status'] === 'published') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-success" for="status_published">
                                    <i class="bi bi-check-circle me-1"></i>Published
                                </label>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i><?php echo $isNew ? 'Create Page' : 'Update Page'; ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Featured Image</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="image" class="form-label">Image URL</label>
                            <input type="text"
                                   class="form-control"
                                   id="image"
                                   name="image"
                                   value="<?php echo $this->escape($page['image']); ?>"
                                   placeholder="https://example.com/image.jpg">
                            <div class="form-text">Enter the URL of the page poster image.</div>
                        </div>

                        <?php if (!empty($page['image'])): ?>
                            <div class="mt-2">
                                <img src="<?php echo $this->escape($page['image']); ?>"
                                     alt="Preview"
                                     class="img-fluid rounded"
                                     style="max-height: 200px;"
                                     onerror="this.style.display='none'">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <strong>Navigation</strong>
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
                                    Show in navigation menu
                                </label>
                            </div>
                            <div class="form-text">Display this page in the site header navigation.</div>
                        </div>

                        <div class="mb-0">
                            <label for="navigation_order" class="form-label">Navigation Order</label>
                            <input type="number"
                                   class="form-control"
                                   id="navigation_order"
                                   name="navigation_order"
                                   value="<?php echo isset($page['navigation_order']) ? (int)$page['navigation_order'] : 50; ?>"
                                   min="0"
                                   step="1">
                            <div class="form-text">Lower numbers appear first in the menu.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#content',
    height: 500,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | ' +
        'bold italic forecolor | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | link image media | code | help',
    content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
    branding: false,
    promotion: false,
    image_advtab: true,
    link_default_target: '_blank',
    link_assume_external_targets: true,
    relative_urls: false,
    remove_script_host: false,
    convert_urls: true,
    image_caption: true,
    image_title: true,
    automatic_uploads: false,
    file_picker_types: 'image',
    images_upload_handler: function (blobInfo, success, failure) {
        // For now, just show a message that upload is not configured
        failure('Image upload not configured. Please use image URL instead.');
    }
});

// Auto-generate slug from title
document.getElementById('title').addEventListener('input', function() {
    var slugField = document.getElementById('slug');
    if (<?php echo $isNew ? 'true' : 'false'; ?> && slugField.value === '') {
        var slug = this.value
            .toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        slugField.value = slug;
    }
});
</script>
