<article class="product">
    <?php
    // Display breadcrumbs if available (provided by SEO module)
    if (isset($breadcrumbs) && !empty($breadcrumbs)) {
        echo widget('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs));
    }
    ?>

    <div class="row">
        <div class="col-md-6">
            <?php if (isset($product['images']) && !empty($product['images'])): ?>
                <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php foreach ($product['images'] as $index => $image): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo $this->escape($image); ?>"
                                     class="d-block w-100"
                                     alt="<?php echo $this->escape($product['title']); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($product['images']) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <h1><?php echo $this->escape($product['title']); ?></h1>

            <?php if (isset($formatted_price)): ?>
                <p class="h3 text-primary mb-3"><?php echo $this->escape($formatted_price); ?></p>
            <?php endif; ?>

            <?php if (isset($stock_status)): ?>
                <p class="<?php echo $this->escape($stock_class); ?>">
                    <strong><?php echo $this->escape($stock_status); ?></strong>
                </p>
            <?php endif; ?>

            <?php if (isset($product['sku'])): ?>
                <p class="text-muted">SKU: <?php echo $this->escape($product['sku']); ?></p>
            <?php endif; ?>

            <div class="content my-4">
                <?php echo $product['content']; ?>
            </div>

            <button class="btn btn-success btn-lg">Add to Cart</button>
        </div>
    </div>
</article>
