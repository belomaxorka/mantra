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
    .admin-sidebar .nav-link.level-1 { padding-left: 1.5rem; }
    .admin-sidebar .nav-link.level-2 { padding-left: 2.5rem; }
    .admin-sidebar .nav-link.level-3 { padding-left: 3.5rem; }

    .admin-sidebar .nav-link.is-parent {
      font-weight: 600;
      color: var(--bs-body-color);
    }
    .admin-sidebar .nav-link.is-parent.expanded {
      background: rgba(13, 110, 253, 0.08);
    }
    @media (max-width: 991.98px) {
      .admin-sidebar { width: 100%; }
    }
  </style>

  <?php
    $adminHead = app()->hooks()->fire('admin.head', '');
    if (is_array($adminHead)) {
      $adminHead = implode("\n", $adminHead);
    }
    echo is_string($adminHead) ? $adminHead : '';
  ?>
</head>
<body>
<div class="admin-shell d-flex">
  <aside class="admin-sidebar border-end bg-body-tertiary p-3">
    <div class="d-flex align-items-center mb-3">
      <span class="fs-5 fw-semibold">Mantra</span>
    </div>

    <?php
      $renderSidebarItems = function ($items, $level = 0) use (&$renderSidebarItems) {
        if (empty($items) || !is_array($items)) {
          return;
        }

        echo '<ul class="nav nav-pills flex-column">';
        foreach ($items as $item) {
          if (!is_array($item)) {
            continue;
          }

          $url = $item['url'] ?? '#';
          $title = $item['title'] ?? ($item['id'] ?? '');
          $icon = $item['icon'] ?? '';
          $active = !empty($item['active']);
          $expanded = !empty($item['expanded']);
          $children = isset($item['children']) && is_array($item['children']) ? $item['children'] : array();
          $hasChildren = !empty($children);

          $levelClass = 'level-' . (int)$level;

          echo '<li class="nav-item">';
          $classes = 'nav-link ';
          if ($active) {
            $classes .= 'active ';
          }
          if ($hasChildren) {
            $classes .= 'is-parent ';
            if ($expanded) {
              $classes .= 'expanded ';
            }
          }
          $classes .= e($levelClass);

          // Parent items act as headers (no navigation) by default.
          $href = $hasChildren ? '#' : $url;

          echo '<a class="' . $classes . '" href="' . e($href) . '">';
          if (!empty($icon)) {
            echo '<i class="bi ' . e($icon) . ' me-2"></i>';
          }
          echo e($title);
          echo '</a>';

          if (!empty($children)) {
            $renderSidebarItems($children, $level + 1);
          }

          echo '</li>';
        }
        echo '</ul>';
      };
    ?>

    <?php if (!empty($sidebarItems) && is_array($sidebarItems)): ?>
      <?php $currentGroup = null; ?>
      <?php foreach ($sidebarItems as $item): ?>
        <?php
          if (!is_array($item)) {
            continue;
          }

          $group = $item['group'] ?? '';
          if ($group !== $currentGroup) {
            $currentGroup = $group;
            if ($currentGroup !== '') {
              echo '<div class="text-uppercase text-secondary small mt-3 mb-1">' . e($currentGroup) . '</div>';
            }
          }

          $renderSidebarItems(array($item), 0);
        ?>
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
<?php
  $adminFooter = app()->hooks()->fire('admin.footer', '');
  if (is_array($adminFooter)) {
    $adminFooter = implode("\n", $adminFooter);
  }
  echo is_string($adminFooter) ? $adminFooter : '';
?>
</body>
</html>
