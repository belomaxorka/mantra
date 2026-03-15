<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($title) ? e($title) : 'Admin'; ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    .admin-shell { min-height: 100vh; }
    .admin-sidebar { width: 280px; }
    .admin-sidebar .nav-link.active { font-weight: 600; }
    @media (max-width: 991.98px) {
      .admin-sidebar { width: 100%; }
    }
  </style>
</head>
<body>
<div class="admin-shell d-flex">
  <aside class="admin-sidebar border-end bg-body-tertiary p-3">
    <div class="d-flex align-items-center mb-3">
      <span class="fs-5 fw-semibold">Mantra</span>
    </div>

    <?php if (!empty($sidebarItems)): ?>
      <?php $currentGroup = null; ?>
      <?php foreach ($sidebarItems as $item): ?>
        <?php
          $group = $item['group'] ?? '';
          if ($group !== $currentGroup) {
            $currentGroup = $group;
            if ($currentGroup !== '') {
              echo '<div class="text-uppercase text-secondary small mt-3 mb-1">' . e($currentGroup) . '</div>';
            }
          }
        ?>

        <ul class="nav nav-pills flex-column">
          <li class="nav-item">
            <a class="nav-link <?php echo !empty($item['active']) ? 'active' : ''; ?>" href="<?php echo e($item['url'] ?? '#'); ?>">
              <?php if (!empty($item['icon'])): ?>
                <i class="bi <?php echo e($item['icon']); ?> me-2"></i>
              <?php endif; ?>
              <?php echo e($item['title'] ?? ($item['id'] ?? '')); ?>
            </a>
          </li>
        </ul>
      <?php endforeach; ?>
    <?php endif; ?>

    <hr>
    <div class="small text-secondary">
      <?php if (!empty($user) && !empty($user['username'])): ?>
        Signed in as <strong><?php echo e($user['username']); ?></strong>
      <?php endif; ?>
      <div class="mt-2">
        <a href="<?php echo e(base_url('/admin/logout')); ?>" class="link-secondary text-decoration-none">Logout</a>
      </div>
    </div>
  </aside>

  <main class="flex-fill p-4">
    <?php echo isset($content) ? $content : ''; ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
