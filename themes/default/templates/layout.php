<!DOCTYPE html>
<html lang="<?php echo isset($lang) ? $lang : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $this->escape($title) : 'Mantra CMS'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $this->asset('css/style.css'); ?>">
    <?php
    // Hook: allow modules to add content to <head>
    $app = Application::getInstance();
    echo $app->hooks()->fire('theme.head', '');
    ?>
</head>
<body>
    <?php
    // Hook: allow modules to add content after <body>
    echo $app->hooks()->fire('theme.body.start', '');
    ?>

    <header class="bg-dark text-white">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="<?php echo base_url(); ?>">Mantra CMS</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_url(); ?>">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_url('/admin'); ?>">Admin</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container my-5">
        <?php echo isset($content) ? $content : ''; ?>
    </main>

    <footer class="bg-light text-center py-4 mt-5">
        <div class="container">
            <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> Mantra CMS</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    // Hook: allow modules to add scripts before </body>
    echo $app->hooks()->fire('theme.footer', '');
    ?>

    <?php
    // Hook: allow modules to add content before </body>
    echo $app->hooks()->fire('theme.body.end', '');
    ?>
</body>
</html>
