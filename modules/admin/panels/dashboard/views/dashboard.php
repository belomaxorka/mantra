<div class="container-fluid">
    <div class="admin-page-header mb-4">
        <h1 class="h3"><?php echo t('admin-dashboard.title'); ?></h1>
    </div>

    <?php if (!empty($stats) && is_array($stats)): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($stats as $stat): ?>
            <div class="col-sm-6 col-lg-4">
                <?php $hasUrl = !empty($stat['url']); ?>
                <<?php echo $hasUrl ? 'a href="' . e($stat['url']) . '"' : 'div'; ?> class="card stat-card stat-card--<?php echo e($stat['color']); ?> text-decoration-none">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-card-icon">
                            <i class="bi <?php echo e($stat['icon']); ?>"></i>
                        </div>
                        <div>
                            <div class="stat-card-value"><?php echo (int)$stat['value']; ?></div>
                            <div class="stat-card-title"><?php echo e($stat['title']); ?></div>
                            <?php if (!empty($stat['sub'])): ?>
                                <div class="stat-card-sub"><?php echo e($stat['sub']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </<?php echo $hasUrl ? 'a' : 'div'; ?>>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><?php echo t('admin-dashboard.recent'); ?></div>
                <div class="card-body">
                    <?php if (empty($recentContent)): ?>
                        <div class="admin-empty-state">
                            <i class="bi bi-clock-history"></i>
                            <p><?php echo t('admin-dashboard.recent.empty'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo t('admin-dashboard.recent.page'); ?></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentContent as $item):
                                        $type = isset($item['_type']) ? $item['_type'] : 'post';
                                        $title = isset($item['title']) ? $item['title'] : '';
                                        $status = isset($item['status']) ? $item['status'] : 'draft';
                                        $updatedAt = isset($item['updated_at']) ? $item['updated_at'] : '';
                                        $editUrl = ($type === 'post')
                                            ? base_url('/admin/posts/edit/' . $item['_id'])
                                            : base_url('/admin/pages/edit/' . $item['_id']);
                                        $typeLabel = ($type === 'post')
                                            ? t('admin-dashboard.recent.post')
                                            : t('admin-dashboard.recent.page');
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo e($editUrl); ?>" class="text-decoration-none fw-medium">
                                                    <?php echo e($title); ?>
                                                </a>
                                                <span class="badge bg-<?php echo $status === 'published' ? 'success' : 'secondary'; ?> ms-2"><?php echo e($status); ?></span>
                                            </td>
                                            <td>
                                                <span class="text-muted small"><?php echo e($typeLabel); ?></span>
                                            </td>
                                            <td class="text-end">
                                                <small class="text-muted"><?php echo e($updatedAt ? clock()->formatDatetime($updatedAt) : ''); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><?php echo t('admin-dashboard.quick_actions'); ?></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if (!empty($quickActions) && is_array($quickActions)): ?>
                            <?php foreach ($quickActions as $action): ?>
                                <?php if (is_array($action) && !empty($action['url']) && !empty($action['title'])): ?>
                                    <a href="<?php echo $this->escape($action['url']); ?>" class="btn btn-outline-primary">
                                        <?php if (!empty($action['icon'])): ?>
                                            <i class="<?php echo $this->escape($action['icon']); ?> me-2"></i>
                                        <?php endif; ?>
                                        <?php echo $this->escape($action['title']); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
