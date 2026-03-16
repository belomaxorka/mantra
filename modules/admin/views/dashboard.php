<div class="admin-dashboard">
    <h1>Dashboard</h1>
    
    <p>Welcome, <?php echo $this->escape($user['username']); ?>!</p>
    
    <div class="admin-menu">
        <a href="<?php echo base_url('/admin/settings'); ?>" class="btn">Settings</a>
    </div>
</div>
