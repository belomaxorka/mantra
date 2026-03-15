<div class="admin-dashboard">
    <h1>Dashboard</h1>
    
    <p>Welcome, <?php echo $this->escape($user['username']); ?>!</p>
    
    <div class="admin-menu">
        <a href="<?php echo base_url('/admin/pages'); ?>" class="btn">Manage Pages</a>
        <a href="<?php echo base_url('/admin/posts'); ?>" class="btn">Manage Posts</a>
        <a href="<?php echo base_url('/admin/media'); ?>" class="btn">Media Library</a>
        <a href="<?php echo base_url('/admin/users'); ?>" class="btn">Users</a>
        <a href="<?php echo base_url('/admin/settings'); ?>" class="btn">Settings</a>
    </div>
</div>
