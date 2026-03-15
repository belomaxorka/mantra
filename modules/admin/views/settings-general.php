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
    <i class="bi bi-check2 me-1"></i> <?php echo e(t('admin.settings.save')); ?>
  </button>
</form>
