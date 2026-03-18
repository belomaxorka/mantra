<?php
/**
 * JsonCodec Tests
 * Tests for simple JSON encoding/decoding wrapper
 */

require_once __DIR__ . '/../core/bootstrap.php';

class JsonCodecTest {
    private $results = array();

    public function run() {
        echo "Running JsonCodec Tests...\n\n";

        $this->testEncode();
        $this->testDecode();
        $this->testEncodeInvalidData();
        $this->testDecodeInvalidJson();
        $this->testDecodeNonArrayRoot();
        $this->testUnicodeHandling();
        $this->testSpecialCharacters();
        $this->testNestedData();
        $this->testEmptyArray();
        $this->testBooleanValues();
        $this->testNumericValues();
        $this->testNullValues();

        // Additional edge case tests
        $this->testEmptyStringDecode();
        $this->testEmptyObjectDecode();
        $this->testSpecialNumericValues();
        $this->testJsonFormatting();
        $this->testVariousInvalidJson();
        $this->testMixedArrayTypes();
        $this->testSpecialKeysInObjects();
        $this->testDeepNesting();
        $this->testLongStrings();

        $this->printResults();
    }

    private function assert($condition, $message) {
        if ($condition) {
            $this->results[] = array('status' => 'PASS', 'message' => $message);
            echo "✓ PASS: $message\n";
        } else {
            $this->results[] = array('status' => 'FAIL', 'message' => $message);
            echo "✗ FAIL: $message\n";
        }
    }

    private function testEncode() {
        $data = array('key' => 'value', 'number' => 42);
        $json = JsonCodec::encode($data);

        $this->assert(is_string($json), 'encode() returns string');
        $this->assert(strpos($json, '"key"') !== false, 'encode() contains key');
        $this->assert(strpos($json, '"value"') !== false, 'encode() contains value');
        $this->assert(strpos($json, '42') !== false, 'encode() contains number');
    }

    private function testDecode() {
        $json = '{"key": "value", "number": 42}';
        $data = JsonCodec::decode($json);

        $this->assert(is_array($data), 'decode() returns array');
        $this->assert($data['key'] === 'value', 'decode() preserves string values');
        $this->assert($data['number'] === 42, 'decode() preserves numeric values');
    }

    private function testEncodeInvalidData() {
        // Test with resource (should fail)
        $resource = fopen('php://memory', 'r');

        try {
            JsonCodec::encode($resource);
            $this->assert(false, 'encode() should throw exception for invalid data');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'encode() throws JsonCodecException for invalid data');
            $this->assert(strpos($e->getMessage(), 'Failed to encode JSON') !== false, 'Exception message mentions encoding failure');
        }

