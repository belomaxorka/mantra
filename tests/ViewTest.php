<?php declare(strict_types=1);

/**
 * View Tests
 * Tests for View class template rendering, layout wrapping, and output buffering
 *
 * @covers View
 */
class ViewTest extends MantraTestCase
{
    private $testDir;
    private $themePath;
    private $modulePath;
    private $originalTheme;
    private $originalUrl;

    protected function setUp(): void
    {
        parent::setUp();

        // Use actual theme/module directories with unique test names
        $this->themePath = MANTRA_THEMES . '/test-theme-' . time();
        $this->modulePath = MANTRA_MODULES . '/test-module-' . time();

        // Initialize Application with minimal setup for View tests
        $this->resetHookManager();

        $this->setupTestEnvironment();

        // Save originals and switch to test theme
        $this->originalTheme = config('theme.active');
        $this->originalUrl = config('site.url');
        config()->set('theme.active', basename($this->themePath));
    }

    protected function tearDown(): void
    {
        // Restore config before cleanup (always runs, even on test failure)
        config()->set('theme.active', $this->originalTheme);
        config()->set('site.url', $this->originalUrl);

        // Clean up test theme and module
        $this->removeDirectory($this->themePath);
        $this->removeDirectory($this->modulePath);

        parent::tearDown();
    }

    /**
     * Reset HookManager to a clean state.
     * Call before any test that registers hooks to prevent leakage.
     */
    private function resetHookManager(): void
    {
        $app = Application::getInstance();

        // Use reflection to set hookManager since it's private
        $reflection = new ReflectionClass($app);
        $hookManagerProperty = $reflection->getProperty('hookManager');
        $hookManagerProperty->setValue($app, new HookManager());
    }

    private function setupTestEnvironment(): void
    {
        // Create directory structure
        $dirs = [
            $this->themePath . '/templates',
            $this->themePath . '/templates/partials',
            $this->themePath . '/assets',
            $this->modulePath . '/views',
            $this->modulePath . '/views/partials',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
        }

        // Create test templates
        $this->createTestTemplates();
    }

    private function createTestTemplates(): void
    {
        // Theme layout
        file_put_contents($this->themePath . '/templates/layout.php',
            '<!DOCTYPE html><html><body><?php echo $content; ?></body></html>');

        // Theme template
        file_put_contents($this->themePath . '/templates/page.php',
            '<h1><?php echo $title; ?></h1>');

        // Module template
        file_put_contents($this->modulePath . '/views/admin.php',
            '<div class="admin"><?php echo $message; ?></div>');

        // Theme partial
        file_put_contents($this->themePath . '/templates/partials/sidebar.php',
            '<aside><?php echo isset($content) ? $content : "Sidebar"; ?></aside>');

        // Module partial
        file_put_contents($this->modulePath . '/views/partials/menu.php',
            '<nav><?php echo isset($items) ? $items : "Menu"; ?></nav>');
    }

    public function testBasicTemplateRendering(): void
    {
        $view = new View();
        $output = $view->fetch('page', ['title' => 'Test Page']);

        // Should contain the title
        $this->assertStringContainsString(
            '<h1>Test Page</h1>',
            $output,
            'Template renders with data',
        );

        // Should be wrapped in layout
        $this->assertStringContainsString(
            '<!DOCTYPE html>',
            $output,
            'Template is wrapped in layout',
        );
    }

    public function testLayoutWrapping(): void
    {
        $view = new View();

        // Theme template should be wrapped
        $output = $view->fetch('page', ['title' => 'Test']);
        $this->assertStringContainsString(
            '<!DOCTYPE html>',
            $output,
            'Theme template is wrapped in layout',
        );
    }

