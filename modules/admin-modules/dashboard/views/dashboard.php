<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">Dashboard</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Welcome, <?php echo $this->escape($user['username']); ?>!</h5>
                    <p class="card-text text-muted">You're logged in to Mantra CMS admin panel.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="<?php echo base_url('/admin/settings'); ?>" class="btn btn-outline-primary">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
