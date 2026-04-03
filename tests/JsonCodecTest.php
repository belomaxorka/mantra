<?php declare(strict_types=1);

/**
 * JsonCodec Tests (PHPUnit)
 * Tests for simple JSON encoding/decoding wrapper
 */
class JsonCodecTest extends MantraTestCase
{
    public function testEncode(): void
    {
        $data = ['key' => 'value', 'number' => 42];
        $json = JsonCodec::encode($data);

        $this->assertIsString($json, 'encode() returns string');
        $this->assertStringContainsString('"key"', $json, 'encode() contains key');
        $this->assertStringContainsString('"value"', $json, 'encode() contains value');
        $this->assertStringContainsString('42', $json, 'encode() contains number');
    }

    public function testDecode(): void
    {
        $json = '{"key": "value", "number": 42}';
        $data = JsonCodec::decode($json);

        $this->assertIsArray($data, 'decode() returns array');
        $this->assertSame('value', $data['key'], 'decode() preserves string values');
        $this->assertSame(42, $data['number'], 'decode() preserves numeric values');
    }

    public function testEncodeInvalidData(): void
    {
        // Test with resource (should fail)
        $resource = fopen('php://memory', 'r');

        try {
            JsonCodec::encode($resource);
            $this->fail('Expected JsonCodecException');
        } catch (JsonCodecException $e) {
            $this->assertStringContainsString('Failed to encode JSON', $e->getMessage(), 'Exception message mentions encoding failure');
        }

        fclose($resource);
    }

    public function testDecodeInvalidJson(): void
    {
        $invalidJson = '{invalid json content}';

        try {
            JsonCodec::decode($invalidJson);
            $this->fail('Expected JsonCodecException');
        } catch (JsonCodecException $e) {
            $this->assertStringContainsString('Invalid JSON', $e->getMessage(), 'Exception message mentions invalid JSON');
        }
    }

    public function testDecodeNonArrayRoot(): void
    {
        $stringJson = '"just a string"';

        try {
            JsonCodec::decode($stringJson);
            $this->fail('Expected JsonCodecException');
        } catch (JsonCodecException $e) {
            $this->assertStringContainsString('JSON root must be', $e->getMessage(), 'Exception message mentions root requirement');
        }
    }

    public function testUnicodeHandling(): void
    {
        $data = [
            'russian' => 'Привет мир',
            'japanese' => '日本語',
            'emoji' => '🚀 🎉 ✨',
        ];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertSame('Привет мир', $decoded['russian'], 'Unicode Russian text preserved');
        $this->assertSame('日本語', $decoded['japanese'], 'Unicode Japanese text preserved');
        $this->assertSame('🚀 🎉 ✨', $decoded['emoji'], 'Unicode emoji preserved');
    }

    public function testSpecialCharacters(): void
    {
        $data = [
            'quotes' => 'Text with "quotes" and \'apostrophes\'',
            'slashes' => '/path/to/file',
            'backslashes' => 'C:\\Windows\\Path',
            'newlines' => "Line 1\nLine 2\nLine 3",
        ];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertSame($data['quotes'], $decoded['quotes'], 'Quotes preserved correctly');
        $this->assertSame($data['slashes'], $decoded['slashes'], 'Forward slashes preserved');
        $this->assertSame($data['backslashes'], $decoded['backslashes'], 'Backslashes preserved');
        $this->assertSame($data['newlines'], $decoded['newlines'], 'Newlines preserved');
    }

