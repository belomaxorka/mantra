<?php
/**
 * Bench — stress-test and benchmark CMS components.
 *
 * Measures throughput and latency of Database, FileIO, JsonCodec,
 * SchemaValidator, Router pattern matching, and collection queries.
 *
 * Usage:
 *   php tools/bench.php                   # run all benchmarks (default iterations)
 *   php tools/bench.php --iterations=500  # custom iteration count
 *   php tools/bench.php --suite=db        # run only one suite
 *   php tools/bench.php --suite=db,io     # run several suites
 *   php tools/bench.php --list            # list available suites
 *   php tools/bench.php --verbose         # show per-operation timings
 *
 * Suites: db, io, json, schema, query, router
 *
 * All generated data is written to a temporary "_bench" collection
 * and removed automatically on completion (or Ctrl-C).
 */

// ── Bootstrap ───────────────────────────────────────────────────────────────

chdir(dirname(__DIR__));
require 'core/bootstrap.php';

// ── CLI arguments ───────────────────────────────────────────────────────────

$opts = getopt('', array('iterations::', 'suite::', 'list', 'verbose'));

$defaultIterations = 200;
$iterations = isset($opts['iterations']) && $opts['iterations'] !== false
    ? max(1, (int)$opts['iterations'])
    : $defaultIterations;

$verbose = isset($opts['verbose']);

$allSuites = array('db', 'io', 'json', 'schema', 'query', 'router');

if (isset($opts['list'])) {
    echo "Available benchmark suites:\n";
    foreach ($allSuites as $s) {
        echo "  - {$s}\n";
    }
    exit(0);
}

$selectedSuites = $allSuites;
if (isset($opts['suite']) && $opts['suite'] !== false) {
    $selectedSuites = array_intersect(
        array_map('trim', explode(',', $opts['suite'])),
        $allSuites,
    );
    if (empty($selectedSuites)) {
        echo "Error: no valid suites selected. Use --list to see available suites.\n";
        exit(1);
    }
}

// ── Constants ───────────────────────────────────────────────────────────────

define('BENCH_COLLECTION', '_bench');
define('BENCH_DIR', MANTRA_CONTENT . '/' . BENCH_COLLECTION);

// ── Helpers ─────────────────────────────────────────────────────────────────

/**
 * High-resolution timer (microseconds).
 */
function bench_now()
{
    return hrtime(true); // nanoseconds
}

/**
 * Nanoseconds → milliseconds.
 */
function bench_ns_to_ms($ns)
{
    return $ns / 1_000_000;
}

/**
 * Run a callable $n times, collect per-iteration timings.
 *
 * @param string   $label   Human-readable name
 * @param int      $n       Iterations
 * @param callable $fn      fn(int $i): void
 * @param callable|null $setup   Optional setup before the timed loop
 * @param callable|null $teardown Optional cleanup after the timed loop
 * @return array   Stats array
 */
function bench_run($label, $n, $fn, $setup = null, $teardown = null)
{
    if ($setup) {
        $setup();
    }

    $timings = array();

    for ($i = 0; $i < $n; $i++) {
        $start = bench_now();
        $fn($i);
        $timings[] = bench_now() - $start;
    }

    if ($teardown) {
        $teardown();
    }

    sort($timings);

    $totalNs = array_sum($timings);
    $totalMs = bench_ns_to_ms($totalNs);
    $avgNs = $totalNs / $n;
    $minNs = $timings[0];
    $maxNs = $timings[$n - 1];
    $medianNs = $n % 2 === 0
        ? ($timings[$n / 2 - 1] + $timings[$n / 2]) / 2
        : $timings[(int)floor($n / 2)];
    $p95idx = (int)floor($n * 0.95);
    $p95Ns = $timings[min($p95idx, $n - 1)];

    $opsPerSec = $totalMs > 0 ? ($n / ($totalMs / 1000)) : 0;

    return array(
        'label' => $label,
        'n' => $n,
        'total_ms' => $totalMs,
        'avg_ms' => bench_ns_to_ms($avgNs),
        'min_ms' => bench_ns_to_ms($minNs),
        'max_ms' => bench_ns_to_ms($maxNs),
        'median_ms' => bench_ns_to_ms($medianNs),
        'p95_ms' => bench_ns_to_ms($p95Ns),
        'ops_sec' => $opsPerSec,
        'timings_ns' => $timings,
    );
}

/**
 * Print a stats table for a group of results.
 */
