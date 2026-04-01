<article>
    <?php
    if (isset($breadcrumbs) && !empty($breadcrumbs)) {
        echo partial('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs));
    }
    ?>

    <header class="article-header">
        <h1><?php echo $this->escape($page['title']); ?></h1>
    </header>

    <div class="article-content">
        <?php echo $page['content']; ?>
    </div>
</article>
