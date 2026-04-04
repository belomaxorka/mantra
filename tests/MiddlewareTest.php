<?php declare(strict_types=1);

/**
 * Middleware System Tests
 *
 * Tests MiddlewarePipeline, MiddlewareRegistry, and their integration.
 *
 * @covers \Http\MiddlewarePipeline
 * @covers \Http\MiddlewareRegistry
 * @covers \Http\MiddlewareInterface
 */
class MiddlewareTest extends MantraTestCase
{
    // ==========================================================
    //  MiddlewarePipeline
    // ==========================================================

    // ---------- Core handler execution ----------

    public function testEmptyPipelineRunsCoreHandler(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $called = false;

        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testPipelineReturnsTrueWhenCoreReached(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();

        $result = $pipeline->run(function (): void {});

        $this->assertTrue($result);
    }

    // ---------- Class-based middleware (MiddlewareInterface) ----------

    public function testMiddlewareThatCallsNext(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $pipeline->pipe(new PassthroughMiddleware());

        $called = false;
        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testMiddlewareThatHalts(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $pipeline->pipe(new HaltingMiddleware());

        $called = false;
        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });

        $this->assertFalse($called, 'Core handler should not run when middleware halts');
        $this->assertFalse($result);
    }

    public function testMultipleMiddlewareExecuteInOrder(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $log = [];

        $pipeline->pipe(new LoggingMiddleware($log, 'A'));
        $pipeline->pipe(new LoggingMiddleware($log, 'B'));
        $pipeline->pipe(new LoggingMiddleware($log, 'C'));

        $pipeline->run(function () use (&$log): void {
            $log[] = 'core';
        });

        $this->assertSame(['A:before', 'B:before', 'C:before', 'core', 'C:after', 'B:after', 'A:after'], $log);
    }

    public function testMiddlewareCanRunCodeAfterNext(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $log = [];

        $pipeline->pipe(new LoggingMiddleware($log, 'wrap'));

        $pipeline->run(function () use (&$log): void {
            $log[] = 'handler';
        });

        $this->assertSame(['wrap:before', 'handler', 'wrap:after'], $log);
    }

    public function testHaltingMiddlewarePreventsSubsequentMiddleware(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $log = [];

        $pipeline->pipe(new LoggingMiddleware($log, 'first'));
        $pipeline->pipe(new HaltingMiddleware());
        $pipeline->pipe(new LoggingMiddleware($log, 'third'));

        $called = false;
        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertFalse($result);
        // Third middleware never runs; first middleware's after still runs
        // (pipeline unwinds like try/finally — outer layers always complete)
        $this->assertSame(['first:before', 'first:after'], $log);
    }

    // ---------- Backward-compatible callables ----------