function bench_table($title, $results)
{
    echo "\n  {$title}\n";
    echo '  ' . str_repeat('-', 96) . "\n";
    echo sprintf(
        "  %-30s %8s %10s %10s %10s %10s %12s\n",
        'Operation',
        'Ops',
        'Avg (ms)',
        'Med (ms)',
        'P95 (ms)',
        'Max (ms)',
        'Throughput',
    );
    echo '  ' . str_repeat('-', 96) . "\n";

    foreach ($results as $r) {
        echo sprintf(
            "  %-30s %8d %10.3f %10.3f %10.3f %10.3f %10.0f op/s\n",
            $r['label'],
            $r['n'],
            $r['avg_ms'],
            $r['median_ms'],
            $r['p95_ms'],
            $r['max_ms'],
            $r['ops_sec'],
        );
    }

    echo '  ' . str_repeat('-', 96) . "\n";
}

/**
 * Print verbose per-iteration timings.
 */
function bench_verbose($results)
{
    foreach ($results as $r) {
        echo "\n  [verbose] {$r['label']}: ";
        $samples = array_slice($r['timings_ns'], 0, 20);
        $strs = array_map(function ($ns) {
            return sprintf('%.2fms', bench_ns_to_ms($ns));
        }, $samples);
        echo implode(', ', $strs);
        if ($r['n'] > 20) {
            echo ' ... (' . ($r['n'] - 20) . ' more)';
        }
        echo "\n";
    }
}

/**
 * Generate a sample document of approximate $sizeKb kilobytes.
 */
function bench_document($sizeKb = 2)
{
    $body = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', (int)($sizeKb * 18));
    return array(
        'title' => 'Bench document ' . bin2hex(random_bytes(4)),
        'slug' => 'bench-' . bin2hex(random_bytes(6)),
        'content' => $body,
        'excerpt' => 'Benchmark excerpt text for testing.',
        'status' => 'draft',
        'category' => '',
        'image' => '',
        'author' => 'bench',
        'author_id' => 'bench-user',
        '_bench' => true,
    );
}

/**
 * Clean up all bench data.
 */
function bench_cleanup()
{
    if (!is_dir(BENCH_DIR)) {
        return 0;
    }

    $count = 0;
    foreach (glob(BENCH_DIR . '/*.json') as $file) {
        @unlink($file);
        $lockFile = $file . '.lock';
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
        $count++;
    }

    // Also clean .lock files without matching .json
    foreach (glob(BENCH_DIR . '/*.lock') as $file) {
        @unlink($file);
    }

    @rmdir(BENCH_DIR);
    return $count;
}

// Always clean up on exit
register_shutdown_function('bench_cleanup');

// ── Suite: Database CRUD ────────────────────────────────────────────────────

function suite_db($n)
{
    $db = app()->db();
    $ids = array();
    $results = array();

    // Warm up — ensure directory exists
    @mkdir(BENCH_DIR, 0o755, true);

    // --- Write ---
    $results[] = bench_run('db::write', $n, function ($i) use ($db, &$ids) {
        $doc = bench_document(2);
        $id = 'bench-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
        $ids[] = $id;
        $db->write(BENCH_COLLECTION, $id, $doc);
    });

    // --- Read single ---
    $results[] = bench_run('db::read (single)', $n, function ($i) use ($db, $ids) {
        $db->read(BENCH_COLLECTION, $ids[$i]);
    });

    // --- Exists check ---
    $results[] = bench_run('db::exists', $n, function ($i) use ($db, $ids) {
        $db->exists(BENCH_COLLECTION, $ids[$i]);
    });

    // --- Count (fast path, no filters) ---
    $countN = min($n, 50);
    $results[] = bench_run('db::count (no filter)', $countN, function () use ($db) {
        $db->count(BENCH_COLLECTION);
    });

    // --- Count (with filter) ---
    $results[] = bench_run('db::count (filtered)', $countN, function () use ($db) {
        $db->count(BENCH_COLLECTION, array('status' => 'draft'));
    });

    // --- listIds ---
    $results[] = bench_run('db::listIds', $countN, function () use ($db) {
        $db->listIds(BENCH_COLLECTION);
    });

    // --- Delete ---
    $results[] = bench_run('db::delete', $n, function ($i) use ($db, $ids) {
        $db->delete(BENCH_COLLECTION, $ids[$i]);
    });

    return $results;
}

// ── Suite: FileIO ───────────────────────────────────────────────────────────

