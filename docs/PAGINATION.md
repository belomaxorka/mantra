# Pagination

Universal pagination system based on a standalone `Paginator` value object and a Bootstrap 5 partial.

## Quick Start

```php
// 1. Read page number from request
$page = max(1, (int)app()->request()->query('page', 1));
$perPage = 10;

// 2. Count total items
$total = app()->db()->count('posts', array('status' => 'published'));

// 3. Create paginator
$paginator = new Paginator($total, $perPage, $page);

// 4. Query with limit/offset
$posts = app()->db()->query('posts', array('status' => 'published'), array(
    'sort'   => 'created_at',
    'order'  => 'desc',
    'limit'  => $paginator->perPage(),
    'offset' => $paginator->offset(),
));

// 5. Pass paginator to template
app()->view()->render('list', array(
    'posts'     => $posts,
    'paginator' => $paginator,
));
```

In the template:

```php
<?php foreach ($posts as $post): ?>
    <!-- render post -->
<?php endforeach; ?>

<?php echo partial('pagination', array('paginator' => $paginator)); ?>
```

The partial renders nothing when there is only one page.

## Paginator API

`Paginator` is a pure value object with no dependencies. It only needs three numbers.

```php
$paginator = new Paginator($totalItems, $perPage, $currentPage);
```

| Method | Returns | Description |
|--------|---------|-------------|
| `currentPage()` | `int` | Current page (1-based, clamped to `[1..totalPages]`) |
| `totalPages()` | `int` | Total number of pages |
| `totalItems()` | `int` | Total number of items |
| `perPage()` | `int` | Items per page |
| `offset()` | `int` | Offset for `Database::query()` (0-based) |
| `hasPrevious()` | `bool` | Whether a previous page exists |
| `hasNext()` | `bool` | Whether a next page exists |
| `previousPage()` | `int` | Previous page number |
| `nextPage()` | `int` | Next page number |
| `hasPages()` | `bool` | `true` when `totalPages > 1` |
| `pages($window)` | `array` | Page numbers with `'...'` gaps (default window: 2) |

### Page number clamping

`currentPage` is always clamped to a valid range:

```php
new Paginator(100, 10, 999)->currentPage(); // 10 (last page)
new Paginator(100, 10, -5)->currentPage();  // 1  (first page)
new Paginator(0, 10, 1)->currentPage();     // 1  (empty set)
```

### The `pages()` method

Returns an array of page numbers for rendering, with `'...'` strings as gap markers.

```php
// Page 6 of 10:
$paginator->pages();  // [1, '...', 4, 5, 6, 7, 8, '...', 10]

// Page 1 of 5 (no gaps needed):
$paginator->pages();  // [1, 2, 3, 4, 5]

// Custom window size:
$paginator->pages(1); // [1, '...', 5, 6, 7, '...', 10]
```

## Database::count()

Count documents in a collection with optional filters (same syntax as `query()`):

```php
// Count all posts
$total = app()->db()->count('posts');

// Count published posts
$total = app()->db()->count('posts', array('status' => 'published'));

// Count users with role "editor"
$total = app()->db()->count('users', array('role' => 'editor'));
```

## Pagination Partial

`themes/default/templates/partials/pagination.php` renders Bootstrap 5 pagination.

```php
<?php echo partial('pagination', array('paginator' => $paginator)); ?>
```

Behavior:
- Renders nothing when `$paginator->hasPages()` is false (only 1 page)
- Preserves existing query parameters (`?search=foo&page=2`)
- Shows `<< Previous` / `Next >>` with disabled state
- Shows page numbers with `...` gaps

### Custom base URL

By default the partial uses the current request path. Override with `$baseUrl`:

```php
<?php echo partial('pagination', array(
    'paginator' => $paginator,
    'baseUrl'   => base_url('/blog'),
)); ?>
```

### Theme override

Themes can override the partial by placing their own file at:

```
themes/{theme}/templates/partials/pagination.php
```

## Integration Examples

### In a module controller

```php
public function listArticles() {
    $page = max(1, (int)app()->request()->query('page', 1));
    $perPage = 15;
    $filter = array('status' => 'published');

    $total = app()->db()->count('articles', $filter);
    $paginator = new Paginator($total, $perPage, $page);

    $articles = app()->db()->query('articles', $filter, array(
        'sort'   => 'created_at',
        'order'  => 'desc',
        'limit'  => $paginator->perPage(),
        'offset' => $paginator->offset(),
    ));

    app()->view()->render('module:article-list', array(
        'articles'  => $articles,
        'paginator' => $paginator,
    ));
}
```

### In an admin panel

`ContentPanel` handles pagination automatically (25 items per page). If you extend `ContentPanel`, list pagination works out of the box.

For custom admin panels, apply the same pattern:

```php
public function listItems() {
    $perPage = 25;
    $page = max(1, (int)app()->request()->query('page', 1));
    $total = app()->db()->count($this->getCollectionName());
    $paginator = new \Paginator($total, $perPage, $page);

    $items = app()->db()->query($this->getCollectionName(), array(), array(
        'sort'   => 'updated_at',
        'order'  => 'desc',
        'limit'  => $paginator->perPage(),
        'offset' => $paginator->offset(),
    ));

    $content = $this->renderView('list', array(
        'items'     => $items,
        'paginator' => $paginator,
    ));

    return $this->renderAdmin('Items', $content);
}
```

In the admin list template:

```php
<table>
    <!-- table rows -->
</table>

<?php if (isset($paginator)): ?>
    <div class="mt-3">
        <?php echo partial('pagination', array('paginator' => $paginator)); ?>
    </div>
<?php endif; ?>
```

### For search results (future)

```php
$results = $searchEngine->search($query);
$paginator = new Paginator(count($results), $perPage, $page);
$pageResults = array_slice($results, $paginator->offset(), $paginator->perPage());
```

### For API responses (future)

```php
$response = array(
    'data' => $items,
    'meta' => array(
        'current_page' => $paginator->currentPage(),
        'per_page'     => $paginator->perPage(),
        'total'        => $paginator->totalItems(),
        'total_pages'  => $paginator->totalPages(),
    ),
);
```

## Configuration

Public pages (home, blog) use the `content.posts_per_page` setting from admin Settings > Content. Default: `10`.

Admin panels use a fixed value of `25` items per page.
