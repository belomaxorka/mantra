<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="admin-page-header">
                <h1 class="h3"><?php echo $isNew ? t('categories.new_category') : t('categories.edit_category'); ?></h1>
                <a href="<?php echo base_url('/admin/categories'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i><?php echo t('admin.common.back'); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <form method="POST" action="<?php echo $isNew ? base_url('/admin/categories/new') : base_url('/admin/categories/edit/' . $category['_id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">
                                <?php echo t('categories.field.title'); ?> <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="title"
                                   name="title"
                                   value="<?php echo e($category['title']); ?>"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">
                                <?php echo t('categories.field.slug'); ?>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="slug"
                                   name="slug"
                                   value="<?php echo e($category['slug']); ?>">
                            <div class="form-text"><?php echo t('categories.slug_help'); ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">
                                <?php echo t('categories.field.description'); ?>
                            </label>
                            <textarea class="form-control"
                                      id="description"
                                      name="description"
                                      rows="3"><?php echo e(isset($category['description']) ? $category['description'] : ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="order" class="form-label">
                                <?php echo t('categories.field.order'); ?>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   id="order"
                                   name="order"
                                   value="<?php echo (int)(isset($category['order']) ? $category['order'] : 0); ?>"
                                   min="0"
                                   max="999">
                            <div class="form-text"><?php echo t('categories.order_help'); ?></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>
                                <?php echo $isNew ? t('categories.create') : t('categories.update'); ?>
                            </button>
                            <a href="<?php echo base_url('/admin/categories'); ?>" class="btn btn-outline-secondary">
                                <?php echo t('admin.common.back'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
