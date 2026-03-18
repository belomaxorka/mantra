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