    public function testModuleTemplateNoLayout(): void
    {
        $testModuleName = basename($this->modulePath);

        $view = new View();

        // Explicit module syntax
        $output = $view->fetch($testModuleName . ':admin', ['message' => 'Admin Panel']);
        $this->assertStringContainsString(
            '<div class="admin">Admin Panel</div>',
            $output,
            'Module template renders with explicit syntax',
        );
        $this->assertStringNotContainsString(
            '<!DOCTYPE html>',
            $output,
            'Module template (explicit) is NOT wrapped in layout',
        );

        // _module parameter syntax
        $output2 = $view->fetch('admin', ['_module' => $testModuleName, 'message' => 'Admin']);
        $this->assertStringContainsString(
            '<div class="admin">Admin</div>',
            $output2,
            'Module template renders with _module parameter',
        );
        $this->assertStringNotContainsString(
            '<!DOCTYPE html>',
            $output2,
            'Module template (_module) is NOT wrapped in layout',
        );
    }

    public function testContentVariableProtection(): void
    {
        // Backup original layout
        $layoutPath = $this->themePath . '/templates/layout.php';
        $originalLayout = file_get_contents($layoutPath);

        // Create a layout that uses $content
        file_put_contents($layoutPath,
            '<html><body><main><?php echo $content; ?></main></body></html>');

        // Create a template
        file_put_contents($this->themePath . '/templates/test.php',
            '<p>Template content</p>');

        $view = new View();

        // Pass 'content' in data - should NOT override rendered content
        $output = $view->fetch('test', ['content' => 'USER DATA']);

        $this->assertStringContainsString(
            '<p>Template content</p>',
            $output,
            'Rendered template content is preserved',
        );
        $this->assertStringContainsString(
            '<main>',
            $output,
            'Layout renders correctly',
        );
        $this->assertStringNotContainsString(
            'USER DATA',
            $output,
            'User data "content" does not override rendered content',
        );

        // Restore original layout
        file_put_contents($layoutPath, $originalLayout);
    }

    public function testAssetUrlGeneration(): void
    {
        // Test with trailing slash
        config()->set('site.url', 'http://example.com/');
        $view = new View();
        $url = $view->asset('css/style.css');

        $this->assertStringNotContainsString(
            '//themes',
            $url,
            'No double slash in URL with trailing slash base',
        );
        $this->assertStringContainsString(
            'http://example.com/themes',
            $url,
            'Asset URL is correctly formed',
        );

        // Test without trailing slash
        config()->set('site.url', 'http://example.com');
        $view2 = new View();
        $url2 = $view2->asset('css/style.css');

        $this->assertStringContainsString(
            'http://example.com/themes',
            $url2,
            'Asset URL works without trailing slash',
        );
    }

    public function testEscapeMethod(): void
    {
        $view = new View();

        $escaped = $view->escape('<script>alert("xss")</script>');
        $this->assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            $escaped,
            'HTML is properly escaped',
        );

        $escapedArray = $view->escape(['<b>test</b>', '<i>test</i>']);
        $this->assertSame(
            '&lt;b&gt;test&lt;/b&gt;',
            $escapedArray[0],
            'Array values are escaped',
        );

