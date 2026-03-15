<div class="d-flex align-items-center justify-content-between mb-4">
  <h1 class="h3 mb-0"><?php echo e($title ?? 'Settings'); ?></h1>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo e($error); ?></div>
<?php endif; ?>

<?php if (!empty($notice)): ?>
  <div class="alert alert-success"><?php echo e($notice); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo e($action); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

  <ul class="nav nav-tabs mb-3" role="tablist">
    <?php foreach ($tabs as $i => $tab): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $i === 0 ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-<?php echo e($tab['id']); ?>" type="button" role="tab">
          <?php echo e($tab['title']); ?>
        </button>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="tab-content">
    <?php foreach ($tabs as $i => $tab): ?>
      <div class="tab-pane fade <?php echo $i === 0 ? 'show active' : ''; ?>" id="tab-<?php echo e($tab['id']); ?>" role="tabpanel">
        <?php foreach ($tab['fields'] as $field): ?>
          <div class="mb-3">
            <label class="form-label" for="f-<?php echo e($field['name']); ?>"><?php echo e($field['title']); ?></label>

            <?php if ($field['type'] === 'textarea'): ?>
              <textarea class="form-control" id="f-<?php echo e($field['name']); ?>" name="<?php echo e($field['name']); ?>" rows="5"><?php echo e((string)$field['value']); ?></textarea>

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
    <i class="bi bi-check2 me-1"></i> Save
  </button>
</form>
