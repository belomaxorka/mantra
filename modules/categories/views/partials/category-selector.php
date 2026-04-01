<div class="card mb-4">
    <div class="card-header"><?php echo t('categories.category'); ?></div>
    <div class="card-body">
        <select name="category" id="category" class="form-select">
            <option value=""><?php echo t('categories.select_category'); ?></option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo e($cat['slug']); ?>"
                    <?php echo (isset($currentCategory) && $currentCategory === $cat['slug']) ? 'selected' : ''; ?>>
                    <?php echo e($cat['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
