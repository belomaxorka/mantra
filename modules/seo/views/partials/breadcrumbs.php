<?php
/**
 * Breadcrumbs partial
 *
 * @var array $breadcrumbs Array of breadcrumb items
 */

if (empty($breadcrumbs) || !is_array($breadcrumbs)) {
    return;
}
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <?php foreach ($breadcrumbs as $item): ?>
            <?php if (!empty($item['url'])): ?>
                <li class="breadcrumb-item">
                    <a href="<?php echo e($item['url']); ?>">
                        <?php echo e($item['title']); ?>
                    </a>
                </li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php echo e($item['title']); ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