        fclose($resource);
    }

    private function testDecodeInvalidJson() {
        $invalidJson = '{invalid json content}';

        try {
            JsonCodec::decode($invalidJson);
            $this->assert(false, 'decode() should throw exception for invalid JSON');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'decode() throws JsonCodecException for invalid JSON');
            $this->assert(strpos($e->getMessage(), 'Invalid JSON') !== false, 'Exception message mentions invalid JSON');
        }
    }

    private function testDecodeNonArrayRoot() {
        $stringJson = '"just a string"';

        try {
            JsonCodec::decode($stringJson);
            $this->assert(false, 'decode() should throw exception for non-array root');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'decode() throws JsonCodecException for non-array root');
            $this->assert(strpos($e->getMessage(), 'JSON root must be') !== false, 'Exception message mentions root requirement');
        }
    }

    private function testUnicodeHandling() {
        $data = array(
            'russian' => 'Привет мир',
            'japanese' => '日本語',
            'emoji' => '🚀 🎉 ✨'
        );

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert($decoded['russian'] === 'Привет мир', 'Unicode Russian text preserved');
        $this->assert($decoded['japanese'] === '日本語', 'Unicode Japanese text preserved');
        $this->assert($decoded['emoji'] === '🚀 🎉 ✨', 'Unicode emoji preserved');
    }

    private function testSpecialCharacters() {
        $data = array(
            'quotes' => 'Text with "quotes" and \'apostrophes\'',
            'slashes' => '/path/to/file',
            'backslashes' => 'C:\\Windows\\Path',
            'newlines' => "Line 1\nLine 2\nLine 3"
        );

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert($decoded['quotes'] === $data['quotes'], 'Quotes preserved correctly');
        $this->assert($decoded['slashes'] === $data['slashes'], 'Forward slashes preserved');
        $this->assert($decoded['backslashes'] === $data['backslashes'], 'Backslashes preserved');
        $this->assert($decoded['newlines'] === $data['newlines'], 'Newlines preserved');
    }

    private function testNestedData() {
        $data = array(
            'level1' => array(
                'level2' => array(
                    'level3' => array(
                        'value' => 'deep'
                    )
                )
            ),
            'array' => array(1, 2, 3, 4, 5)
        );

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert($decoded['level1']['level2']['level3']['value'] === 'deep', 'Nested objects preserved');
        $this->assert(count($decoded['array']) === 5, 'Nested arrays preserved');
        $this->assert($decoded['array'][2] === 3, 'Array values preserved');
    }

    private function testEmptyArray() {
        $data = array();

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert(is_array($decoded), 'Empty array encoded/decoded correctly');
        $this->assert(count($decoded) === 0, 'Empty array has zero elements');
    }

    private function testBooleanValues() {
        $data = array(
            'true_value' => true,
            'false_value' => false
        );

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert($decoded['true_value'] === true, 'Boolean true preserved');
        $this->assert($decoded['false_value'] === false, 'Boolean false preserved');
        $this->assert(is_bool($decoded['true_value']), 'True value is boolean type');
        $this->assert(is_bool($decoded['false_value']), 'False value is boolean type');
    }

    private function testNumericValues() {
        $data = array(
            'integer' => 42,
            'float' => 3.14159,
            'zero' => 0,
            'negative' => -123
        );

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert($decoded['integer'] === 42, 'Integer value preserved');
        $this->assert($decoded['float'] === 3.14159, 'Float value preserved');
        $this->assert($decoded['zero'] === 0, 'Zero value preserved');
        $this->assert($decoded['negative'] === -123, 'Negative value preserved');
    }

    private function testNullValues() {
        $data = array(
            'null_value' => null,
            'string' => 'not null'
        );

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert($decoded['null_value'] === null, 'Null value preserved');
        $this->assert($decoded['string'] === 'not null', 'Non-null value preserved');
        $this->assert(is_null($decoded['null_value']), 'Null value is null type');
    }

    private function testEmptyStringDecode() {
        // Test empty string
        try {
            JsonCodec::decode('');
            $this->assert(false, 'decode() should throw exception for empty string');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'decode() throws JsonCodecException for empty string');
        }

        // Test whitespace only
        try {
            JsonCodec::decode('   ');
            $this->assert(false, 'decode() should throw exception for whitespace only');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'decode() throws JsonCodecException for whitespace only');
        }

        // Test newlines and tabs
        try {
            JsonCodec::decode("\n\t \n");
            $this->assert(false, 'decode() should throw exception for newlines/tabs only');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'decode() throws JsonCodecException for newlines/tabs only');
        }
    }

    private function testEmptyObjectDecode() {
        $emptyObjectJson = '{}';
        $decoded = JsonCodec::decode($emptyObjectJson);

        $this->assert(is_array($decoded), 'Empty object decoded as array');
        $this->assert(count($decoded) === 0, 'Empty object has zero elements');
        $this->assert($decoded === array(), 'Empty object equals empty array');
    }

    private function testSpecialNumericValues() {
        // Test INF
        try {
            JsonCodec::encode(array('value' => INF));
            $this->assert(false, 'encode() should throw exception for INF');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'encode() throws JsonCodecException for INF');
        }

        // Test -INF
        try {
            JsonCodec::encode(array('value' => -INF));
            $this->assert(false, 'encode() should throw exception for -INF');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'encode() throws JsonCodecException for -INF');
        }

        // Test NAN
        try {
            JsonCodec::encode(array('value' => NAN));
            $this->assert(false, 'encode() should throw exception for NAN');
        } catch (JsonCodecException $e) {
            $this->assert(true, 'encode() throws JsonCodecException for NAN');
        }

        // Test scientific notation
        $data = array('scientific' => 1.5e10, 'negative_exp' => 2.5e-3);
        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert($decoded['scientific'] == 1.5e10, 'Scientific notation (positive exp) preserved');
        $this->assert($decoded['negative_exp'] == 2.5e-3, 'Scientific notation (negative exp) preserved');
    }

    private function testJsonFormatting() {
        $data = array('key1' => 'value1', 'key2' => array('nested' => 'value'));
        $json = JsonCodec::encode($data);

        // Check for pretty print (should have newlines and indentation)
        $this->assert(strpos($json, "\n") !== false, 'JSON contains newlines (pretty print)');
        $this->assert(strpos($json, '    ') !== false || strpos($json, "\t") !== false, 'JSON contains indentation');

        // Check for unescaped slashes
        $dataWithSlashes = array('path' => '/path/to/file');
        $jsonWithSlashes = JsonCodec::encode($dataWithSlashes);
        $this->assert(strpos($jsonWithSlashes, '\\/') === false, 'Slashes are not escaped (JSON_UNESCAPED_SLASHES)');
        $this->assert(strpos($jsonWithSlashes, '/path/to/file') !== false, 'Slashes preserved as-is');
    }

    private function testVariousInvalidJson() {
        $invalidCases = array(
            '{"key": value}' => 'unquoted value',
            '{"key": "value",}' => 'trailing comma',
            '{"key": "value"' => 'unclosed brace',
            '[1, 2, 3' => 'unclosed bracket',
            '{"key":: "value"}' => 'double colon',
            '{"key": "value"}}' => 'extra closing brace',
            'null' => 'JSON null (not array)',
            '123' => 'JSON number (not array)',
            'true' => 'JSON boolean (not array)',
            '"string"' => 'JSON string (not array)'
        );

        foreach ($invalidCases as $json => $description) {
            try {
                JsonCodec::decode($json);
                $this->assert(false, "decode() should throw exception for $description");
            } catch (JsonCodecException $e) {
                $this->assert(true, "decode() throws JsonCodecException for $description");
            }
        }
    }

    private function testMixedArrayTypes() {
        // Mixed numeric and string keys
        $data = array(
            0 => 'first',
            'key' => 'second',
            2 => 'third'
        );

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert(isset($decoded[0]), 'Mixed array: numeric key 0 exists');
        $this->assert(isset($decoded['key']), 'Mixed array: string key exists');
        $this->assert(isset($decoded[2]), 'Mixed array: numeric key 2 exists');

        // Non-sequential indices
        $data2 = array(0 => 'a', 2 => 'b', 5 => 'c');
        $json2 = JsonCodec::encode($data2);
        $decoded2 = JsonCodec::decode($json2);

        $this->assert(count($decoded2) === 3, 'Non-sequential array has correct count');
        $this->assert($decoded2[0] === 'a', 'Non-sequential array: index 0 preserved');
        $this->assert($decoded2[2] === 'b', 'Non-sequential array: index 2 preserved');
        $this->assert($decoded2[5] === 'c', 'Non-sequential array: index 5 preserved');
    }

    private function testSpecialKeysInObjects() {
        $data = array(
            'key with spaces' => 'value1',
            'key.with.dots' => 'value2',
            'key/with/slashes' => 'value3',
            'key"with"quotes' => 'value4',
            'key\'with\'apostrophes' => 'value5',
            'key-with-dashes' => 'value6',
            'key_with_underscores' => 'value7',
            'кириллица' => 'value8',
            '123numeric' => 'value9',
            '' => 'empty_key'
        );

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert($decoded['key with spaces'] === 'value1', 'Key with spaces preserved');
        $this->assert($decoded['key.with.dots'] === 'value2', 'Key with dots preserved');
        $this->assert($decoded['key/with/slashes'] === 'value3', 'Key with slashes preserved');
        $this->assert($decoded['key"with"quotes'] === 'value4', 'Key with quotes preserved');
        $this->assert($decoded['key\'with\'apostrophes'] === 'value5', 'Key with apostrophes preserved');
        $this->assert($decoded['key-with-dashes'] === 'value6', 'Key with dashes preserved');
        $this->assert($decoded['key_with_underscores'] === 'value7', 'Key with underscores preserved');
        $this->assert($decoded['кириллица'] === 'value8', 'Cyrillic key preserved');
        $this->assert($decoded['123numeric'] === 'value9', 'Numeric-starting key preserved');
        $this->assert($decoded[''] === 'empty_key', 'Empty key preserved');
    }

    private function testDeepNesting() {
        // Create deeply nested structure (10 levels)
        $deep = array();
        $current = &$deep;
        for ($i = 0; $i < 10; $i++) {
            $current['level' . $i] = array();
            $current = &$current['level' . $i];
        }
        $current['final'] = 'deep_value';

        $json = JsonCodec::encode($deep);
        $decoded = JsonCodec::decode($json);

        // Navigate to the deep value
        $current = $decoded;
        for ($i = 0; $i < 10; $i++) {
            $this->assert(isset($current['level' . $i]), "Deep nesting: level $i exists");
            $current = $current['level' . $i];
        }
        $this->assert($current['final'] === 'deep_value', 'Deep nesting: final value preserved');

        // Test large array
        $largeArray = array();
        for ($i = 0; $i < 1000; $i++) {
            $largeArray[] = "item_$i";
        }

        $json2 = JsonCodec::encode($largeArray);
        $decoded2 = JsonCodec::decode($json2);

        $this->assert(count($decoded2) === 1000, 'Large array: correct count');
        $this->assert($decoded2[0] === 'item_0', 'Large array: first item preserved');
        $this->assert($decoded2[999] === 'item_999', 'Large array: last item preserved');
        $this->assert($decoded2[500] === 'item_500', 'Large array: middle item preserved');
    }

    private function testLongStrings() {
        // Test very long string (100KB)
        $longString = str_repeat('abcdefghij', 10000); // 100,000 characters
        $data = array('long' => $longString);

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assert(strlen($decoded['long']) === 100000, 'Long string: correct length preserved');
        $this->assert($decoded['long'] === $longString, 'Long string: content preserved');

        // Test string with repeated unicode
        $unicodeString = str_repeat('🚀日本語Привет', 1000);
        $data2 = array('unicode_long' => $unicodeString);

        $json2 = JsonCodec::encode($data2);
        $decoded2 = JsonCodec::decode($json2);

        $this->assert($decoded2['unicode_long'] === $unicodeString, 'Long unicode string preserved');

        // Test multiple long strings in one document
        $data3 = array(
            'string1' => str_repeat('a', 10000),
            'string2' => str_repeat('b', 10000),
            'string3' => str_repeat('c', 10000)
        );

        $json3 = JsonCodec::encode($data3);
        $decoded3 = JsonCodec::decode($json3);

        $this->assert(strlen($decoded3['string1']) === 10000, 'Multiple long strings: string1 length');
        $this->assert(strlen($decoded3['string2']) === 10000, 'Multiple long strings: string2 length');
        $this->assert(strlen($decoded3['string3']) === 10000, 'Multiple long strings: string3 length');
    }

    private function printResults() {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Test Results Summary\n";
        echo str_repeat('=', 50) . "\n";

        $passed = 0;
        $failed = 0;

        foreach ($this->results as $result) {
            if ($result['status'] === 'PASS') {
                $passed++;
            } else {
                $failed++;
            }
        }

        $total = $passed + $failed;
        echo "Total: $total | Passed: $passed | Failed: $failed\n";

        if ($failed === 0) {
            echo "\n✓ All tests passed!\n";
        } else {
            echo "\n✗ Some tests failed.\n";
        }
    }
}

// Run tests
$test = new JsonCodecTest();
$test->run();