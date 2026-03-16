<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($title) ? e($title) : 'Admin'; ?></title>

  <link href="/core/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
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

    .admin-sidebar .nav-link.is-parent {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .admin-sidebar .nav-link.is-parent .caret {
      opacity: 0.7;
      font-size: 0.9em;
      margin-left: 0.5rem;
    }
    .admin-sidebar .nav-link.is-parent.expanded .caret {
      transform: rotate(90deg);
    }
    .admin-sidebar .nav-subtree {
      margin-top: 0.15rem;
      margin-bottom: 0.15rem;
      border-left: 1px solid rgba(0,0,0,0.08);
      margin-left: 0.5rem;
      padding-left: 0.25rem;
    }
    .admin-sidebar .nav-subtree.collapsed {
      display: none;
    }
    @media (prefers-reduced-motion: no-preference) {
      .admin-sidebar .nav-link.is-parent.expanded .caret {
        transition: transform 0.15s ease-in-out;
      }
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

          $nodeId = $item['id'] ?? '';
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
        <?php echo t('admin.layout.signed_in_as'); ?> <strong><?php echo e($user['username']); ?></strong>
      <?php endif; ?>
      <div class="mt-2">
        <a href="<?php echo e(base_url('/admin/logout')); ?>" class="link-secondary text-decoration-none"><?php echo t('admin.layout.logout'); ?></a>
      </div>
    </div>
  </aside>

  <main class="flex-fill p-4">
    <?php echo isset($content) ? $content : ''; ?>
  </main>
</div>

<script src="/core/assets/bootstrap/bootstrap.min.js"></script>
<script>
  // Auto-dismiss alerts after 3 seconds
  (function () {
    var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function (alert) {
      setTimeout(function () {
        var bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      }, 3000);
    });
  })();

  // Sidebar collapse toggle
  (function () {
    function toggle(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.classList.toggle('collapsed');

      var toggles = document.querySelectorAll('[data-admin-collapse="' + CSS.escape(id) + '"]');
      toggles.forEach(function (a) {
        a.classList.toggle('expanded', !el.classList.contains('collapsed'));
      });
    }

    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('[data-admin-collapse]');
      if (!a) return;
      e.preventDefault();
      toggle(a.getAttribute('data-admin-collapse'));
    });
  })();
</script>
<?php
  $adminFooter = app()->hooks()->fire('admin.footer', '');
  if (is_array($adminFooter)) {
    $adminFooter = implode("\n", $adminFooter);
  }
  echo is_string($adminFooter) ? $adminFooter : '';
?>
</body>
</html>