function suite_io($n)
{
    $results = array();

    @mkdir(BENCH_DIR, 0o755, true);
    $path = BENCH_DIR . '/_io_test.json';

    $smallPayload = json_encode(array('key' => 'value', 'number' => 42), JSON_PRETTY_PRINT);
    $largePayload = json_encode(array('data' => str_repeat('x', 50000)), JSON_PRETTY_PRINT);

    // --- Atomic write (small) ---
    $results[] = bench_run('FileIO::writeAtomic (small)', $n, function () use ($path, $smallPayload) {
        Storage\FileIO::writeAtomic($path, $smallPayload);
    });

    // --- Locked read (small) ---
    Storage\FileIO::writeAtomic($path, $smallPayload);
    $results[] = bench_run('FileIO::readLocked (small)', $n, function () use ($path) {
        Storage\FileIO::readLocked($path);
    });

    // --- Atomic write (50 KB) ---
    $results[] = bench_run('FileIO::writeAtomic (50 KB)', $n, function () use ($path, $largePayload) {
        Storage\FileIO::writeAtomic($path, $largePayload);
    });

    // --- Locked read (50 KB) ---
    Storage\FileIO::writeAtomic($path, $largePayload);
    $results[] = bench_run('FileIO::readLocked (50 KB)', $n, function () use ($path) {
        Storage\FileIO::readLocked($path);
    });

    // Cleanup
    @unlink($path);
    @unlink($path . '.lock');

    return $results;
}

// ── Suite: JsonCodec ────────────────────────────────────────────────────────

function suite_json($n)
{
    $results = array();

    $small = array('title' => 'Hello', 'status' => 'published', 'order' => 1);
    $medium = bench_document(5);
    $large = bench_document(50);

    $smallJson = JsonCodec::encode($small);
    $mediumJson = JsonCodec::encode($medium);
    $largeJson = JsonCodec::encode($large);

    // --- Encode ---
    $results[] = bench_run('JsonCodec::encode (small)', $n, function () use ($small) {
        JsonCodec::encode($small);
    });

    $results[] = bench_run('JsonCodec::encode (5 KB)', $n, function () use ($medium) {
        JsonCodec::encode($medium);
    });

    $encLargeN = min($n, 100);
    $results[] = bench_run('JsonCodec::encode (50 KB)', $encLargeN, function () use ($large) {
        JsonCodec::encode($large);
    });

    // --- Decode ---
    $results[] = bench_run('JsonCodec::decode (small)', $n, function () use ($smallJson) {
        JsonCodec::decode($smallJson);
    });

    $results[] = bench_run('JsonCodec::decode (5 KB)', $n, function () use ($mediumJson) {
        JsonCodec::decode($mediumJson);
    });

    $results[] = bench_run('JsonCodec::decode (50 KB)', $encLargeN, function () use ($largeJson) {
        JsonCodec::decode($largeJson);
    });

    return $results;
}

// ── Suite: SchemaValidator ──────────────────────────────────────────────────

function suite_schema($n)
{
    $results = array();

    $schema = array(
        'version' => 1,
        'fields' => array(
            'title' => array('type' => 'string', 'required' => true, 'maxLength' => 255),
            'slug' => array('type' => 'string', 'required' => true, 'pattern' => '/^[a-z0-9-]+$/'),
            'status' => array('type' => 'string', 'values' => array('draft', 'published')),
            'order' => array('type' => 'integer', 'min' => 0, 'max' => 9999),
            'email' => array('type' => 'email'),
        ),
    );

    $validData = array(
        'title' => 'Test post for benchmarking',
        'slug' => 'test-post-for-benchmarking',
        'status' => 'published',
        'order' => 42,
        'email' => 'test@example.com',
    );

    $invalidData = array(
        'title' => '',
        'slug' => 'INVALID SLUG!!!',
        'status' => 'unknown',
        'order' => -5,
        'email' => 'not-an-email',
    );

    // --- Validate (valid data) ---
    $results[] = bench_run('SchemaValidator (valid)', $n, function () use ($validData, $schema) {
        SchemaValidator::validate($validData, $schema);
    });

    // --- Validate (invalid data) ---
    $results[] = bench_run('SchemaValidator (invalid)', $n, function () use ($invalidData, $schema) {
        SchemaValidator::validate($invalidData, $schema);
    });

    // --- Sanitize ---
    $dirtyData = array(
        'title' => "  Hello \0world  ",
        'content' => "<p>Some \0 text</p>",
        'nested' => array('key' => "  value \0 "),
    );

    $results[] = bench_run('SchemaValidator::sanitize', $n, function () use ($dirtyData) {
        SchemaValidator::sanitize($dirtyData);
    });

    return $results;
}

