<style>
    @media (max-width: 767.98px) {
        .settings-title {
            font-size: 1.5rem;
        }
        .nav-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
        }
        .nav-tabs .nav-link {
            white-space: nowrap;
        }
    }
</style>

<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <h1 class="h3 settings-title"><?php echo e(isset($pageTitle) ? $pageTitle : 'Settings'); ?></h1>
    </div>
  </div>

  <div class="row">
    <div class="col">
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?php echo e($error); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if (!empty($notice)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?php echo e($notice); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <ul class="nav nav-tabs mb-3" role="tablist">
        <?php foreach ((isset($tabs) ? $tabs : array()) as $tab): ?>
          <?php
            $tabId = (string)(isset($tab['id']) ? $tab['id'] : 'tab');
            $tabTitle = (string)(isset($tab['title']) ? $tab['title'] : $tabId);
            $tabUrl = (string)(isset($tab['url']) ? $tab['url'] : '#');
            $isActive = !empty($tab['active']);
          ?>
          <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo e($tabUrl); ?>" role="tab">
              <?php echo e($tabTitle); ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div>
        <?php echo isset($contentHtml) ? $contentHtml : ''; ?>
      </div>
    </div>
  </div>
</div>
