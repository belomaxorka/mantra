<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?php echo e(app()->auth()->generateCsrfToken()); ?>">
<meta name="base-url" content="<?php echo e(rtrim(config('site.url', ''), '/')); ?>">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?php echo $this->moduleAsset('libs/bootstrap/bootstrap.min.css'); ?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?php echo $this->moduleAsset('css/admin.css'); ?>" rel="stylesheet">
<script src="<?php echo $this->moduleAsset('libs/jquery/jquery.min.js'); ?>"></script>
<script src="<?php echo $this->moduleAsset('js/admin-ajax.js'); ?>"></script>