// ── Suite: Query / Collection ───────────────────────────────────────────────

function suite_query($n)
{
    $db = app()->db();
    $results = array();

    // Seed a collection with documents for querying
    @mkdir(BENCH_DIR, 0o755, true);
    $seedCount = min($n, 500);
    $statuses = array('draft', 'published');

    echo "    preparing {$seedCount} documents... ";
    for ($i = 0; $i < $seedCount; $i++) {
        $doc = bench_document(1);
        $doc['status'] = $statuses[$i % 2];
        $doc['order'] = $i;
        $id = 'qbench-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
        $db->write(BENCH_COLLECTION, $id, $doc);
    }
    echo "done.\n";

    $queryN = min($n, 50);

    // --- Read collection (all) ---
    // Invalidate cache before each read to measure real I/O
    $results[] = bench_run('query: readAll (' . $seedCount . ' docs)', $queryN, function () use ($db) {
        // Force cache invalidation by writing+deleting a throwaway doc
        $db->write(BENCH_COLLECTION, '_cache_bust', array('_bench' => true));
        $db->delete(BENCH_COLLECTION, '_cache_bust');
        $db->read(BENCH_COLLECTION);
    });

    // --- Query with filter ---
    $results[] = bench_run('query: filter status (' . $seedCount . ')', $queryN, function () use ($db) {
        $db->write(BENCH_COLLECTION, '_cache_bust', array('_bench' => true));
        $db->delete(BENCH_COLLECTION, '_cache_bust');
        $db->query(BENCH_COLLECTION, array('status' => 'published'));
    });

    // --- Query with sort ---
    $results[] = bench_run('query: sort by order (' . $seedCount . ')', $queryN, function () use ($db) {
        $db->write(BENCH_COLLECTION, '_cache_bust', array('_bench' => true));
        $db->delete(BENCH_COLLECTION, '_cache_bust');
        $db->query(BENCH_COLLECTION, array(), array('sort' => 'order', 'order' => 'desc'));
    });

    // --- Query with filter + sort + limit ---
    $results[] = bench_run('query: filter+sort+limit', $queryN, function () use ($db) {
        $db->write(BENCH_COLLECTION, '_cache_bust', array('_bench' => true));
        $db->delete(BENCH_COLLECTION, '_cache_bust');
        $db->query(BENCH_COLLECTION, array('status' => 'published'), array(
            'sort' => 'order',
            'order' => 'desc',
            'limit' => 25,
            'offset' => 0,
        ));
    });

    // --- Cached read (hot path — no invalidation) ---
    // First call loads into cache, subsequent calls hit cache
    $db->read(BENCH_COLLECTION);
    $results[] = bench_run('query: cached readAll', $n, function () use ($db) {
        $db->read(BENCH_COLLECTION);
    });

    $results[] = bench_run('query: cached filter+sort', $n, function () use ($db) {
        $db->query(BENCH_COLLECTION, array('status' => 'published'), array(
            'sort' => 'order',
            'order' => 'desc',
        ));
    });

    return $results;
}

// ── Suite: Router pattern matching ──────────────────────────────────────────

