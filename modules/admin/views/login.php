<!doctype html>
<html lang="<?php echo e(config('locale.default_language', 'en')); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo t('admin.login.title'); ?> - <?php echo e(MANTRA_PROJECT_INFO['name']); ?></title>

  <link href="/<?php echo basename(MANTRA_CORE); ?>/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    body {
      background-color: #f8f9fa;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .login-card {
      max-width: 420px;
      width: 100%;
    }

    /* Mobile responsive adjustments */
    @media (max-width: 575.98px) {
      body {
        padding: 0.5rem;
      }
      .login-card .card-body {
        padding: 1.5rem !important;
      }
      .login-card h1 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="card shadow">
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <h1 class="h3 fw-bold"><?php echo e(MANTRA_PROJECT_INFO['name']); ?></h1>
          <p class="text-muted"><?php echo t('admin.login.subtitle'); ?></p>
        </div>

        <?php if (isset($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo base_url('/admin/login'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo e(isset($csrf_token) ? $csrf_token : ''); ?>">
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

  <script src="/<?php echo basename(MANTRA_CORE); ?>/assets/bootstrap/bootstrap.min.js"></script>
</body>
</html>