        // Test alias
        $aliasEscaped = $view->e('<div>');
        $this->assertSame(
            '&lt;div&gt;',
            $aliasEscaped,
            'e() alias works correctly',
        );
    }

    public function testPartialRendering(): void
    {
        $testModuleName = basename($this->modulePath);

        $view = new View();

        // Theme partial
        $partial = $view->partial('sidebar');
        $this->assertStringContainsString(
            '<aside>Sidebar</aside>',
            $partial,
            'Theme partial renders',
        );

        // Theme partial with params
        $partial2 = $view->partial('sidebar', ['content' => 'Custom']);
        $this->assertStringContainsString(
            '<aside>Custom</aside>',
            $partial2,
            'Theme partial renders with parameters',
        );

        // Module partial
        $partial3 = $view->partial($testModuleName . ':menu');
        $this->assertStringContainsString(
            '<nav>Menu</nav>',
            $partial3,
            'Module partial renders',
        );

        // Non-existent partial
        $partial4 = $view->partial('nonexistent');
        $this->assertStringContainsString(
            '<!-- Partial not found',
            $partial4,
            'Non-existent partial returns comment',
        );
    }

    public function testOutputBufferingErrorHandling(): void
    {
        // Create a template that throws an exception
        file_put_contents($this->themePath . '/templates/error.php',
            '<?php throw new Exception("Template error"); ?>');

        $view = new View();

        $exceptionThrown = false;
        try {
            $view->fetch('error', []);
        } catch (Exception $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue(
            $exceptionThrown,
            'Exception is properly thrown from template',
        );

        // Verify output buffer is clean
        $level = ob_get_level();
        $this->assertTrue(
            $level >= 0,
            'Output buffer level is valid after exception',
        );
    }

    public function testTemplateNotFound(): void
    {
        $view = new View();

        $exceptionThrown = false;
        $exceptionMessage = '';
        try {
            $view->fetch('nonexistent-template', []);
        } catch (Exception $e) {
            $exceptionThrown = true;
            $exceptionMessage = $e->getMessage();
        }

        $this->assertTrue(
            $exceptionThrown,
            'Exception thrown for non-existent template',
        );
        $this->assertStringContainsString(
            'Template not found',
            $exceptionMessage,
            'Exception message is descriptive',
        );
    }

    public function testNestedOutputBuffering(): void
    {
        // Create nested template structure
        file_put_contents($this->themePath . '/templates/outer.php',
            '<outer><?php echo $view->fetch("inner", array("text" => "nested")); ?></outer>');
        file_put_contents($this->themePath . '/templates/inner.php',
            '<inner><?php echo $text; ?></inner>');

        $view = new View();
        $output = $view->fetch('outer', ['view' => $view]);

        $this->assertStringContainsString('<outer>', $output, 'Nested templates render correctly (outer tag)');
        $this->assertStringContainsString('<inner>nested</inner>', $output, 'Nested templates render correctly (inner tag)');
    }

    public function testOutputBufferingMultipleLevels(): void
    {
        // Create template that uses partials (which also use output buffering)
        file_put_contents($this->themePath . '/templates/with-partial.php',
            '<page><?php echo $view->partial("sidebar"); ?></page>');

        $view = new View();
        $levelBefore = ob_get_level();
        $output = $view->fetch('with-partial', ['view' => $view]);
        $levelAfter = ob_get_level();

        $this->assertSame(
            $levelBefore,
            $levelAfter,
            'Output buffer level is restored after nested buffering',
        );
        $this->assertStringContainsString('<page>', $output, 'Nested buffering produces correct output (page tag)');
        $this->assertStringContainsString('<aside>', $output, 'Nested buffering produces correct output (aside tag)');
    }

    public function testPartialExceptionHandling(): void
    {
        // Create partial that throws exception
        file_put_contents($this->themePath . '/templates/partials/broken.php',
            '<?php throw new Exception("Partial error"); ?>');

        $view = new View();
        $levelBefore = ob_get_level();
        $output = $view->partial('broken');
        $levelAfter = ob_get_level();

        $this->assertStringContainsString(
            '<!-- Partial error:',
            $output,
            'Partial exception returns error comment',
        );
        $this->assertSame(
            $levelBefore,
            $levelAfter,
            'Output buffer is cleaned after partial exception',
        );
    }

    public function testLayoutExceptionHandling(): void
    {
        // Backup original layout
        $layoutPath = $this->themePath . '/templates/layout.php';
        $originalLayout = file_get_contents($layoutPath);

        // Create layout that throws exception
        file_put_contents($layoutPath,
            '<?php throw new Exception("Layout error"); ?>');
        file_put_contents($this->themePath . '/templates/simple.php',
            '<p>Content</p>');

        $view = new View();
        $exceptionThrown = false;
        $levelBefore = ob_get_level();

        try {
            $view->fetch('simple', []);
        } catch (Exception $e) {
            $exceptionThrown = true;
        }

        $levelAfter = ob_get_level();

        $this->assertTrue(
            $exceptionThrown,
            'Layout exception is thrown',
        );
        $this->assertSame(
            $levelBefore,
            $levelAfter,
            'Output buffer is cleaned after layout exception',
        );

        // Restore original layout
        file_put_contents($layoutPath, $originalLayout);
    }

    public function testViewRenderHook(): void
    {
        // Reset hooks to prevent leakage from/to other tests
        $this->resetHookManager();

        file_put_contents($this->themePath . '/templates/hooktest.php',
            '<p>Original</p>');

        // Register hook to modify content
        $app = Application::getInstance();
        $app->hooks()->register('view.render', fn($content) => str_replace('Original', 'Modified', $content));

        $view = new View();
        $output = $view->fetch('hooktest', []);

        $this->assertStringContainsString('Modified', $output, 'view.render hook modifies content');
        $this->assertStringNotContainsString('Original', $output, 'view.render hook removes original content');

        // Clean up hooks so they don't leak to subsequent tests
        $this->resetHookManager();
    }

    public function testMultipleHooks(): void
    {
        // Reset hooks to prevent leakage from/to other tests
        $this->resetHookManager();

        file_put_contents($this->themePath . '/templates/multihook.php',
            '<p>Text</p>');

        // Register multiple hooks with different priorities
        $app = Application::getInstance();
        $app->hooks()->register('view.render', fn($content) => str_replace('Text', 'Step1', $content), 10);
        $app->hooks()->register('view.render', fn($content) => str_replace('Step1', 'Step2', $content), 20);

        $view = new View();
        $output = $view->fetch('multihook', []);

        $this->assertStringContainsString(
            'Step2',
            $output,
            'Multiple hooks execute in order',
        );

        // Clean up hooks so they don't leak to subsequent tests
        $this->resetHookManager();
    }

    public function testEmptyTemplate(): void
    {
        file_put_contents($this->themePath . '/templates/empty.php', '');

        $view = new View();
        $output = $view->fetch('empty', []);

        // Just check that layout wrapper is present
        $this->assertStringContainsString('<!DOCTYPE html>', $output, 'Empty template renders with layout (doctype)');
        $this->assertStringContainsString('<html>', $output, 'Empty template renders with layout (html tag)');
        $this->assertStringContainsString('<body>', $output, 'Empty template renders with layout (body tag)');
    }

    public function testTemplateWithOnlyWhitespace(): void
    {
        file_put_contents($this->themePath . '/templates/whitespace.php', "   \n\n   \t  ");

        $view = new View();
        $output = $view->fetch('whitespace', []);

        $this->assertNotEmpty(
            $output,
            'Whitespace template produces output',
        );
    }

    public function testRenderMethodEchoes(): void
    {
        file_put_contents($this->themePath . '/templates/echo-test.php',
            '<p>Echo test</p>');

        $view = new View();

        ob_start();
        $view->render('echo-test', []);
        $output = ob_get_clean();

        $this->assertStringContainsString(
            '<p>Echo test</p>',
            $output,
            'render() method echoes output',
        );
    }

    public function testEscapeEdgeCases(): void
    {
        $view = new View();

        // Null value
        $escaped = $view->escape(null);
        $this->assertSame(
            '',
            $escaped,
            'Null is escaped to empty string',
        );

        // Nested arrays
        $nested = ['a' => ['b' => '<script>']];
        $escapedNested = $view->escape($nested);
        $this->assertSame(
            '&lt;script&gt;',
            $escapedNested['a']['b'],
            'Nested arrays are escaped recursively',
        );

        // Already escaped string (double escaping)
        $alreadyEscaped = '&lt;div&gt;';
        $doubleEscaped = $view->escape($alreadyEscaped);
        $this->assertSame(
            '&amp;lt;div&amp;gt;',
            $doubleEscaped,
            'Already escaped strings are double-escaped',
        );

        // Empty string
        $emptyEscaped = $view->escape('');
        $this->assertSame(
            '',
            $emptyEscaped,
            'Empty string remains empty',
        );
    }

    public function testAssetUrlEdgeCases(): void
    {
        config()->set('site.url', 'http://example.com');

        $view = new View();

        // Empty path
        $url1 = $view->asset('');
        $this->assertStringNotContainsString(
            '//assets',
            $url1,
            'Empty path does not create double slash',
        );

        // Path with leading slash
        $url2 = $view->asset('/css/style.css');
        $this->assertStringNotContainsString(
            '//css',
            $url2,
            'Leading slash is handled correctly',
        );

        // Path with multiple slashes
        $url3 = $view->asset('css//style.css');
        $this->assertStringContainsString(
            'css//style.css',
            $url3,
            'Multiple slashes in path are preserved',
        );
    }

    public function testTemplateWithUndefinedVariable(): void
    {
        // Template references undefined variable
        file_put_contents($this->themePath . '/templates/undefined.php',
            '<p><?php echo isset($undefined) ? $undefined : "default"; ?></p>');

        $view = new View();
        $output = $view->fetch('undefined', []);

        $this->assertStringContainsString(
            'default',
            $output,
            'Template handles undefined variables gracefully',
        );
    }

    public function testNestedPartials(): void
    {
        // Partial that includes another partial
        file_put_contents($this->themePath . '/templates/partials/outer.php',
            '<div><?php echo $view->partial("sidebar"); ?></div>');

        $view = new View();
        $output = $view->partial('outer', ['view' => $view]);

        $this->assertStringContainsString('<div>', $output, 'Nested partials render correctly (div tag)');
        $this->assertStringContainsString('<aside>', $output, 'Nested partials render correctly (aside tag)');
    }

    public function testThemeOverridesModulePartial(): void
    {
        $testModuleName = basename($this->modulePath);

        // Create theme override for module partial
        $overrideDir = $this->themePath . '/templates/partials/' . $testModuleName;
        if (!is_dir($overrideDir)) {
            mkdir($overrideDir, 0o755, true);
        }
        file_put_contents($overrideDir . '/menu.php',
            '<nav>Theme Override Menu</nav>');

        $view = new View();

        // Module partial should be overridden by theme
        $output = $view->partial($testModuleName . ':menu');
        $this->assertStringContainsString(
            'Theme Override Menu',
            $output,
            'Theme partial overrides module partial',
        );
        $this->assertStringNotContainsString(
            '<nav>Menu</nav>',
            $output,
            'Original module partial is not used when theme override exists',
        );

        // Clean up override
        unlink($overrideDir . '/menu.php');
        rmdir($overrideDir);

        // Without override, module partial should render
        $output2 = $view->partial($testModuleName . ':menu');
        $this->assertStringContainsString(
            '<nav>Menu</nav>',
            $output2,
            'Module partial renders when no theme override',
        );
    }

    public function testModulePartialNonexistentModule(): void
    {
        $view = new View();

        $output = $view->partial('nonexistent-module:some-partial');
        $this->assertStringContainsString(
            '<!-- Partial not found: nonexistent-module:some-partial',
            $output,
            'Nonexistent module partial returns not-found comment',
        );
    }

    public function testAbortRendersThemeTemplate(): void
    {
        // Create a 404 template
        file_put_contents($this->themePath . '/templates/404.php',
            '<div class="error"><?php echo $code; ?> - <?php echo e($title); ?></div>');

        // Capture abort() output
        ob_start();
        abort(404, 'test message');
        $output = ob_get_clean();

        $this->assertStringContainsString(
            '<div class="error">',
            $output,
            'abort() renders theme 404 template',
        );
        $this->assertStringContainsString(
            '<!DOCTYPE html>',
            $output,
            'abort() output is wrapped in layout',
        );
        $this->assertSame(
            404,
            http_response_code(),
            'abort() sets correct HTTP status code',
        );
    }

    public function testAbortFallbackWithoutTemplate(): void
    {
        // No 403 template exists -- should fall back to plain HTML
        ob_start();
        abort(403, 'Access denied');
        $output = ob_get_clean();

        $this->assertStringContainsString(
            '403',
            $output,
            'abort() fallback contains status code',
        );
        $this->assertStringContainsString(
            'Forbidden',
            $output,
            'abort() fallback contains status title',
        );
        $this->assertStringContainsString(
            'Access denied',
            $output,
            'abort() fallback contains custom message',
        );
        $this->assertSame(
            403,
            http_response_code(),
            'abort() sets correct HTTP status for fallback',
        );
    }
}
