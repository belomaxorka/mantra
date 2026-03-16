<div class="products-list">
    <h1><?php echo isset($category) ? $this->escape(ucfirst($category)) . ' ' : ''; ?>Products</h1>

    <?php if (!empty($products)): ?>
        <div class="row">
            <?php foreach ($products as $product): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <?php if (isset($product['images']) && !empty($product['images'][0])): ?>
                            <img src="<?php echo $this->escape($product['images'][0]); ?>"
                                 class="card-img-top"
                                 alt="<?php echo $this->escape($product['title']); ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="<?php echo base_url('/product/' . $product['slug']); ?>">
                                    <?php echo $this->escape($product['title']); ?>
                                </a>
                            </h5>
                            <?php if (isset($product['price'])): ?>
                                <p class="card-text">
                                    <strong>$<?php echo number_format($product['price'], 2); ?></strong>
                                </p>
                            <?php endif; ?>
                            <?php if (isset($product['content'])): ?>
                                <p class="card-text">
                                    <?php echo $this->escape(substr(strip_tags($product['content']), 0, 100)); ?>...
                                </p>
                            <?php endif; ?>
                            <a href="<?php echo base_url('/product/' . $product['slug']); ?>"
                               class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <p class="mb-0">No products found.</p>
        </div>
    <?php endif; ?>
</div>
