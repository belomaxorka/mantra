<?php
/**
 * Storage Tests Runner
 * Runs all storage layer tests
 */

echo "==========================================================\n";
echo "Storage Layer Test Suite\n";
echo "==========================================================\n\n";

$startTime = microtime(true);

// Run JsonCodec tests (format handling)
echo "1. JsonCodec Tests (JSON encoding/decoding)\n";
echo str_repeat('-', 50) . "\n";
require_once __DIR__ . '/JsonCodecTest.php';
echo "\n";

// Run JsonFile tests (deprecated compatibility layer)
echo "2. JsonFile Tests (Deprecated - backward compatibility)\n";
echo str_repeat('-', 50) . "\n";
require_once __DIR__ . '/JsonFileTest.php';
echo "\n";

// Run JsonStorageDriver tests
echo "3. JsonStorageDriver Tests\n";
echo str_repeat('-', 50) . "\n";
require_once __DIR__ . '/JsonStorageDriverTest.php';
echo "\n";

// Run MarkdownStorageDriver tests
echo "4. MarkdownStorageDriver Tests\n";
echo str_repeat('-', 50) . "\n";
require_once __DIR__ . '/MarkdownStorageDriverTest.php';
echo "\n";

$endTime = microtime(true);
$duration = round($endTime - $startTime, 3);

echo "==========================================================\n";
echo "All Storage Tests Completed in {$duration}s\n";
echo "==========================================================\n";
