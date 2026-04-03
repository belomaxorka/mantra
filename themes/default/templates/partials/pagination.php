<?php
/**
 * Pagination partial — Bootstrap 5
 *
 * Expects: $paginator (Paginator instance)
 * Optional: $baseUrl (string, defaults to current path)
 *
 * Usage: <?php echo partial('pagination', array('paginator' => $paginator)); ?>
 */
if (!isset($paginator) || !$paginator->hasPages()) {
    return;
}

// Build base URL preserving existing query parameters
$currentParams = $_GET;
unset($currentParams['page']);
$queryPrefix = !empty($currentParams) ? '?' . http_build_query($currentParams) . '&' : '?';
$base = $baseUrl ?? strtok($_SERVER['REQUEST_URI'], '?');
?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center mb-0">
    <li class="page-item <?php echo $paginator->hasPrevious() ? '' : 'disabled'; ?>">
      <a class="page-link" href="<?php echo e($base . $queryPrefix . 'page=' . $paginator->previousPage()); ?>">&laquo;</a>
    </li>

    <?php foreach ($paginator->pages() as $p): ?>
      <?php if ($p === '...'): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
      <?php else: ?>
        <li class="page-item <?php echo $p === $paginator->currentPage() ? 'active' : ''; ?>">
          <a class="page-link" href="<?php echo e($base . $queryPrefix . 'page=' . $p); ?>"><?php echo $p; ?></a>
        </li>
      <?php endif; ?>
    <?php endforeach; ?>

    <li class="page-item <?php echo $paginator->hasNext() ? '' : 'disabled'; ?>">
      <a class="page-link" href="<?php echo e($base . $queryPrefix . 'page=' . $paginator->nextPage()); ?>">&raquo;</a>
    </li>
  </ul>
</nav>
