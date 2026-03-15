<?php
// Basic placeholder profile view (admin-side).
// This module is still evolving; keep this minimal.

$user = isset($user) && is_array($user) ? $user : array();
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="h3 mb-0">Profile</h1>
</div>

<div class="card">
  <div class="card-body">
    <div class="mb-2"><strong>Username:</strong> <?php echo e($user['username'] ?? ''); ?></div>
    <div class="mb-2"><strong>Email:</strong> <?php echo e($user['email'] ?? ''); ?></div>
    <div class="text-secondary small">Profile editing UI is not implemented yet.</div>
  </div>
</div>
