<?php declare(strict_types=1);

class SchemaMigratorTest extends MantraTestCase
{
    public function testRunsFormalMigrationsSequentially(): void
    {
        $calls = [];
        $schema = [
            'version' => 3,
            'migrations' => [
                2 => function ($data, $from, $to) use (&$calls) {
                    $calls[] = [$from, $to];
                    $data['second'] = true;
                    return $data;
                },
                3 => function ($data, $from, $to) use (&$calls) {
                    $calls[] = [$from, $to];
                    $data['third'] = true;
                    return $data;
                },
            ],
        ];

        $result = SchemaMigrator::migrate(['schema_version' => 1], $schema);

        $this->assertSame([[1, 2], [2, 3]], $calls);
        $this->assertTrue($result['second']);
        $this->assertTrue($result['third']);
        $this->assertSame(3, $result['schema_version']);
    }

    public function testRejectsDataFromNewerSchema(): void
    {
        $this->expectException(UnsupportedSchemaVersionException::class);
        SchemaMigrator::migrate(['schema_version' => 4], ['version' => 3]);
    }

    public function testRejectsNonArrayWithoutMutatingInput(): void
    {
        $original = ['name' => 'safe', 'schema_version' => 1];
        $schema = [
            'version' => 2,
            'migrations' => [
                2 => fn($data) => 'broken',
            ],
        ];

        try {
            SchemaMigrator::migrate($original, $schema);
            $this->fail('Expected migration exception');
        } catch (SchemaMigrationException $e) {
            $this->assertSame(['name' => 'safe', 'schema_version' => 1], $original);
        }
    }
}
