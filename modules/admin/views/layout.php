<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($title) ? e($title) : 'Admin'; ?></title>

  <link href="/<?php echo basename(MANTRA_CORE); ?>/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    .admin-shell { min-height: 100vh; }
    .admin-sidebar {
      width: 280px;
      flex-shrink: 0;
      overflow-y: auto;
      max-height: 100vh;
      position: sticky;
      top: 0;
    }
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

    /* Mobile header */
    .admin-mobile-header {
      display: none;
      position: sticky;
      top: 0;
      z-index: 1020;
      background: var(--bs-body-bg);
      border-bottom: 1px solid var(--bs-border-color);
      padding: 0.75rem 1rem;
    }

    .admin-mobile-toggle {
      border: none;
      background: none;
      font-size: 1.5rem;
      padding: 0.25rem 0.5rem;
      cursor: pointer;
      color: var(--bs-body-color);
    }

    @media (prefers-reduced-motion: no-preference) {
      .admin-sidebar .nav-link.is-parent.expanded .caret {
        transition: transform 0.15s ease-in-out;
      }
      .admin-sidebar {
        transition: transform 0.3s ease-in-out;
      }
    }

    /* Mobile responsive styles */
    @media (max-width: 991.98px) {
      .admin-mobile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }

      .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        max-width: 85vw;
        z-index: 1030;
        transform: translateX(-100%);
        box-shadow: 2px 0 8px rgba(0,0,0,0.1);
      }

      .admin-sidebar.show {
        transform: translateX(0);
      }

      .admin-sidebar-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1025;
      }

      .admin-sidebar-backdrop.show {
        display: block;
      }

      .admin-shell {
        flex-direction: column;
      }

      main.flex-fill {
        width: 100%;
        padding: 1rem !important;
      }
    }

    /* Touch-friendly targets */
    @media (max-width: 991.98px) {
      .admin-sidebar .nav-link {
        padding: 0.75rem 1rem;
        font-size: 1rem;
      }

      .admin-sidebar .nav-link.level-1 { padding-left: 1.75rem; }
      .admin-sidebar .nav-link.level-2 { padding-left: 2.75rem; }
      .admin-sidebar .nav-link.level-3 { padding-left: 3.75rem; }
    }

    /* Small mobile adjustments */
    @media (max-width: 575.98px) {
      main.flex-fill {
        padding: 0.75rem !important;
      }

      .admin-sidebar {
        max-width: 90vw;
      }
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

          $nodeId = isset($item['id']) ? $item['id'] : '';
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

          $group = isset($item['group']) ? $item['group'] : '';
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

  // Mobile sidebar toggle
  (function () {
    var sidebar = document.getElementById('adminSidebar');
    var backdrop = document.getElementById('adminSidebarBackdrop');
    var toggle = document.getElementById('adminMenuToggle');

    if (!sidebar || !backdrop || !toggle) return;

    function openSidebar() {
      sidebar.classList.add('show');
      backdrop.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
      sidebar.classList.remove('show');
      backdrop.classList.remove('show');
      document.body.style.overflow = '';
    }

    // Toggle button click
    toggle.addEventListener('click', function () {
      if (sidebar.classList.contains('show')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    // Backdrop click
    backdrop.addEventListener('click', closeSidebar);

    // Close sidebar when clicking on navigation links (on mobile)
    sidebar.addEventListener('click', function (e) {
      var link = e.target.closest('a.nav-link:not(.is-parent)');
      if (link && window.innerWidth <= 991.98) {
        closeSidebar();
      }
    });

    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function () {
      if (window.innerWidth > 991.98) {
        closeSidebar();
      }
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
