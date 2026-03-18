<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($title) ? e($title) : 'Admin'; ?></title>

  <link href="/<?php echo basename(MANTRA_CORE); ?>/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <?php
    $adminHead = app()->hooks()->fire('admin.head', '');
    if (is_array($adminHead)) {
      $adminHead = implode("\n", $adminHead);
    }
    echo is_string($adminHead) ? $adminHead : '';
  ?>
</head>
<body>
<div class="admin-mobile-header">
  <span class="fs-5 fw-semibold"><?php echo e(MANTRA_PROJECT_INFO['name']); ?></span>
  <button class="admin-mobile-toggle" id="adminMenuToggle" aria-label="Toggle menu">
    <i class="bi bi-list"></i>
  </button>
</div>

<div class="admin-sidebar-backdrop" id="adminSidebarBackdrop"></div>

<div class="admin-shell d-flex">
  <aside class="admin-sidebar border-end bg-body-tertiary p-3" id="adminSidebar">
    <div class="d-flex align-items-center mb-3">
      <a href="<?php echo e(base_url('/admin')); ?>" class="text-decoration-none text-dark">
        <span class="fs-5 fw-semibold"><?php echo e(MANTRA_PROJECT_INFO['name']); ?></span>
      </a>
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

          $url = isset($item['url']) ? $item['url'] : '#';
          $title = isset($item['title']) ? $item['title'] : (isset($item['id']) ? $item['id'] : '');
          $icon = isset($item['icon']) ? $item['icon'] : '';
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

          $nodeId = isset($item['id']) ? $item['id'] :  '';
          $collapseId = 'admin-nav-' . preg_replace('/[^a-z0-9_-]/i', '-', (string)$nodeId) . '-' . (int)$level;

          if ($hasChildren) {
            // Parent acts as collapsible toggle.
            echo '<a class="' . $classes . '" href="#" role="button" data-admin-collapse="' . e($collapseId) . '">';
            echo '<span>';
            if (!empty($icon)) {
              echo '<i class="bi ' . e($icon) . ' me-2"></i>';
            }
            echo e($title);
            echo '</span>';
            echo '<span class="caret">›</span>';
            echo '</a>';

            echo '<div class="nav-subtree ' . (!$expanded ? 'collapsed' : '') . '" id="' . e($collapseId) . '">';
            $renderSidebarItems($children, $level + 1);
            echo '</div>';
          } else {
            echo '<a class="' . $classes . '" href="' . e($url) . '">';
            if (!empty($icon)) {
              echo '<i class="bi ' . e($icon) . ' me-2"></i>';
            }
            echo e($title);
            echo '</a>';
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

          $group = isset($item['group']) ? $item['group'] :  '';
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
        <?php echo t('admin.layout.signed_in_as'); ?> <strong><?php echo e($user['username']); ?></strong>
      <?php endif; ?>
      <div class="mt-2">
        <a href="<?php echo e(base_url('/')); ?>" class="link-secondary text-decoration-none" target="_blank">
          <i class="bi bi-box-arrow-up-right me-1"></i><?php echo t('admin.layout.view_site'); ?>
        </a>
      </div>
      <div class="mt-2">
        <a href="<?php echo e(base_url('/admin/logout')); ?>" class="link-secondary text-decoration-none">
          <i class="bi bi-box-arrow-right me-1"></i><?php echo t('admin.layout.logout'); ?>
        </a>
      </div>

      <?php if (defined('MANTRA_PROJECT_INFO')): ?>
        <?php $projectInfo = MANTRA_PROJECT_INFO; ?>
        <hr class="my-2">
        <div class="text-muted" style="font-size: 0.85rem;">
          <div><strong><?php echo e($projectInfo['name']); ?></strong> v<?php echo e($projectInfo['version']); ?></div>
          <div class="mt-1">Released: <?php echo e($projectInfo['release_date']); ?></div>
          <?php if (!empty($projectInfo['github'])): ?>
            <div class="mt-1">
              <a href="<?php echo e($projectInfo['github']); ?>" class="link-secondary text-decoration-none" target="_blank">
                <i class="bi bi-github me-1"></i>GitHub
              </a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </aside>

  <main class="flex-fill p-4">
    <?php echo isset($content) ? $content : ''; ?>
  </main>
</div>

<script src="/<?php echo basename(MANTRA_CORE); ?>/assets/bootstrap/bootstrap.min.js"></script>
<?php
  $adminFooter = app()->hooks()->fire('admin.footer', '');
  if (is_array($adminFooter)) {
    $adminFooter = implode("\n", $adminFooter);
  }
  echo is_string($adminFooter) ? $adminFooter : '';
?>
</body>
</html>
