<style>
    @media (max-width: 767.98px) {
        .module-settings-title {
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
    @media (max-width: 575.98px) {
        .module-card-wrapper .d-flex {
            flex-direction: column !important;
            gap: 1rem;
        }
        .module-card-wrapper .text-end {
            text-align: left !important;
        }
        .module-card-wrapper .btn {
            width: 100%;
        }
        .text-muted.small {
            font-size: 0.8rem;
        }
        .text-muted.small span {
            display: block;
            margin-bottom: 0.25rem;
        }
    }
</style>

<?php if (!empty($title)): ?>
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 module-settings-title"><?php echo e($title); ?></h1>
  </div>
<?php endif; ?>


<?php $activeTab = isset($active_tab) ? (string)$active_tab : ''; ?>

<form method="post" action="<?php echo e($action); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
  <input type="hidden" name="active_tab" id="active_tab" value="<?php echo e($activeTab); ?>">

  <ul class="nav nav-tabs mb-3" role="tablist">
    <?php foreach ($tabs as $i => $tab): ?>
      <?php
        $tabId = isset($tab['id']) ? (string)$tab['id'] : 'tab';
        $isActive = ($activeTab !== '') ? ($activeTab === $tabId) : ($i === 0);
      ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $isActive ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-<?php echo e($tabId); ?>" type="button" role="tab" data-settings-tab="<?php echo e($tabId); ?>">
          <?php echo e($tab['title']); ?>
        </button>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="tab-content">
    <?php foreach ($tabs as $i => $tab): ?>
      <?php
        $tabId = isset($tab['id']) ? (string)$tab['id'] : 'tab';
        $isActive = ($activeTab !== '') ? ($activeTab === $tabId) : ($i === 0);
      ?>
      <div class="tab-pane fade <?php echo $isActive ? 'show active' : ''; ?>" id="tab-<?php echo e($tabId); ?>" role="tabpanel">
        <?php foreach ($tab['fields'] as $field): ?>
          <div class="mb-3">
            <?php if (!in_array($field['type'], array('toggle', 'module_cards'), true)): ?>
              <label class="form-label" for="f-<?php echo e($field['name']); ?>"><?php echo e($field['title']); ?></label>
            <?php endif; ?>

            <?php if ($field['type'] === 'textarea'): ?>
              <textarea class="form-control" id="f-<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>" rows="5"><?php
                if (is_array($field['value'])) {
                    echo e(implode("\n", $field['value']));
                } else {
                    echo e((string)$field['value']);
                }
              ?></textarea>

            <?php elseif ($field['type'] === 'number'): ?>
              <input class="form-control" type="number" id="f-<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>" value="<?php echo e((string)$field['value']); ?>">

            <?php elseif ($field['type'] === 'toggle'): ?>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="f-<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>" value="1" <?php echo !empty($field['value']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="f-<?php echo e($field['name']); ?>"><?php echo e($field['title']); ?></label>
              </div>

            <?php elseif ($field['type'] === 'select'): ?>
              <select class="form-select" id="f-<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>">
                <?php foreach ($field['options'] as $optValue => $optLabel): ?>
                  <option value="<?php echo e($optValue); ?>" <?php echo ((string)$field['value'] === (string)$optValue) ? 'selected' : ''; ?>>
                    <?php echo e($optLabel); ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <?php if ($field['path'] === 'theme.active' && !empty($field['theme_metadata']) && !empty($field['value'])): ?>
                <?php
                  $activeThemeId = (string)$field['value'];
                  $themeInfo = isset($field['theme_metadata'][$activeThemeId]) ? $field['theme_metadata'][$activeThemeId] : null;
                ?>
                <?php if ($themeInfo): ?>
                  <div class="card mt-3">
                    <div class="card-body">
                      <h6 class="card-subtitle mb-2 text-muted"><?php echo e(t('admin-settings.theme.active_theme_info')); ?></h6>
                      <div class="mb-2">
                        <strong><?php echo e(t('admin-settings.theme.name')); ?>:</strong> <?php echo e($themeInfo['name']); ?>
                      </div>
                      <?php if (!empty($themeInfo['version'])): ?>
                        <div class="mb-2">
                          <strong><?php echo e(t('admin-settings.theme.version')); ?>:</strong> <?php echo e($themeInfo['version']); ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($themeInfo['author'])): ?>
                        <div class="mb-2">
                          <strong><?php echo e(t('admin-settings.theme.author')); ?>:</strong> <?php echo e($themeInfo['author']); ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($themeInfo['description'])): ?>
                        <div class="mb-2">
                          <strong><?php echo e(t('admin-settings.theme.description')); ?>:</strong>
                          <?php echo e($themeInfo['description']); ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($themeInfo['homepage'])): ?>
                        <div class="mb-0">
                          <strong><?php echo e(t('admin.modules.homepage')); ?>:</strong>
                          <a href="<?php echo e($themeInfo['homepage']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($themeInfo['homepage']); ?></a>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endif; ?>

            <?php elseif ($field['type'] === 'module_cards'): ?>
              <label class="form-label d-block"><?php echo e($field['title']); ?></label>
              <?php
                $modules = array();
                if (isset($field['options']) && is_array($field['options'])) {
                    $modules = $field['options'];
                }

                // Separate modules into core and other groups
                $coreModules = array();
                $otherModules = array();
                foreach ($modules as $m) {
                    $type = !empty($m['type']) ? strtolower((string)$m['type']) : '';
                    if ($type === 'core') {
                        $coreModules[] = $m;
                    } else {
                        $otherModules[] = $m;
                    }
                }
              ?>

              <?php if (!empty($coreModules)): ?>
                <div class="mb-3">
                  <h6 class="mb-2"><?php echo e(t('admin-settings.modules.core_modules')); ?></h6>
                  <div class="border rounded" style="overflow:hidden;">
                    <?php foreach ($coreModules as $m): ?>
                      <?php
                        $id = isset($m['id']) ? (string)$m['id'] : '';
                        if ($id === '') {
                            continue;
                        }
                        $isEnabled = !empty($m['enabled']);
                        $canToggle = !empty($m['disableable']);
                        $canDelete = !empty($m['deletable']);
                        $hasSettings = !empty($m['has_settings']);
                        $homepage = isset($m['homepage']) ? (string)$m['homepage'] : '';
                      ?>

                      <div class="p-3 border-bottom module-card-wrapper">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                          <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                              <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" id="f-mod-<?php echo e($id); ?>" name="modules.enabled[]" value="<?php echo e($id); ?>" <?php echo $isEnabled ? 'checked' : ''; ?> <?php echo $canToggle ? '' : 'disabled'; ?>>
                              </div>
                              <div>
                                <div class="fw-semibold"><?php echo e(isset($m['title']) ? (string)$m['title'] : $id); ?></div>
                                <?php if (!empty($m['description'])): ?>
                                  <div class="text-muted small"><?php echo e((string)$m['description']); ?></div>
                                <?php endif; ?>
                              </div>
                            </div>

                            <div class="text-muted small mt-2">
                              <?php if (!empty($m['type'])): ?>
                                <span class="me-1"><strong><?php echo e(t('admin.modules.type')); ?>:</strong> <?php echo e(t('admin.modules.type.' . (string)$m['type'])); ?></span>
                              <?php endif; ?>
                              <?php if (!empty($m['version'])): ?>
                                <span class="me-1">v<?php echo e((string)$m['version']); ?></span>
                              <?php endif; ?>
                              <?php if (!empty($m['author'])): ?>
                                <span class="me-1"><strong><?php echo e(t('admin.modules.author')); ?></strong>: <?php echo e((string)$m['author']); ?></span>
                              <?php endif; ?>
                              <?php if ($homepage !== ''): ?>
                                <span class="me-1"><strong><?php echo e(t('admin.modules.homepage')); ?></strong>: <a href="<?php echo e($homepage); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($homepage); ?></a></span>
                              <?php endif; ?>
                            </div>

                            <?php if ($hasSettings && $isEnabled): ?>
                              <div class="mt-2">
                                <a class="small" href="<?php echo e(base_url('/admin/settings?tab=' . $id)); ?>"><?php echo e(t('admin.modules.settings')); ?></a>
                              </div>
                            <?php endif; ?>
                          </div>

                          <div class="text-end">
                            <button class="btn btn-sm btn-outline-danger" type="submit" name="module_delete" value="<?php echo e($id); ?>" <?php echo $canDelete ? '' : 'disabled'; ?> onclick="return confirm('<?php echo e(t('admin.modules.delete_confirm')); ?>');">
                              <?php echo e(t('admin.modules.delete')); ?>
                            </button>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if (!empty($otherModules)): ?>
                <div class="mb-3">
                  <h6 class="mb-2"><?php echo e(t('admin-settings.modules.other_modules')); ?></h6>
                  <div class="border rounded" style="overflow:hidden;">
                    <?php foreach ($otherModules as $m): ?>
                      <?php
                        $id = isset($m['id']) ? (string)$m['id'] : '';
                        if ($id === '') {
                            continue;
                        }
                        $isEnabled = !empty($m['enabled']);
                        $canToggle = !empty($m['disableable']);
                        $canDelete = !empty($m['deletable']);
                        $hasSettings = !empty($m['has_settings']);
                        $homepage = isset($m['homepage']) ? (string)$m['homepage'] : '';
                      ?>

                      <div class="p-3 border-bottom module-card-wrapper">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                          <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                              <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" id="f-mod-<?php echo e($id); ?>" name="modules.enabled[]" value="<?php echo e($id); ?>" <?php echo $isEnabled ? 'checked' : ''; ?> <?php echo $canToggle ? '' : 'disabled'; ?>>
                              </div>
                              <div>
                                <div class="fw-semibold"><?php echo e(isset($m['title']) ? (string)$m['title'] : $id); ?></div>
                                <?php if (!empty($m['description'])): ?>
                                  <div class="text-muted small"><?php echo e((string)$m['description']); ?></div>
                                <?php endif; ?>
                              </div>
                            </div>

                            <div class="text-muted small mt-2">
                              <?php if (!empty($m['type'])): ?>
                                <span class="me-1"><strong><?php echo e(t('admin.modules.type')); ?>:</strong> <?php echo e(t('admin.modules.type.' . (string)$m['type'])); ?></span>
                              <?php endif; ?>
                              <?php if (!empty($m['version'])): ?>
                                <span class="me-1">v<?php echo e((string)$m['version']); ?></span>
                              <?php endif; ?>
                              <?php if (!empty($m['author'])): ?>
                                <span class="me-1"><strong><?php echo e(t('admin.modules.author')); ?></strong>: <?php echo e((string)$m['author']); ?></span>
                              <?php endif; ?>
                              <?php if ($homepage !== ''): ?>
                                <span class="me-1"><strong><?php echo e(t('admin.modules.homepage')); ?></strong> <a href="<?php echo e($homepage); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($homepage); ?></a></span>
                              <?php endif; ?>
                            </div>

                            <?php if ($hasSettings && $isEnabled): ?>
                              <div class="mt-2">
                                <a class="small" href="<?php echo e(base_url('/admin/settings?tab=' . $id)); ?>"><?php echo e(t('admin.modules.settings')); ?></a>
                              </div>
                            <?php endif; ?>
                          </div>

                          <div class="text-end">
                            <button class="btn btn-sm btn-outline-danger" type="submit" name="module_delete" value="<?php echo e($id); ?>" <?php echo $canDelete ? '' : 'disabled'; ?> onclick="return confirm('<?php echo e(t('admin.modules.delete_confirm')); ?>');">
                              <?php echo e(t('admin.modules.delete')); ?>
                            </button>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

            <?php else: ?>
              <input class="form-control" type="text" id="f-<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>" value="<?php echo e((string)$field['value']); ?>">
            <?php endif; ?>

            <?php if (!empty($field['help'])): ?>
              <div class="form-text"><?php echo e($field['help']); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <button class="btn btn-primary" type="submit">
    <i class="bi bi-check2 me-1"></i> <?php echo t('admin.common.save'); ?>
  </button>
