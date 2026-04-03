<?php
// Reusable hook table renderer
function renderHookTable($hooks): void {
    ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><?php echo t('admin-hooks.col.name'); ?></th>
                    <th><?php echo t('admin-hooks.col.description'); ?></th>
                    <th><?php echo t('admin-hooks.col.data_type'); ?></th>
                    <th class="text-center"><?php echo t('admin-hooks.col.listeners'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hooks as $hook): ?>
                    <tr>
                        <td><code><?php echo e($hook['name']); ?></code></td>
                        <td>
                            <?php if (!empty($hook['description'])): ?>
                                <span class="text-muted"><?php echo e($hook['description']); ?></span>
                            <?php elseif (empty($hook['registered'])): ?>
                                <span class="text-muted fst-italic"><?php echo t('admin-hooks.not_documented'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($hook['context'])): ?>
                                <br><small class="text-muted"><?php echo t('admin-hooks.context'); ?>: <code><?php echo e($hook['context']); ?></code></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($hook['data_type'])): ?>
                                <code class="text-muted"><?php echo e($hook['data_type']); ?></code>
                                <?php if (!empty($hook['return_type']) && $hook['return_type'] !== $hook['data_type']): ?>
                                    &rarr; <code class="text-muted"><?php echo e($hook['return_type']); ?></code>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($hook['listeners'] > 0): ?>
                                <span class="badge bg-success"><?php echo $hook['listeners']; ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

$coreGroupLabels = [
    'system' => t('admin-hooks.group.system'),
    'theme' => t('admin-hooks.group.theme'),
    'admin' => t('admin-hooks.group.admin'),
    'content' => t('admin-hooks.group.content'),
    'other' => t('admin-hooks.group.other'),
];
$coreGroupOrder = ['system', 'theme', 'admin', 'content', 'other'];
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="admin-page-header">
                <h1 class="h3"><?php echo t('admin-hooks.title'); ?></h1>
                <span class="text-muted">
                    <?php echo t('admin-hooks.total_hooks', ['count' => $totalHooks]); ?>
                    &middot;
                    <?php echo t('admin-hooks.total_listeners', ['count' => $totalListeners]); ?>
                </span>
            </div>
        </div>
    </div>

    <?php foreach ($coreGroupOrder as $groupKey): ?>
        <?php if (empty($coreGroups[$groupKey])) continue; ?>
        <div class="row mb-3">
            <div class="col">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><?php echo $coreGroupLabels[$groupKey]; ?></span>
                        <span class="badge bg-secondary"><?php echo count($coreGroups[$groupKey]); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php renderHookTable($coreGroups[$groupKey]); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($moduleGroups)): ?>
        <h2 class="h5 text-muted mb-3 mt-4"><?php echo t('admin-hooks.modules_section'); ?></h2>

        <?php foreach ($moduleGroups as $source => $hooks): ?>
            <div class="row mb-3">
                <div class="col">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-puzzle me-2"></i><?php echo e(ucfirst($source)); ?></span>
                            <span class="badge bg-secondary"><?php echo count($hooks); ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php renderHookTable($hooks); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
