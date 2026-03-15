<!DOCTYPE html>
<html lang="<?php echo isset($lang) ? $lang : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $this->escape($title) : 'Mantra CMS'; ?></title>
    <link rel="stylesheet" href="<?php echo $this->asset('css/style.css'); ?>">
</head>
<body>
    <header>
        <nav>
            <a href="<?php echo base_url(); ?>">Home</a>
            <a href="<?php echo base_url('/admin'); ?>">Admin</a>
        </nav>
    </header>
    
    <main>
        <?php echo isset($content) ? $content : ''; ?>
    </main>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Mantra CMS</p>
    </footer>
</body>
</html>