function suite_router($n)
{
    $results = array();

    // We benchmark the pattern matching logic directly (no dispatch side effects).
    // Use reflection to call the private matchPattern method.
    $router = new Router();
    $ref = new ReflectionMethod($router, 'matchPattern');
    $ref->setAccessible(true);

    // Register a realistic set of routes
    $patterns = array(
        '/',
        '/blog',
        '/post/{slug}',
        '/admin',
        '/admin/posts',
        '/admin/posts/new',
        '/admin/posts/edit/{id}',
        '/admin/posts/delete/{id}',
        '/admin/pages',
        '/admin/pages/new',
        '/admin/pages/edit/{id}',
        '/admin/users',
        '/admin/users/edit/{id}',
        '/admin/settings',
        '/admin/uploads',
        '/admin/hooks',
        '/admin/permissions',
        '/api/posts',
        '/api/posts/{id}',
        '/api/categories/{slug}/posts',
        '/{slug}',
    );

    $testUris = array(
        '/',
        '/blog',
        '/post/hello-world',
        '/admin/posts/edit/abc123',
        '/api/categories/news/posts',
        '/about-us',
        '/nonexistent/deep/path',
    );

    // --- Match against all patterns (worst case: last or miss) ---
    $results[] = bench_run('router: matchPattern (hit first)', $n, function () use ($ref, $router) {
        $ref->invoke($router, '/', '/');
    });

    $results[] = bench_run('router: matchPattern (hit param)', $n, function () use ($ref, $router) {
        $ref->invoke($router, '/post/{slug}', '/post/hello-world');
    });

    $results[] = bench_run('router: matchPattern (hit 2 params)', $n, function () use ($ref, $router) {
        $ref->invoke($router, '/api/categories/{slug}/posts', '/api/categories/news/posts');
    });

    $results[] = bench_run('router: matchPattern (miss)', $n, function () use ($ref, $router) {
        $ref->invoke($router, '/admin/posts/edit/{id}', '/nonexistent/deep/path');
    });

    // --- Full route scan (find matching route among many) ---
    $results[] = bench_run('router: full scan (' . count($patterns) . ' routes)', $n, function () use ($ref, $router, $patterns, $testUris) {
        $uri = $testUris[array_rand($testUris)];
        foreach ($patterns as $pattern) {
            $match = $ref->invoke($router, $pattern, $uri);
            if ($match !== false) {
                break;
            }
        }
    });

    // --- Middleware matching ---
    $refMw = new ReflectionMethod($router, 'middlewareMatches');
    $refMw->setAccessible(true);

    $results[] = bench_run('router: middlewareMatches', $n, function () use ($refMw, $router) {
        $refMw->invoke($router, '/admin/*', '/admin/posts/edit/abc123');
        $refMw->invoke($router, '*', '/anything');
        $refMw->invoke($router, '/login', '/login');
        $refMw->invoke($router, '/admin/*', '/blog');
    });

    return $results;
}

// ── Main ────────────────────────────────────────────────────────────────────

echo "Mantra CMS — Benchmark Tool\n";
echo str_repeat('=', 100) . "\n";
echo "Iterations: {$iterations}  |  Suites: " . implode(', ', $selectedSuites) . "\n";
echo "PHP " . PHP_VERSION . '  |  ' . PHP_OS . "\n";
echo str_repeat('=', 100) . "\n";

$allResults = array();
$totalStart = bench_now();

$suiteMap = array(
    'db' => array('Database CRUD', 'suite_db'),
    'io' => array('FileIO (atomic writes / locked reads)', 'suite_io'),
    'json' => array('JsonCodec (encode / decode)', 'suite_json'),
    'schema' => array('SchemaValidator', 'suite_schema'),
    'query' => array('Collection queries', 'suite_query'),
    'router' => array('Router pattern matching', 'suite_router'),
);

foreach ($selectedSuites as $suite) {
    [$title, $fn] = $suiteMap[$suite];
    echo "\n[{$suite}] Running: {$title}...\n";

    $suiteStart = bench_now();
    $results = $fn($iterations);
    $suiteMs = bench_ns_to_ms(bench_now() - $suiteStart);

    bench_table($title . sprintf(' (%.0f ms total)', $suiteMs), $results);

    if ($verbose) {
        bench_verbose($results);
    }

    $allResults[$suite] = $results;

    // Clean up between suites
    bench_cleanup();
}

$totalMs = bench_ns_to_ms(bench_now() - $totalStart);

echo "\n" . str_repeat('=', 100) . "\n";
echo sprintf("All done in %.0f ms.\n", $totalMs);

// ── Summary: bottleneck detection ───────────────────────────────────────────

echo "\nBottleneck analysis:\n";

$all = array();
foreach ($allResults as $suiteResults) {
    foreach ($suiteResults as $r) {
        $all[] = $r;
    }
}

usort($all, function ($a, $b) {
    return $b['avg_ms'] <=> $a['avg_ms'];
});

$top = array_slice($all, 0, 5);
echo "  Slowest operations (by avg latency):\n";
foreach ($top as $i => $r) {
    echo sprintf(
        "    %d. %-35s %8.3f ms avg  |  %8.3f ms p95\n",
        $i + 1,
        $r['label'],
        $r['avg_ms'],
        $r['p95_ms'],
    );
}

usort($all, function ($a, $b) {
    return $b['p95_ms'] <=> $a['p95_ms'];
});

$topP95 = array_slice($all, 0, 5);
echo "\n  Highest tail latency (p95):\n";
foreach ($topP95 as $i => $r) {
    echo sprintf(
        "    %d. %-35s %8.3f ms p95  |  %8.3f ms max\n",
        $i + 1,
        $r['label'],
        $r['p95_ms'],
        $r['max_ms'],
    );
}

echo "\nDone.\n";