    public function testCallableThatReturnsTrueContinues(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $pipeline->pipe(fn () => true);

        $called = false;
        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testCallableThatReturnsFalseHalts(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $pipeline->pipe(fn () => false);

        $called = false;
        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertFalse($result);
    }

    public function testCallableThatReturnsNullContinues(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $pipeline->pipe(function (): void {
            // no return = null
        });

        $called = false;
        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testCallableIsInvokedWithoutArguments(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $argCount = -1;

        $pipeline->pipe(function () use (&$argCount) {
            $argCount = func_num_args();
            return true;
        });

        $pipeline->run(function (): void {});

        $this->assertSame(0, $argCount, 'Legacy callable should receive no arguments');
    }

    // ---------- Mixed layers ----------

    public function testMixedClassAndCallableLayers(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $log = [];

        $pipeline->pipe(new LoggingMiddleware($log, 'class'));
        $pipeline->pipe(function () use (&$log) {
            $log[] = 'callable';
            return true;
        });

        $pipeline->run(function () use (&$log): void {
            $log[] = 'core';
        });

        $this->assertSame(['class:before', 'callable', 'core', 'class:after'], $log);
    }

    public function testCallableHaltsPreventsInnerButOuterUnwinds(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $log = [];

        $pipeline->pipe(new LoggingMiddleware($log, 'outer'));
        $pipeline->pipe(fn () => false);
        $pipeline->pipe(new LoggingMiddleware($log, 'inner'));

        $result = $pipeline->run(function () use (&$log): void {
            $log[] = 'core';
        });

        $this->assertFalse($result);
        // Inner and core never run; outer's after still runs (unwinding)
        $this->assertSame(['outer:before', 'outer:after'], $log);
    }

    // ---------- Pipe fluent interface ----------

    public function testPipeReturnsSelf(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();

        $returned = $pipeline->pipe(new PassthroughMiddleware());

        $this->assertSame($pipeline, $returned);
    }

    // ---------- Edge cases ----------

    public function testMultipleCallablesInSequence(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $log = [];

        $pipeline->pipe(function () use (&$log) {
            $log[] = 'A';
            return true;
        });
        $pipeline->pipe(function () use (&$log) {
            $log[] = 'B';
            return true;
        });
        $pipeline->pipe(function () use (&$log) {
            $log[] = 'C';
            return true;
        });

        $pipeline->run(function () use (&$log): void {
            $log[] = 'core';
        });

        $this->assertSame(['A', 'B', 'C', 'core'], $log);
    }

    public function testSecondCallableHalts(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $log = [];

        $pipeline->pipe(function () use (&$log) {
            $log[] = 'A';
            return true;
        });
        $pipeline->pipe(function () use (&$log) {
            $log[] = 'B';
            return false;
        });
        $pipeline->pipe(function () use (&$log) {
            $log[] = 'C';
            return true;
        });

        $result = $pipeline->run(function () use (&$log): void {
            $log[] = 'core';
        });

        $this->assertSame(['A', 'B'], $log);
        $this->assertFalse($result);
    }

    public function testMiddlewareCanInspectNextResult(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $capturedResult = null;

        $pipeline->pipe(new class($capturedResult) implements \Http\MiddlewareInterface {
            private mixed $ref;
            public function __construct(&$ref) { $this->ref = &$ref; }
            public function handle($next)
            {
                $result = $next();
                $this->ref = $result;
                return $result;
            }
        });

        $pipeline->run(function (): void {});

        $this->assertTrue($capturedResult);
    }

    public function testMiddlewareCanSwallowHaltFromInner(): void
    {
        $pipeline = new \Http\MiddlewarePipeline();

        $pipeline->pipe(new class() implements \Http\MiddlewareInterface {
            public function handle($next)
            {
                $next(); // inner halts, but we ignore and return true
                return true;
            }
        });
        $pipeline->pipe(new HaltingMiddleware());

        $coreRan = false;
        $result = $pipeline->run(function () use (&$coreRan): void {
            $coreRan = true;
        });

        $this->assertFalse($coreRan, 'Inner halt still prevents core');
        $this->assertTrue($result, 'Outer middleware overrides halt result');
    }

    // ==========================================================
    //  MiddlewareRegistry
    // ==========================================================

    // ---------- register + has ----------

    public function testRegisterAndHas(): void
    {
        $registry = new \Http\MiddlewareRegistry();

        $this->assertFalse($registry->has('auth'));

        $registry->register('auth', new PassthroughMiddleware());

        $this->assertTrue($registry->has('auth'));
    }

    public function testHasReturnsFalseForUnregistered(): void
    {
        $registry = new \Http\MiddlewareRegistry();

        $this->assertFalse($registry->has('nonexistent'));
    }

    public function testRegisterOverwritesPrevious(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $first = new PassthroughMiddleware();
        $second = new HaltingMiddleware();

        $registry->register('mw', $first);
        $registry->register('mw', $second);

        $resolved = $registry->resolve('mw');
        $this->assertCount(1, $resolved);
        $this->assertSame($second, $resolved[0]);
    }

    // ---------- resolve ----------

    public function testResolveReturnsRegisteredMiddleware(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $mw = new PassthroughMiddleware();

        $registry->register('auth', $mw);

        $resolved = $registry->resolve('auth');
        $this->assertCount(1, $resolved);
        $this->assertSame($mw, $resolved[0]);
    }

    public function testResolveUnknownReturnsEmptyArray(): void
    {
        $registry = new \Http\MiddlewareRegistry();

        $resolved = $registry->resolve('unknown');

        $this->assertSame([], $resolved);
    }

    // ---------- Groups ----------

    public function testGroupRegistration(): void
    {
        $registry = new \Http\MiddlewareRegistry();

        $registry->group('admin', ['csrf', 'auth']);

        $this->assertTrue($registry->has('admin'));
    }

    public function testGroupResolvesInOrder(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $csrf = new PassthroughMiddleware();
        $auth = new HaltingMiddleware();

        $registry->register('csrf', $csrf);
        $registry->register('auth', $auth);
        $registry->group('admin', ['csrf', 'auth']);

        $resolved = $registry->resolve('admin');
        $this->assertCount(2, $resolved);
        $this->assertSame($csrf, $resolved[0]);
        $this->assertSame($auth, $resolved[1]);
    }

    public function testNestedGroupsResolveRecursively(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $a = new PassthroughMiddleware();
        $b = new PassthroughMiddleware();
        $c = new PassthroughMiddleware();

        $registry->register('a', $a);
        $registry->register('b', $b);
        $registry->register('c', $c);
        $registry->group('base', ['a', 'b']);
        $registry->group('full', ['base', 'c']);

        $resolved = $registry->resolve('full');
        $this->assertCount(3, $resolved);
        $this->assertSame($a, $resolved[0]);
        $this->assertSame($b, $resolved[1]);
        $this->assertSame($c, $resolved[2]);
    }

    public function testGroupWithUnknownEntrySkipsMissing(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $known = new PassthroughMiddleware();

        $registry->register('known', $known);
        $registry->group('mixed', ['known', 'missing']);

        $resolved = $registry->resolve('mixed');
        $this->assertCount(1, $resolved);
        $this->assertSame($known, $resolved[0]);
    }

    // ---------- resolveAll ----------

    public function testResolveAllWithStringNames(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $csrf = new PassthroughMiddleware();
        $auth = new PassthroughMiddleware();

        $registry->register('csrf', $csrf);
        $registry->register('auth', $auth);

        $resolved = $registry->resolveAll(['csrf', 'auth']);
        $this->assertCount(2, $resolved);
        $this->assertSame($csrf, $resolved[0]);
        $this->assertSame($auth, $resolved[1]);
    }

    public function testResolveAllWithInstances(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $mw = new PassthroughMiddleware();

        $resolved = $registry->resolveAll([$mw]);
        $this->assertCount(1, $resolved);
        $this->assertSame($mw, $resolved[0]);
    }

    public function testResolveAllWithCallables(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $fn = fn () => true;

        $resolved = $registry->resolveAll([$fn]);
        $this->assertCount(1, $resolved);
        $this->assertSame($fn, $resolved[0]);
    }

    public function testResolveAllWithMixedTypes(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $named = new PassthroughMiddleware();
        $instance = new HaltingMiddleware();
        $callable = fn () => true;

        $registry->register('named', $named);

        $resolved = $registry->resolveAll(['named', $instance, $callable]);
        $this->assertCount(3, $resolved);
        $this->assertSame($named, $resolved[0]);
        $this->assertSame($instance, $resolved[1]);
        $this->assertSame($callable, $resolved[2]);
    }

    public function testResolveAllExpandsGroups(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $a = new PassthroughMiddleware();
        $b = new PassthroughMiddleware();

        $registry->register('a', $a);
        $registry->register('b', $b);
        $registry->group('bundle', ['a', 'b']);

        $resolved = $registry->resolveAll(['bundle']);
        $this->assertCount(2, $resolved);
        $this->assertSame($a, $resolved[0]);
        $this->assertSame($b, $resolved[1]);
    }

    public function testResolveAllEmptyArray(): void
    {
        $registry = new \Http\MiddlewareRegistry();

        $this->assertSame([], $registry->resolveAll([]));
    }

    // ---------- Register callable ----------

    public function testRegisterCallableMiddleware(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $fn = fn () => true;

        $registry->register('guard', $fn);

        $this->assertTrue($registry->has('guard'));
        $resolved = $registry->resolve('guard');
        $this->assertCount(1, $resolved);
        $this->assertSame($fn, $resolved[0]);
    }

    // ==========================================================
    //  Pipeline + Registry Integration
    // ==========================================================

    public function testRegistryResolvedMiddlewareWorksInPipeline(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $log = [];

        $registry->register('logger', new LoggingMiddleware($log, 'MW'));

        $pipeline = new \Http\MiddlewarePipeline();
        foreach ($registry->resolve('logger') as $mw) {
            $pipeline->pipe($mw);
        }

        $pipeline->run(function () use (&$log): void {
            $log[] = 'handler';
        });

        $this->assertSame(['MW:before', 'handler', 'MW:after'], $log);
    }

    public function testGroupResolvedIntoPipeline(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $log = [];

        $registry->register('first', new LoggingMiddleware($log, 'A'));
        $registry->register('second', new LoggingMiddleware($log, 'B'));
        $registry->group('all', ['first', 'second']);

        $pipeline = new \Http\MiddlewarePipeline();
        foreach ($registry->resolveAll(['all']) as $mw) {
            $pipeline->pipe($mw);
        }

        $pipeline->run(function () use (&$log): void {
            $log[] = 'core';
        });

        $this->assertSame(['A:before', 'B:before', 'core', 'B:after', 'A:after'], $log);
    }

    public function testResolveAllMixedIntoPipeline(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $log = [];

        $registry->register('named', new LoggingMiddleware($log, 'named'));

        $pipeline = new \Http\MiddlewarePipeline();
        $items = $registry->resolveAll([
            'named',
            function () use (&$log) {
                $log[] = 'callable';
                return true;
            },
        ]);
        foreach ($items as $mw) {
            $pipeline->pipe($mw);
        }

        $pipeline->run(function () use (&$log): void {
            $log[] = 'core';
        });

        $this->assertSame(['named:before', 'callable', 'core', 'named:after'], $log);
    }

    public function testPipelineWithHaltingMiddlewareFromRegistry(): void
    {
        $registry = new \Http\MiddlewareRegistry();
        $log = [];

        $registry->register('pass', new LoggingMiddleware($log, 'pass'));
        $registry->register('block', new HaltingMiddleware());
        $registry->register('after', new LoggingMiddleware($log, 'after'));

        $pipeline = new \Http\MiddlewarePipeline();
        foreach ($registry->resolveAll(['pass', 'block', 'after']) as $mw) {
            $pipeline->pipe($mw);
        }

        $coreRan = false;
        $result = $pipeline->run(function () use (&$coreRan): void {
            $coreRan = true;
        });

        $this->assertFalse($coreRan);
        $this->assertFalse($result);
        // 'after' middleware never starts; 'pass' unwinds its after phase
        $this->assertSame(['pass:before', 'pass:after'], $log);
    }
}

// ==========================================================
//  Test Doubles
// ==========================================================

/**
 * Middleware that always continues the pipeline.
 */
class PassthroughMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        return $next();
    }
}

/**
 * Middleware that always halts the pipeline.
 */
class HaltingMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        return false;
    }
}

/**
 * Middleware that logs before/after markers to a shared array.
 * Used to verify execution order in pipeline tests.
 */
class LoggingMiddleware implements \Http\MiddlewareInterface
{
    /** @var array */
    private $log;

    /** @var string */
    private $label;

    public function __construct(&$log, $label)
    {
        $this->log = &$log;
        $this->label = $label;
    }

    public function handle($next)
    {
        $this->log[] = $this->label . ':before';
        $result = $next();
        $this->log[] = $this->label . ':after';
        return $result;
    }
}
