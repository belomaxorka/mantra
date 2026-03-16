<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3"><?php echo t('admin.dashboard.title'); ?></h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo t('admin.dashboard.welcome'); ?>, <?php echo $this->escape($user['username']); ?>!</h5>
                    <p class="card-text text-muted"><?php echo t('admin.dashboard.welcome_message'); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo t('admin.dashboard.quick_actions'); ?></h5>
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
