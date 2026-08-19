<?php declare(strict_types=1);

class CollectionRegistryTest extends MantraTestCase
{
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = MANTRA_STORAGE . '/test-collection-registry-' . bin2hex(random_bytes(5));
        mkdir($this->testDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    public function testCoreSchemaIsResolvedLazily(): void
    {
        $coreSchemas = $this->testDir . '/core';
        mkdir($coreSchemas, 0o755, true);
        $this->writeSchema($coreSchemas . '/articles.php', ['version' => 2]);

        $registry = new CollectionRegistry($coreSchemas);

        $definition = $registry->definition('articles');
        $this->assertInstanceOf(CollectionDefinition::class, $definition);
        $this->assertSame($coreSchemas . '/articles.php', $definition->schemaPath());
        $this->assertSame(['version' => 2], $registry->schema('articles'));
        $this->assertNull($registry->definition('missing'));
    }

    public function testCoreDefinitionCannotBeOverridden(): void
    {
        $coreSchemas = $this->testDir . '/core';
        mkdir($coreSchemas, 0o755, true);
        $this->writeSchema($coreSchemas . '/articles.php', ['source' => 'core']);
        $moduleSchema = $this->testDir . '/module-articles.php';
        $this->writeSchema($moduleSchema, ['source' => 'module']);

        $registry = new CollectionRegistry($coreSchemas);
        $this->expectException(LogicException::class);
        $registry->register('articles', $moduleSchema);
    }

    public function testDuplicateModuleDefinitionIsIdempotentOnlyForSamePath(): void
    {
        $firstPath = $this->testDir . '/first.php';
        $secondPath = $this->testDir . '/second.php';
        $this->writeSchema($firstPath, ['source' => 'first']);
        $this->writeSchema($secondPath, ['source' => 'second']);

        $registry = new CollectionRegistry($this->testDir . '/empty-core');
        $definition = $registry->register('articles', $firstPath);
        $this->assertSame($definition, $registry->register('articles', $firstPath));

        $this->expectException(LogicException::class);
        $registry->register('articles', $secondPath);
    }

    public function testRegisteredSchemaIsSharedByProductionDatabases(): void
    {
        $collection = 'registry_shared_' . bin2hex(random_bytes(4));
        $schemaPath = $this->testDir . '/shared.php';
        $this->writeSchema($schemaPath, ['version' => 7]);

        $first = new Database();
        $second = new Database(MANTRA_CONTENT);

        $this->assertSame($first->collections(), $second->collections());
        try {
            $first->registerSchema($collection, $schemaPath);
            $this->assertSame(['version' => 7], $second->collections()->schema($collection));
        } finally {
            CollectionRegistry::shared()->unregister($collection);
        }
    }

    public function testCustomDatabaseRegistriesAreIsolatedByDefault(): void
    {
        $schemaPath = $this->testDir . '/isolated.php';
        $this->writeSchema($schemaPath, ['version' => 1]);
        $first = new Database($this->testDir . '/first');
        $second = new Database($this->testDir . '/second');

        $first->registerSchema('isolated', $schemaPath);

        $this->assertNotSame($first->collections(), $second->collections());
        $this->assertSame(['version' => 1], $first->collections()->schema('isolated'));
        $this->assertNull($second->collections()->schema('isolated'));
    }

    public function testInjectedRegistryIsSharedAcrossCustomDatabases(): void
    {
        $schemaPath = $this->testDir . '/injected.php';
        $this->writeSchema($schemaPath, [
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);
        $registry = new CollectionRegistry($this->testDir . '/empty-core');
        $first = new Database($this->testDir . '/first', null, $registry);
        $second = new Database($this->testDir . '/second', null, $registry);

        $first->registerSchema('injected', $schemaPath);

        $this->expectException(SchemaValidationException::class);
        $second->write('injected', 'document', []);
    }

    public function testMissingRegisteredSchemaCanAppearAfterRegistration(): void
    {
        $schemaPath = $this->testDir . '/late.php';
        $registry = new CollectionRegistry($this->testDir . '/empty-core');
        $registry->register('late', $schemaPath);

        $this->assertNull($registry->schema('late'));
        $this->writeSchema($schemaPath, ['version' => 3]);
        $this->assertSame(['version' => 3], $registry->schema('late'));
    }

    public function testUsersSchemaIsACoreContract(): void
    {
        $schema = (new CollectionRegistry())->schema('users');

        $this->assertIsArray($schema);
        $this->assertTrue($schema['unique']['username']['case_insensitive']);
        $this->assertTrue($schema['unique']['email']['case_insensitive']);
        $this->assertSame(['admin', 'editor', 'viewer'], $schema['fields']['role']['values']);
        $this->assertSame(['active', 'inactive', 'banned'], $schema['fields']['status']['values']);
    }

    public function testDatabaseEnforcesUsersSchemaBeforeModulesLoad(): void
    {
        $database = new Database($this->testDir . '/users-database');

        $this->expectException(SchemaValidationException::class);
        $database->write('users', 'invalid-user', [
            'username' => 'tester',
            'email' => 'tester@example.com',
            'password' => str_repeat('x', 60),
            'role' => 'superuser',
            'status' => 'active',
        ]);
    }

    public function testDatabaseEnforcesCaseInsensitiveCoreUserUniqueness(): void
    {
        $database = new Database($this->testDir . '/unique-users-database');
        $user = [
            'username' => 'RegistryAdmin',
            'email' => 'registry@example.com',
            'password' => str_repeat('x', 60),
            'role' => 'admin',
            'status' => 'active',
        ];
        $database->write('users', 'first-user', $user);

        $user['username'] = 'registryadmin';
        $user['email'] = 'other@example.com';

        $this->expectException(UniqueConstraintViolationException::class);
        $database->write('users', 'second-user', $user);
    }

    public function testDatabaseOwnsCoreUserTimestamps(): void
    {
        $database = new Database($this->testDir . '/timestamp-users-database');
        $database->write('users', 'timestamp-user', [
            'username' => 'TimestampUser',
            'email' => 'timestamp@example.com',
            'password' => str_repeat('x', 60),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $user = $database->read('users', 'timestamp-user');
        $this->assertNotSame('', $user['created_at']);
        $this->assertNotSame('', $user['updated_at']);
        $this->assertSame($user['created_at'], $user['updated_at']);
    }

    public function testInvalidCollectionNamesAreRejected(): void
    {
        $registry = new CollectionRegistry($this->testDir);

        $this->expectException(InvalidArgumentException::class);
        $registry->register('../users', $this->testDir . '/users.php');
    }

    private function writeSchema($path, $schema): void
    {
        file_put_contents($path, "<?php\nreturn " . var_export($schema, true) . ";\n");
    }
}