</form>

<script>
(function () {
  function setActiveTab(id) {
    var input = document.getElementById('active_tab');
    if (input) input.value = id || '';

    if (!id) return;

    // Persist active tab in URL so F5 can render correct tab immediately (hash alone isn't sent to server).
    try {
      var url = new URL(window.location.href);
      url.hash = 'tab-' + id;
      url.searchParams.set('section', id);
      history.replaceState(null, '', url.toString());
    } catch (e) {
      try {
        history.replaceState(null, '', '#tab-' + id);
      } catch (e2) {}
    }
  }

  // On click, persist tab + update hash.
  document.querySelectorAll('[data-settings-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setActiveTab(btn.getAttribute('data-settings-tab'));
    });
  });

  function activateFromHash() {
    if (!window.bootstrap || !bootstrap.Tab) {
      return false;
    }

    var hash = (window.location.hash || '');
    if (hash.indexOf('#tab-') !== 0) {
      return false;
    }

    var id = hash.slice('#tab-'.length);
    var safeId = (window.CSS && CSS.escape) ? CSS.escape(id) : id.replace(/[^a-zA-Z0-9_-]/g, '');
    var trigger = document.querySelector('[data-settings-tab="' + safeId + '"]');
    if (!trigger) {
      return false;
    }

    try {
      new bootstrap.Tab(trigger).show();
      setActiveTab(id);
      return true;
    } catch (e) {
      return false;
    }
  }

  // Note: Bootstrap bundle is loaded at the end of the admin layout,
  // so on hard refresh this template script may run before `bootstrap.Tab` exists.
  // Try now, and again on load.
  activateFromHash();
  window.addEventListener('load', function () {
    activateFromHash();
  });
})();
</script>
