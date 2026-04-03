<div class="container-fluid">
  <div class="admin-page-header mb-4">
      <h1 class="h3"><?php echo e($pageTitle ?? 'Settings'); ?></h1>
  </div>

  <div class="row">
    <div class="col">
      <?php if (!empty($error)): ?>
      <script>document.addEventListener('DOMContentLoaded', function() { adminToast(<?php echo json_encode(e($error), JSON_HEX_TAG); ?>, 'danger'); });</script>
      <?php endif; ?>
      <?php if (!empty($notice)): ?>
      <script>document.addEventListener('DOMContentLoaded', function() { adminToast(<?php echo json_encode(e($notice), JSON_HEX_TAG); ?>, 'success'); });</script>
      <?php endif; ?>

      <ul class="nav nav-tabs mb-3" role="tablist">
        <?php foreach (($tabs ?? []) as $tab): ?>
          <?php
            $tabId = (string)($tab['id'] ?? 'tab');
            $tabTitle = (string)($tab['title'] ?? $tabId);
            $tabUrl = (string)($tab['url'] ?? '#');
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
        <?php echo $contentHtml ?? ''; ?>
      </div>
    </div>
  </div>
</div>
