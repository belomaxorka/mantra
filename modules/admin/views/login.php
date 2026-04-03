<!doctype html>
<html lang="<?php echo e(config('locale.default_language', 'en')); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo t('admin.login.title'); ?> - <?php echo e(MANTRA_PROJECT_INFO['name']); ?></title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?php echo $this->moduleAsset('admin', 'bootstrap/bootstrap.min.css'); ?>" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $this->moduleAsset('admin', 'css/admin.css'); ?>" rel="stylesheet">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="card">
      <div class="card-body">
        <div class="text-center mb-4">
          <h1 class="login-brand"><?php echo e(MANTRA_PROJECT_INFO['name']); ?></h1>
          <p class="login-subtitle"><?php echo t('admin.login.subtitle'); ?></p>
        </div>

        <?php if (isset($error)): ?>
          <div class="login-error">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e($error); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo base_url('/admin/login'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token ?? ''); ?>">
          <div class="mb-3">
            <label for="username" class="form-label"><?php echo t('admin.login.username'); ?></label>
            <input type="text" class="form-control" id="username" name="username" required autofocus>
          </div>

          <div class="mb-4">
            <label for="password" class="form-label"><?php echo t('admin.login.password'); ?></label>
            <input type="password" class="form-control" id="password" name="password" required>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i><?php echo t('admin.login.sign_in'); ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <script src="<?php echo $this->moduleAsset('admin', 'bootstrap/bootstrap.min.js'); ?>"></script>
</body>
</html>
