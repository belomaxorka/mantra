<!doctype html>
<html lang="<?php echo e(config('locale.default_language', 'en')); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($title) ? e($title) : 'Admin'; ?></title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?php echo $this->moduleAsset('bootstrap/bootstrap.min.css'); ?>" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $this->moduleAsset('css/admin.css'); ?>" rel="stylesheet">

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
  <aside class="admin-sidebar p-3" id="adminSidebar">
    <div class="d-flex align-items-center mb-3">
      <a href="<?php echo e(base_url('/admin')); ?>" class="text-decoration-none sidebar-brand">
        <?php echo e(MANTRA_PROJECT_INFO['name']); ?>
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
              echo '<div class="sidebar-group">' . e($currentGroup) . '</div>';
            }
          }

          $renderSidebarItems(array($item), 0);
        ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <hr>
    <div class="sidebar-footer">
      <?php if (!empty($user) && !empty($user['username'])): ?>
        <?php echo t('admin.layout.signed_in_as'); ?> <strong><?php echo e($user['username']); ?></strong>
      <?php endif; ?>
      <div class="mt-2">
        <a href="<?php echo e(base_url('/')); ?>" target="_blank">
          <i class="bi bi-box-arrow-up-right me-1"></i><?php echo t('admin.layout.view_site'); ?>
        </a>
      </div>
      <div class="mt-2">
        <form method="POST" action="<?php echo e(base_url('/admin/logout')); ?>" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?php echo e(app()->auth()->generateCsrfToken()); ?>">
          <button type="submit" class="btn btn-link p-0 border-0">
            <i class="bi bi-box-arrow-right me-1"></i><?php echo t('admin.layout.logout'); ?>
          </button>
        </form>
      </div>

      <?php $projectInfo = MANTRA_PROJECT_INFO; ?>
        <hr class="my-2">
        <div class="sidebar-meta">
          <div><strong><?php echo e($projectInfo['name']); ?></strong> v<?php echo e($projectInfo['version']); ?></div>
          <div class="mt-1">Released: <?php echo e($projectInfo['release_date']); ?></div>
          <?php if (!empty($projectInfo['github'])): ?>
            <div class="mt-1">
              <a href="<?php echo e($projectInfo['github']); ?>" target="_blank">
                <i class="bi bi-github me-1"></i>GitHub
              </a>
            </div>
          <?php endif; ?>
        </div>
    </div>
  </aside>

  <main class="flex-fill p-4">
    <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
      <nav class="admin-breadcrumb" aria-label="breadcrumb">
        <ol>
          <?php foreach ($breadcrumbs as $i => $crumb):
            $isLast = ($i === count($breadcrumbs) - 1);
          ?>
            <li<?php echo $isLast ? ' class="active" aria-current="page"' : ''; ?>>
              <?php if (!$isLast && !empty($crumb['url'])): ?>
                <a href="<?php echo e($crumb['url']); ?>"><?php echo e($crumb['title']); ?></a>
              <?php else: ?>
                <?php echo e($crumb['title']); ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
    <?php echo isset($content) ? $content : ''; ?>
  </main>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="adminDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo t('admin.common.delete_confirm_title'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo e(t('admin.common.close')); ?>"></button>
      </div>
      <div class="modal-body">
        <p id="adminDeleteMessage"><?php echo t('admin.common.delete_confirm_body'); ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php echo t('admin.common.cancel'); ?></button>
        <button type="button" class="btn btn-danger" id="adminDeleteConfirm">
          <i class="bi bi-trash me-1"></i><?php echo t('admin.common.delete'); ?>
        </button>
      </div>
    </div>
  </div>
</div>
<form id="adminDeleteForm" method="POST" class="d-none">
  <input type="hidden" name="csrf_token" value="<?php echo e(app()->auth()->generateCsrfToken()); ?>">
</form>

<script src="<?php echo $this->moduleAsset('bootstrap/bootstrap.min.js'); ?>"></script>
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

  // Delete confirmation modal
  (function () {
    var modal = document.getElementById('adminDeleteModal');
    var form = document.getElementById('adminDeleteForm');
    var confirmBtn = document.getElementById('adminDeleteConfirm');
    var messageEl = document.getElementById('adminDeleteMessage');

    if (!modal || !form || !confirmBtn) return;

    var bsModal = null;

    window.adminConfirmDelete = function (url, message) {
      form.action = url;
      if (message && messageEl) {
        messageEl.textContent = message;
      }
      if (!bsModal) {
        bsModal = new bootstrap.Modal(modal);
      }
      bsModal.show();
    };

    confirmBtn.addEventListener('click', function () {
      if (form.action) {
        form.submit();
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