    public function testNestedData(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep',
                    ],
                ],
            ],
            'array' => [1, 2, 3, 4, 5],
        ];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertSame('deep', $decoded['level1']['level2']['level3']['value'], 'Nested objects preserved');
        $this->assertCount(5, $decoded['array'], 'Nested arrays preserved');
        $this->assertSame(3, $decoded['array'][2], 'Array values preserved');
    }

    public function testEmptyArray(): void
    {
        $data = [];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertIsArray($decoded, 'Empty array encoded/decoded correctly');
        $this->assertCount(0, $decoded, 'Empty array has zero elements');
    }

    public function testBooleanValues(): void
    {
        $data = [
            'true_value' => true,
            'false_value' => false,
        ];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertTrue($decoded['true_value'], 'Boolean true preserved');
        $this->assertFalse($decoded['false_value'], 'Boolean false preserved');
        $this->assertIsBool($decoded['true_value'], 'True value is boolean type');
        $this->assertIsBool($decoded['false_value'], 'False value is boolean type');
    }

    public function testNumericValues(): void
    {
        $data = [
            'integer' => 42,
            'float' => 3.14159,
            'zero' => 0,
            'negative' => -123,
        ];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertSame(42, $decoded['integer'], 'Integer value preserved');
        $this->assertSame(3.14159, $decoded['float'], 'Float value preserved');
        $this->assertSame(0, $decoded['zero'], 'Zero value preserved');
        $this->assertSame(-123, $decoded['negative'], 'Negative value preserved');
    }

    public function testNullValues(): void
    {
        $data = [
            'null_value' => null,
            'string' => 'not null',
        ];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertNull($decoded['null_value'], 'Null value preserved');
        $this->assertSame('not null', $decoded['string'], 'Non-null value preserved');
        $this->assertNull($decoded['null_value'], 'Null value is null type');
    }

    public function testEmptyStringDecode(): void
    {
        // Test empty string
        $this->expectException(JsonCodecException::class);
        JsonCodec::decode('');
    }

    public function testWhitespaceOnlyDecode(): void
    {
        $this->expectException(JsonCodecException::class);
        JsonCodec::decode('   ');
    }

    public function testNewlinesTabsOnlyDecode(): void
    {
        $this->expectException(JsonCodecException::class);
        JsonCodec::decode("\n\t \n");
    }

    public function testEmptyObjectDecode(): void
    {
        $emptyObjectJson = '{}';
        $decoded = JsonCodec::decode($emptyObjectJson);

        $this->assertIsArray($decoded, 'Empty object decoded as array');
        $this->assertCount(0, $decoded, 'Empty object has zero elements');
        $this->assertSame([], $decoded, 'Empty object equals empty array');
    }

    public function testSpecialNumericValuesInf(): void
    {
        $this->expectException(JsonCodecException::class);
        JsonCodec::encode(['value' => INF]);
    }

    public function testSpecialNumericValuesNegInf(): void
    {
        $this->expectException(JsonCodecException::class);
        JsonCodec::encode(['value' => -INF]);
    }

    public function testSpecialNumericValuesNan(): void
    {
        $this->expectException(JsonCodecException::class);
        JsonCodec::encode(['value' => NAN]);
    }

    public function testSpecialNumericValuesScientificNotation(): void
    {
        $data = ['scientific' => 1.5e10, 'negative_exp' => 2.5e-3];
        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertEquals(1.5e10, $decoded['scientific'], 'Scientific notation (positive exp) preserved');
        $this->assertEquals(2.5e-3, $decoded['negative_exp'], 'Scientific notation (negative exp) preserved');
    }

    public function testJsonFormatting(): void
    {
        $data = ['key1' => 'value1', 'key2' => ['nested' => 'value']];
        $json = JsonCodec::encode($data);

        // Check for pretty print (should have newlines and indentation)
        $this->assertStringContainsString("\n", $json, 'JSON contains newlines (pretty print)');
        $this->assertTrue(
            str_contains($json, '    ') || str_contains($json, "\t"),
            'JSON contains indentation',
        );

        // Check for unescaped slashes
        $dataWithSlashes = ['path' => '/path/to/file'];
        $jsonWithSlashes = JsonCodec::encode($dataWithSlashes);
        $this->assertStringNotContainsString('\\/', $jsonWithSlashes, 'Slashes are not escaped (JSON_UNESCAPED_SLASHES)');
        $this->assertStringContainsString('/path/to/file', $jsonWithSlashes, 'Slashes preserved as-is');
    }

    public function testVariousInvalidJson(): void
    {
        $invalidCases = [
            '{"key": value}' => 'unquoted value',
            '{"key": "value",}' => 'trailing comma',
            '{"key": "value"' => 'unclosed brace',
            '[1, 2, 3' => 'unclosed bracket',
            '{"key":: "value"}' => 'double colon',
            '{"key": "value"}}' => 'extra closing brace',
            'null' => 'JSON null (not array)',
            '123' => 'JSON number (not array)',
            'true' => 'JSON boolean (not array)',
            '"string"' => 'JSON string (not array)',
        ];

        foreach ($invalidCases as $json => $description) {
            $json = (string)$json; // numeric keys auto-cast to int
            try {
                JsonCodec::decode($json);
                $this->fail("decode() should throw exception for $description");
            } catch (JsonCodecException $e) {
                $this->assertTrue(true, "decode() throws JsonCodecException for $description");
            }
        }
    }

    public function testMixedArrayTypes(): void
    {
        // Mixed numeric and string keys
        $data = [
            0 => 'first',
            'key' => 'second',
            2 => 'third',
        ];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertArrayHasKey(0, $decoded, 'Mixed array: numeric key 0 exists');
        $this->assertArrayHasKey('key', $decoded, 'Mixed array: string key exists');
        $this->assertArrayHasKey(2, $decoded, 'Mixed array: numeric key 2 exists');

        // Non-sequential indices
        $data2 = [0 => 'a', 2 => 'b', 5 => 'c'];
        $json2 = JsonCodec::encode($data2);
        $decoded2 = JsonCodec::decode($json2);

        $this->assertCount(3, $decoded2, 'Non-sequential array has correct count');
        $this->assertSame('a', $decoded2[0], 'Non-sequential array: index 0 preserved');
        $this->assertSame('b', $decoded2[2], 'Non-sequential array: index 2 preserved');
        $this->assertSame('c', $decoded2[5], 'Non-sequential array: index 5 preserved');
    }

    public function testSpecialKeysInObjects(): void
    {
        $data = [
            'key with spaces' => 'value1',
            'key.with.dots' => 'value2',
            'key/with/slashes' => 'value3',
            'key"with"quotes' => 'value4',
            'key\'with\'apostrophes' => 'value5',
            'key-with-dashes' => 'value6',
            'key_with_underscores' => 'value7',
            'кириллица' => 'value8',
            '123numeric' => 'value9',
            '' => 'empty_key',
        ];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertSame('value1', $decoded['key with spaces'], 'Key with spaces preserved');
        $this->assertSame('value2', $decoded['key.with.dots'], 'Key with dots preserved');
        $this->assertSame('value3', $decoded['key/with/slashes'], 'Key with slashes preserved');
        $this->assertSame('value4', $decoded['key"with"quotes'], 'Key with quotes preserved');
        $this->assertSame('value5', $decoded['key\'with\'apostrophes'], 'Key with apostrophes preserved');
        $this->assertSame('value6', $decoded['key-with-dashes'], 'Key with dashes preserved');
        $this->assertSame('value7', $decoded['key_with_underscores'], 'Key with underscores preserved');
        $this->assertSame('value8', $decoded['кириллица'], 'Cyrillic key preserved');
        $this->assertSame('value9', $decoded['123numeric'], 'Numeric-starting key preserved');
        $this->assertSame('empty_key', $decoded[''], 'Empty key preserved');
    }

    public function testDeepNesting(): void
    {
        // Create deeply nested structure (10 levels)
        $deep = [];
        $current = &$deep;
        for ($i = 0; $i < 10; $i++) {
            $current['level' . $i] = [];
            $current = &$current['level' . $i];
        }
        $current['final'] = 'deep_value';

        $json = JsonCodec::encode($deep);
        $decoded = JsonCodec::decode($json);

        // Navigate to the deep value
        $current = $decoded;
        for ($i = 0; $i < 10; $i++) {
            $this->assertArrayHasKey('level' . $i, $current, "Deep nesting: level $i exists");
            $current = $current['level' . $i];
        }
        $this->assertSame('deep_value', $current['final'], 'Deep nesting: final value preserved');

        // Test large array
        $largeArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeArray[] = "item_$i";
        }

        $json2 = JsonCodec::encode($largeArray);
        $decoded2 = JsonCodec::decode($json2);

        $this->assertCount(1000, $decoded2, 'Large array: correct count');
        $this->assertSame('item_0', $decoded2[0], 'Large array: first item preserved');
        $this->assertSame('item_999', $decoded2[999], 'Large array: last item preserved');
        $this->assertSame('item_500', $decoded2[500], 'Large array: middle item preserved');
    }

    public function testLongStrings(): void
    {
        // Test very long string (100KB)
        $longString = str_repeat('abcdefghij', 10000); // 100,000 characters
        $data = ['long' => $longString];

        $json = JsonCodec::encode($data);
        $decoded = JsonCodec::decode($json);

        $this->assertSame(100000, strlen($decoded['long']), 'Long string: correct length preserved');
        $this->assertSame($longString, $decoded['long'], 'Long string: content preserved');

        // Test string with repeated unicode
        $unicodeString = str_repeat('🚀日本語Привет', 1000);
        $data2 = ['unicode_long' => $unicodeString];

        $json2 = JsonCodec::encode($data2);
        $decoded2 = JsonCodec::decode($json2);

        $this->assertSame($unicodeString, $decoded2['unicode_long'], 'Long unicode string preserved');

        // Test multiple long strings in one document
        $data3 = [
            'string1' => str_repeat('a', 10000),
            'string2' => str_repeat('b', 10000),
            'string3' => str_repeat('c', 10000),
        ];

        $json3 = JsonCodec::encode($data3);
        $decoded3 = JsonCodec::decode($json3);

        $this->assertSame(10000, strlen($decoded3['string1']), 'Multiple long strings: string1 length');
        $this->assertSame(10000, strlen($decoded3['string2']), 'Multiple long strings: string2 length');
        $this->assertSame(10000, strlen($decoded3['string3']), 'Multiple long strings: string3 length');
    }
}
