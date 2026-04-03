# Bench — Performance Benchmark

Stress-test and benchmark core CMS components. Measures throughput (op/s) and latency distribution (avg, median, p95, max) for Database, FileIO, JsonCodec, SchemaValidator, collection queries, and Router.

## Quick Start

```bash
# Run all benchmark suites with default 200 iterations
php tools/bench.php

# More iterations for stable results
php tools/bench.php --iterations=500

# Run specific suites
php tools/bench.php --suite=db,query

# List available suites
php tools/bench.php --list

# Show per-iteration timings (first 20 samples per operation)
php tools/bench.php --verbose
```

## CLI Options

| Option | Default | Description |
|--------|---------|-------------|
| `--iterations=N` | `200` | Number of iterations per operation |
| `--suite=name,...` | all | Comma-separated list of suites to run |
| `--list` | — | Print available suite names and exit |
| `--verbose` | off | Show per-iteration timing samples after each suite |

## Benchmark Suites

### `db` — Database CRUD

End-to-end `Database` operations including schema validation, sanitization, timestamps, and atomic file I/O.

| Operation | Description |
|-----------|-------------|
| `db::write` | Create a ~2 KB document (full pipeline: sanitize, validate, write) |
| `db::read (single)` | Read one document by ID (file read + JSON decode + normalization) |
| `db::exists` | Check document existence (`file_exists` call) |
| `db::count (no filter)` | Count documents via `glob()` — fast path, no file reads |
| `db::count (filtered)` | Count with equality filter — reads all documents |
| `db::listIds` | List document IDs without reading contents |
| `db::delete` | Delete a document (locked file removal) |

### `io` — FileIO

Low-level atomic file operations with locking. Tests two payload sizes to show how I/O scales.

| Operation | Description |
|-----------|-------------|
| `FileIO::writeAtomic (small)` | Write ~50 bytes with exclusive lock + temp-file + rename |
| `FileIO::readLocked (small)` | Read ~50 bytes with shared lock |
| `FileIO::writeAtomic (50 KB)` | Write ~50 KB payload |
| `FileIO::readLocked (50 KB)` | Read ~50 KB payload |

### `json` — JsonCodec

Pure in-memory JSON encode/decode. No file I/O — isolates serialization cost.

| Operation | Description |
|-----------|-------------|
| `JsonCodec::encode (small)` | Encode a 3-field array |
| `JsonCodec::encode (5 KB)` | Encode a typical post document |
| `JsonCodec::encode (50 KB)` | Encode a large document |
| `JsonCodec::decode (small)` | Decode a small JSON string |
| `JsonCodec::decode (5 KB)` | Decode a medium JSON string |
| `JsonCodec::decode (50 KB)` | Decode a large JSON string |

### `schema` — SchemaValidator

Validation and sanitization without file I/O. Uses a 5-field schema with type checks, required fields, patterns, enums, and range constraints.

| Operation | Description |
|-----------|-------------|
| `SchemaValidator (valid)` | Validate data that passes all rules |
| `SchemaValidator (invalid)` | Validate data that fails every rule |
| `SchemaValidator::sanitize` | Trim strings, strip null bytes, recurse into nested arrays |

### `query` — Collection Queries

Full read-collection pipeline: glob files, read each, decode JSON, normalize schemas, then filter/sort in memory. Tests both cold (uncached) and hot (cached) paths.

Before running, seeds `min(N, 500)` documents into a temporary collection.

| Operation | Description |
|-----------|-------------|
| `query: readAll (N docs)` | Read entire collection from disk (cache invalidated each iteration) |
| `query: filter status (N)` | Read all + filter by `status` field |
| `query: sort by order (N)` | Read all + sort by `order` field descending |
| `query: filter+sort+limit` | Read all + filter + sort + limit 25 + offset 0 |
| `query: cached readAll` | Read collection from in-request cache (no disk I/O) |
| `query: cached filter+sort` | Filter + sort on cached collection |

### `router` — Router Pattern Matching

Regex-based route matching and middleware pattern checks. Uses reflection to call private methods directly — no HTTP dispatch overhead.

| Operation | Description |
|-----------|-------------|
| `router: matchPattern (hit first)` | Match `/` against `/` — best case |
| `router: matchPattern (hit param)` | Match `/post/{slug}` with one parameter |
| `router: matchPattern (hit 2 params)` | Match pattern with two `{param}` segments |
| `router: matchPattern (miss)` | Pattern that does not match — regex failure path |
| `router: full scan (21 routes)` | Scan all 21 route patterns for a random URI |
| `router: middlewareMatches` | Four middleware pattern checks (wildcard, prefix, exact, miss) |

## Output Format

Each suite prints a table:

```
  Operation                      Ops   Avg (ms)   Med (ms)   P95 (ms)   Max (ms)   Throughput
  --------------------------------------------------------------------------------------------
  db::write                      200      1.842      1.756      2.350      4.120        543 op/s
  db::read (single)              200      0.498      0.470      0.650      0.830       2010 op/s
```

After all suites, a **bottleneck analysis** section ranks the top 5 operations by:
- Slowest average latency
- Highest tail latency (p95)

## Metrics Glossary

| Metric | Meaning |
|--------|---------|
| **Ops** | Number of iterations executed |
| **Avg** | Arithmetic mean of all iteration times |
| **Med** | Median (50th percentile) — less affected by outliers than avg |
| **P95** | 95th percentile — worst-case latency excluding top 5% outliers |
| **Max** | Single slowest iteration |
| **Throughput** | Operations per second (`ops / total_seconds`) |

## Cleanup

All benchmark data is written to a temporary `content/_bench/` collection. It is automatically deleted:
- Between suites (to prevent cross-suite interference)
- On normal exit
- On fatal error (via `register_shutdown_function`)

No real content is ever read, modified, or deleted.

## Typical Bottlenecks

Based on benchmark results, these are the usual performance characteristics:

| Component | Typical throughput | Notes |
|-----------|-------------------|-------|
| Router matching | 300K-500K op/s | Pure regex — effectively free |
| SchemaValidator | 30K-130K op/s | In-memory field checks — negligible |
| JsonCodec (small) | 500K-1.4M op/s | PHP's native `json_*` functions |
| `db::exists` | ~50K op/s | Single `file_exists()` call |
| `db::read` (single) | ~2K op/s | Lock + read + decode + normalize |
| `db::write` | ~500 op/s | Sanitize + validate + atomic write |
| Collection readAll | ~70 op/s | Proportional to document count — main bottleneck |
| Cached readAll | ~2.5M op/s | In-memory array return — effectively free |

The dominant cost is always **disk I/O on uncached collection reads**. The in-request cache eliminates this for repeated reads within the same request.

## Tips

- Use `--iterations=500` or higher for stable median/p95 numbers on fast operations.
- Use `--suite=query --iterations=1000` to stress-test collection reads with many documents.
- Compare results before and after code changes to catch performance regressions.
- Run on the target server (not dev machine) for production-relevant numbers.
- Close other disk-heavy processes to reduce I/O noise during benchmarks.
