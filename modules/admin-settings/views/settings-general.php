<form method="post" action="<?php echo e($action); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

  <?php foreach (($groups ?? array()) as $group): ?>
    <div class="card mb-4">
      <div class="card-header">
        <strong><?php echo e($group['title'] ?? ''); ?></strong>
      </div>
      <div class="card-body">
        <?php foreach (($group['fields'] ?? array()) as $field): ?>
          <?php
            $name = (string)($field['name'] ?? '');
            $type = (string)($field['type'] ?? 'text');
            $title = (string)($field['title'] ?? $name);
            $help = (string)($field['help'] ?? '');
            $value = $field['value'] ?? null;
          ?>

          <div class="mb-3">
            <?php if ($type === 'toggle'): ?>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="f-<?php echo e($name); ?>" name="<?php echo e($name); ?>" value="1" <?php echo !empty($value) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="f-<?php echo e($name); ?>"><?php echo e($title); ?></label>
              </div>

            <?php elseif ($type === 'number'): ?>
              <label class="form-label" for="f-<?php echo e($name); ?>"><?php echo e($title); ?></label>
              <input class="form-control" type="number" id="f-<?php echo e($name); ?>" name="<?php echo e($name); ?>" value="<?php echo e((string)$value); ?>">

            <?php elseif ($type === 'list'): ?>
              <label class="form-label" for="f-<?php echo e($name); ?>"><?php echo e($title); ?></label>
              <textarea class="form-control" id="f-<?php echo e($name); ?>" name="<?php echo e($name); ?>" rows="5"><?php
                if (is_array($value)) {
                    echo e(implode("\n", $value));
                } else {
                    echo e((string)$value);
                }
              ?></textarea>

            <?php elseif ($type === 'select'): ?>
              <label class="form-label" for="f-<?php echo e($name); ?>"><?php echo e($title); ?></label>
              <select class="form-select" id="f-<?php echo e($name); ?>" name="<?php echo e($name); ?>">
                <?php foreach (($field['options'] ?? array()) as $optValue => $optLabel): ?>
                  <option value="<?php echo e($optValue); ?>" <?php echo ((string)$value === (string)$optValue) ? 'selected' : ''; ?>>
                    <?php echo e($optLabel); ?>
                  </option>
                <?php endforeach; ?>
              </select>

            <?php elseif ($type === 'module_cards'): ?>
              <label class="form-label d-block"><?php echo e($title); ?></label>
              <?php
                $modules = array();
                if (isset($field['options']) && is_array($field['options'])) {
                    $modules = $field['options'];
                }
              ?>

              <div class="border rounded" style="overflow:hidden;">
                <?php foreach ($modules as $m): ?>
                  <?php
                    $id = (string)($m['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    $isEnabled = !empty($m['enabled']);
                    $canToggle = !empty($m['disableable']);
                    $canDelete = !empty($m['deletable']);
                    $hasSettings = !empty($m['has_settings']);
                    $homepage = (string)($m['homepage'] ?? '');
                  ?>

                  <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                      <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                          <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" id="f-mod-<?php echo e($id); ?>" name="modules.enabled[]" value="<?php echo e($id); ?>" <?php echo $isEnabled ? 'checked' : ''; ?> <?php echo $canToggle ? '' : 'disabled'; ?>>
                          </div>
                          <div>
                            <div class="fw-semibold"><?php echo e((string)($m['title'] ?? $id)); ?></div>
                            <?php if (!empty($m['description'])): ?>
                              <div class="text-muted small"><?php echo e((string)$m['description']); ?></div>
                            <?php endif; ?>
                          </div>
                        </div>

                        <div class="text-muted small mt-2">
                          <?php if (!empty($m['version'])): ?>
                            <span class="me-3"><strong>v</strong> <?php echo e((string)$m['version']); ?></span>
                          <?php endif; ?>
                          <?php if (!empty($m['author'])): ?>
                            <span class="me-3"><strong><?php echo e(t('admin.modules.author')); ?></strong> <?php echo e((string)$m['author']); ?></span>
                          <?php endif; ?>
                          <?php if ($homepage !== ''): ?>
                            <span class="me-3"><strong><?php echo e(t('admin.modules.homepage')); ?></strong> <a href="<?php echo e($homepage); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($homepage); ?></a></span>
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

            <?php elseif ($type === 'checklist'): ?>
              <label class="form-label d-block"><?php echo e($title); ?></label>
              <?php
                $selected = array();
                if (is_array($value)) {
                    $selected = $value;
                } elseif (is_string($value) && $value !== '') {
                    $selected = array_map('trim', explode(',', $value));
                }
                $selected = array_values(array_filter($selected, 'strlen'));
              ?>
              <div class="border rounded p-2" style="max-height: 240px; overflow:auto;">
                <?php foreach (($field['options'] ?? array()) as $optValue => $optLabel): ?>
                  <?php $isChecked = in_array((string)$optValue, array_map('strval', $selected), true); ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="f-<?php echo e($name); ?>-<?php echo e($optValue); ?>" name="<?php echo e($name); ?>[]" value="<?php echo e($optValue); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="f-<?php echo e($name); ?>-<?php echo e($optValue); ?>"><?php echo e($optLabel); ?></label>
                  </div>
                <?php endforeach; ?>
              </div>

            <?php else: ?>
              <label class="form-label" for="f-<?php echo e($name); ?>"><?php echo e($title); ?></label>
              <input class="form-control" type="text" id="f-<?php echo e($name); ?>" name="<?php echo e($name); ?>" value="<?php echo e((string)$value); ?>">
            <?php endif; ?>

            <?php if (!empty($help)): ?>
              <div class="form-text"><?php echo e($help); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <button class="btn btn-primary" type="submit">
    <i class="bi bi-check2 me-1"></i> <?php echo e(t('admin.common.save')); ?>
  </button>
</form>
