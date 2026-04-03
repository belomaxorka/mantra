<?php declare(strict_types=1);
/**
 * Paginator - Universal pagination value object
 *
 * Pure calculation — no dependencies on Router, Request, or Database.
 * Any code that knows totalItems, perPage, and currentPage can use it.
 *
 * Usage:
 *   $paginator = new Paginator($total, $perPage, $currentPage);
 *   $items = $db->query($col, $filter, array(
 *       'limit'  => $paginator->perPage(),
 *       'offset' => $paginator->offset(),
 *   ));
 *   echo partial('pagination', array('paginator' => $paginator));
 */
class Paginator {
    private $totalItems;
    private $perPage;
    private $currentPage;
    private $totalPages;

    /**
     * @param int $totalItems  Total number of items in the result set
     * @param int $perPage     Items per page (minimum 1)
     * @param int $currentPage Requested page number (clamped to valid range)
     */
    public function __construct($totalItems, $perPage, $currentPage = 1) {
        $this->totalItems = max(0, (int)$totalItems);
        $this->perPage = max(1, (int)$perPage);
        $this->totalPages = $this->totalItems > 0
            ? (int)ceil($this->totalItems / $this->perPage)
            : 1;
        $this->currentPage = max(1, min((int)$currentPage, $this->totalPages));
    }

    /**
     * Current page number (1-based, clamped to valid range).
     */
    public function currentPage() {
        return $this->currentPage;
    }

    /**
     * Total number of pages.
     */
    public function totalPages() {
        return $this->totalPages;
    }

    /**
     * Total number of items.
     */
    public function totalItems() {
        return $this->totalItems;
    }

    /**
     * Items per page.
     */
    public function perPage() {
        return $this->perPage;
    }

    /**
     * Offset for Database query (0-based).
     */
    public function offset() {
        return ($this->currentPage - 1) * $this->perPage;
    }

    /**
     * Whether there is a previous page.
     */
    public function hasPrevious() {
        return $this->currentPage > 1;
    }

    /**
     * Whether there is a next page.
     */
    public function hasNext() {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * Previous page number.
     */
    public function previousPage() {
        return max(1, $this->currentPage - 1);
    }

    /**
     * Next page number.
     */
    public function nextPage() {
        return min($this->totalPages, $this->currentPage + 1);
    }

    /**
     * Whether pagination is needed (more than one page).
     */
    public function hasPages() {
        return $this->totalPages > 1;
    }

    /**
     * Page numbers for template rendering, with '...' gaps.
     *
     * Example for page 6 of 10: [1, '...', 4, 5, 6, 7, 8, '...', 10]
     *
     * @param int $window Number of pages to show around current page
     * @return array
     */
    public function pages($window = 2) {
        if ($this->totalPages <= 1) {
            return [1];
        }

        // If total pages fit within a reasonable range, show all
        $maxWithoutGaps = ($window * 2) + 5; // window on each side + first + last + 2 dots + current
        if ($this->totalPages <= $maxWithoutGaps) {
            return range(1, $this->totalPages);
        }

        $pages = [];

        // Always include first page
        $pages[] = 1;

        // Left boundary of window
        $windowStart = max(2, $this->currentPage - $window);
        // Right boundary of window
        $windowEnd = min($this->totalPages - 1, $this->currentPage + $window);

        // Gap before window
        if ($windowStart > 2) {
            $pages[] = '...';
        }

        // Window pages
        for ($i = $windowStart; $i <= $windowEnd; $i++) {
            $pages[] = $i;
        }

        // Gap after window
        if ($windowEnd < $this->totalPages - 1) {
            $pages[] = '...';
        }

        // Always include last page
        $pages[] = $this->totalPages;

        return $pages;
    }
}
