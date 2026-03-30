<?php
/**
 * View Tests
 * Tests for View class template rendering, layout wrapping, and output buffering
 */

require_once __DIR__ . '/../core/bootstrap.php';

class ViewTest {
    private $testDir;
    private $themePath;
    private $modulePath;
    private $results = array();

    public function __construct() {
        // Use actual theme/module directories with unique test names
        $this->themePath = MANTRA_THEMES . '/test-theme-' . time();
        $this->modulePath = MANTRA_MODULES . '/test-module-' . time();

        // Initialize Application with minimal setup for View tests
        $this->initializeApplication();

        $this->setupTestEnvironment();
    }

    private function initializeApplication() {
        // View class depends on Application::getInstance()->hooks()
        // We need to initialize HookManager
        $this->resetHookManager();
    }

    /**
     * Reset HookManager to a clean state.
     * Call before any test that registers hooks to prevent leakage.
     */
    private function resetHookManager() {
        $app = Application::getInstance();

        // Use reflection to set hookManager since it's private
        $reflection = new ReflectionClass($app);
        $hookManagerProperty = $reflection->getProperty('hookManager');
        $hookManagerProperty->setAccessible(true);
        $hookManagerProperty->setValue($app, new HookManager());
    }

    public function __destruct() {
        // Clean up test theme and module
        $this->removeDirectory($this->themePath);
        $this->removeDirectory($this->modulePath);
    }

    private function setupTestEnvironment() {
        // Create directory structure
        $dirs = array(
            $this->themePath . '/templates',
            $this->themePath . '/templates/partials',
            $this->themePath . '/assets',
            $this->modulePath . '/views',
            $this->modulePath . '/views/partials'
        );

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Create test templates
        $this->createTestTemplates();
    }

    private function createTestTemplates() {
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
            '<aside><?php echo $content ?? "Sidebar"; ?></aside>');

        // Module partial
        file_put_contents($this->modulePath . '/views/partials/menu.php',
            '<nav><?php echo $items ?? "Menu"; ?></nav>');
    }

    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function assert($condition, $message) {
        if ($condition) {
            $this->results[] = array('status' => 'PASS', 'message' => $message);
            echo "✓ $message\n";
        } else {
            $this->results[] = array('status' => 'FAIL', 'message' => $message);
            echo "✗ $message\n";
        }
    }

    public function run() {
        echo "Running View Tests...\n\n";

        // Basic functionality
        $this->testBasicTemplateRendering();
        $this->testLayoutWrapping();
        $this->testModuleTemplateNoLayout();
        $this->testContentVariableProtection();
        $this->testAssetUrlGeneration();
        $this->testEscapeMethod();
        $this->testPartialRendering();
        $this->testOutputBufferingErrorHandling();
        $this->testTemplateNotFound();

        // Extended output buffering tests
        $this->testNestedOutputBuffering();
        $this->testOutputBufferingMultipleLevels();
        $this->testPartialExceptionHandling();
        $this->testLayoutExceptionHandling();

        // Hook integration tests
        $this->testViewRenderHook();
        $this->testMultipleHooks();

        // Edge cases
        $this->testEmptyTemplate();
        $this->testTemplateWithOnlyWhitespace();
        $this->testRenderMethodEchoes();
        $this->testEscapeEdgeCases();
        $this->testAssetUrlEdgeCases();
        $this->testTemplateWithUndefinedVariable();
        $this->testNestedPartials();
        $this->testThemeOverridesModulePartial();
        $this->testModulePartialNonexistentModule();
        $this->testAbortRendersThemeTemplate();
        $this->testAbortFallbackWithoutTemplate();

        $this->printSummary();
    }

    private function testBasicTemplateRendering() {
        echo "Test: Basic template rendering\n";

        // Override config to use our test theme
        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        config()->set('theme.active', $testThemeName);

        $view = new View();
        $output = $view->fetch('page', array('title' => 'Test Page'));

        // Should contain the title
        $this->assert(
            str_contains($output, '<h1>Test Page</h1>'),
            'Template renders with data'
        );

        // Should be wrapped in layout
        $this->assert(
            str_contains($output, '<!DOCTYPE html>'),
            'Template is wrapped in layout'
        );

        // Restore config
        config()->set('theme.active', $originalTheme);
    }

    private function testLayoutWrapping() {
        echo "\nTest: Layout wrapping logic\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        config()->set('theme.active', $testThemeName);

        $view = new View();

        // Theme template should be wrapped
        $output = $view->fetch('page', array('title' => 'Test'));
        $this->assert(
            str_contains($output, '<!DOCTYPE html>'),
            'Theme template is wrapped in layout'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testModuleTemplateNoLayout() {
        echo "\nTest: Module template without layout\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        $testModuleName = basename($this->modulePath);
        config()->set('theme.active', $testThemeName);

        $view = new View();

        // Explicit module syntax
        $output = $view->fetch($testModuleName . ':admin', array('message' => 'Admin Panel'));
        $this->assert(
            str_contains($output, '<div class="admin">Admin Panel</div>'),
            'Module template renders with explicit syntax'
        );
        $this->assert(
            !str_contains($output, '<!DOCTYPE html>'),
            'Module template (explicit) is NOT wrapped in layout'
        );

        // _module parameter syntax
        $output2 = $view->fetch('admin', array('_module' => $testModuleName, 'message' => 'Admin'));
        $this->assert(
            str_contains($output2, '<div class="admin">Admin</div>'),
            'Module template renders with _module parameter'
        );
        $this->assert(
            !str_contains($output2, '<!DOCTYPE html>'),
            'Module template (_module) is NOT wrapped in layout'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testContentVariableProtection() {
        echo "\nTest: Content variable protection in layout\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        // Backup original layout
        $layoutPath = $this->themePath . '/templates/layout.php';
        $originalLayout = file_get_contents($layoutPath);

        // Create a layout that uses $content
        file_put_contents($layoutPath,
            '<html><body><main><?php echo $content; ?></main></body></html>');

        // Create a template
        file_put_contents($this->themePath . '/templates/test.php',
            '<p>Template content</p>');

        config()->set('theme.active', $testThemeName);

        $view = new View();

        // Pass 'content' in data - should NOT override rendered content
        $output = $view->fetch('test', array('content' => 'USER DATA'));

        $this->assert(
            str_contains($output, '<p>Template content</p>'),
            'Rendered template content is preserved'
        );
        $this->assert(
            str_contains($output, '<main>'),
            'Layout renders correctly'
        );
        $this->assert(
            !str_contains($output, 'USER DATA'),
            'User data "content" does not override rendered content'
        );

        // Restore original layout
        file_put_contents($layoutPath, $originalLayout);

        config()->set('theme.active', $originalTheme);
    }

    private function testAssetUrlGeneration() {
        echo "\nTest: Asset URL generation\n";

        $originalTheme = config('theme.active');
        $originalUrl = config('site.url');
        $testThemeName = basename($this->themePath);

        config()->set('theme.active', $testThemeName);

        // Test with trailing slash
        config()->set('site.url', 'http://example.com/');
        $view = new View();
        $url = $view->asset('css/style.css');

        $this->assert(
            !str_contains($url, '//themes'),
            'No double slash in URL with trailing slash base'
        );
        $this->assert(
            str_contains($url, 'http://example.com/themes'),
            'Asset URL is correctly formed'
        );

        // Test without trailing slash
        config()->set('site.url', 'http://example.com');
        $view2 = new View();
        $url2 = $view2->asset('css/style.css');

        $this->assert(
            str_contains($url2, 'http://example.com/themes'),
            'Asset URL works without trailing slash'
        );

        config()->set('theme.active', $originalTheme);
        config()->set('site.url', $originalUrl);
    }

    private function testEscapeMethod() {
        echo "\nTest: Escape method\n";

        $view = new View();

        $escaped = $view->escape('<script>alert("xss")</script>');
        $this->assert(
            $escaped === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            'HTML is properly escaped'
        );

        $escapedArray = $view->escape(array('<b>test</b>', '<i>test</i>'));
        $this->assert(
            $escapedArray[0] === '&lt;b&gt;test&lt;/b&gt;',
            'Array values are escaped'
        );

        // Test alias
        $aliasEscaped = $view->e('<div>');
        $this->assert(
            $aliasEscaped === '&lt;div&gt;',
            'e() alias works correctly'
        );
    }

    private function testPartialRendering() {
        echo "\nTest: Partial rendering\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        $testModuleName = basename($this->modulePath);
        config()->set('theme.active', $testThemeName);

        $view = new View();

        // Theme partial
        $partial = $view->partial('sidebar');
        $this->assert(
            str_contains($partial, '<aside>Sidebar</aside>'),
            'Theme partial renders'
        );

        // Theme partial with params
        $partial2 = $view->partial('sidebar', array('content' => 'Custom'));
        $this->assert(
            str_contains($partial2, '<aside>Custom</aside>'),
            'Theme partial renders with parameters'
        );

        // Module partial
        $partial3 = $view->partial($testModuleName . ':menu');
        $this->assert(
            str_contains($partial3, '<nav>Menu</nav>'),
            'Module partial renders'
        );

        // Non-existent partial
        $partial4 = $view->partial('nonexistent');
        $this->assert(
            str_contains($partial4, '<!-- Partial not found'),
            'Non-existent partial returns comment'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testOutputBufferingErrorHandling() {
        echo "\nTest: Output buffering error handling\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        // Create a template that throws an exception
        file_put_contents($this->themePath . '/templates/error.php',
            '<?php throw new Exception("Template error"); ?>');

        config()->set('theme.active', $testThemeName);

        $view = new View();

        $exceptionThrown = false;
        try {
            $view->fetch('error', array());
        } catch (Exception $e) {
            $exceptionThrown = true;
        }

        $this->assert(
            $exceptionThrown,
            'Exception is properly thrown from template'
        );

        // Verify output buffer is clean
        $level = ob_get_level();
        $this->assert(
            $level >= 0,
            'Output buffer level is valid after exception'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testTemplateNotFound() {
        echo "\nTest: Template not found\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        config()->set('theme.active', $testThemeName);

        $view = new View();

        $exceptionThrown = false;
        $exceptionMessage = '';
        try {
            $view->fetch('nonexistent-template', array());
        } catch (Exception $e) {
            $exceptionThrown = true;
            $exceptionMessage = $e->getMessage();
        }

        $this->assert(
            $exceptionThrown,
            'Exception thrown for non-existent template'
        );
        $this->assert(
            str_contains($exceptionMessage, 'Template not found'),
            'Exception message is descriptive'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testNestedOutputBuffering() {
        echo "\nTest: Nested output buffering\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        // Create nested template structure
        file_put_contents($this->themePath . '/templates/outer.php',
            '<outer><?php echo $view->fetch("inner", array("text" => "nested")); ?></outer>');
        file_put_contents($this->themePath . '/templates/inner.php',
            '<inner><?php echo $text; ?></inner>');

        config()->set('theme.active', $testThemeName);

        $view = new View();
        $output = $view->fetch('outer', array('view' => $view));

        $this->assert(
            str_contains($output, '<outer>') && str_contains($output, '<inner>nested</inner>'),
            'Nested templates render correctly'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testOutputBufferingMultipleLevels() {
        echo "\nTest: Multiple output buffering levels\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        config()->set('theme.active', $testThemeName);

        // Create template that uses partials (which also use output buffering)
        file_put_contents($this->themePath . '/templates/with-partial.php',
            '<page><?php echo $view->partial("sidebar"); ?></page>');

        $view = new View();
        $levelBefore = ob_get_level();
        $output = $view->fetch('with-partial', array('view' => $view));
        $levelAfter = ob_get_level();

        $this->assert(
            $levelBefore === $levelAfter,
            'Output buffer level is restored after nested buffering'
        );
        $this->assert(
            str_contains($output, '<page>') && str_contains($output, '<aside>'),
            'Nested buffering produces correct output'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testPartialExceptionHandling() {
        echo "\nTest: Partial exception handling\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        // Create partial that throws exception
        file_put_contents($this->themePath . '/templates/partials/broken.php',
            '<?php throw new Exception("Partial error"); ?>');

        config()->set('theme.active', $testThemeName);

        $view = new View();
        $levelBefore = ob_get_level();
        $output = $view->partial('broken');
        $levelAfter = ob_get_level();

        $this->assert(
            str_contains($output, '<!-- Partial error:'),
            'Partial exception returns error comment'
        );
        $this->assert(
            $levelBefore === $levelAfter,
            'Output buffer is cleaned after partial exception'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testLayoutExceptionHandling() {
        echo "\nTest: Layout exception handling\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        // Backup original layout
        $layoutPath = $this->themePath . '/templates/layout.php';
        $originalLayout = file_get_contents($layoutPath);

        // Create layout that throws exception
        file_put_contents($layoutPath,
            '<?php throw new Exception("Layout error"); ?>');
        file_put_contents($this->themePath . '/templates/simple.php',
            '<p>Content</p>');

        config()->set('theme.active', $testThemeName);

        $view = new View();
        $exceptionThrown = false;
        $levelBefore = ob_get_level();

        try {
            $view->fetch('simple', array());
        } catch (Exception $e) {
            $exceptionThrown = true;
        }

        $levelAfter = ob_get_level();

        $this->assert(
            $exceptionThrown,
            'Layout exception is thrown'
        );
        $this->assert(
            $levelBefore === $levelAfter,
            'Output buffer is cleaned after layout exception'
        );

        // Restore original layout
        file_put_contents($layoutPath, $originalLayout);

        config()->set('theme.active', $originalTheme);
    }

    private function testViewRenderHook() {
        echo "\nTest: view.render hook integration\n";

        // Reset hooks to prevent leakage from/to other tests
        $this->resetHookManager();

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        file_put_contents($this->themePath . '/templates/hooktest.php',
            '<p>Original</p>');

        config()->set('theme.active', $testThemeName);

        // Register hook to modify content
        $app = Application::getInstance();
        $app->hooks()->register('view.render', function($content) {
            return str_replace('Original', 'Modified', $content);
        });

        $view = new View();
        $output = $view->fetch('hooktest', array());

        $this->assert(
            str_contains($output, 'Modified') && !str_contains($output, 'Original'),
            'view.render hook modifies content'
        );

        config()->set('theme.active', $originalTheme);

        // Clean up hooks so they don't leak to subsequent tests
        $this->resetHookManager();
    }

    private function testMultipleHooks() {
        echo "\nTest: Multiple hooks on same event\n";

        // Reset hooks to prevent leakage from/to other tests
        $this->resetHookManager();

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        file_put_contents($this->themePath . '/templates/multihook.php',
            '<p>Text</p>');

        config()->set('theme.active', $testThemeName);

        // Register multiple hooks with different priorities
        $app = Application::getInstance();
        $app->hooks()->register('view.render', function($content) {
            return str_replace('Text', 'Step1', $content);
        }, 10);
        $app->hooks()->register('view.render', function($content) {
            return str_replace('Step1', 'Step2', $content);
        }, 20);

        $view = new View();
        $output = $view->fetch('multihook', array());

        $this->assert(
            str_contains($output, 'Step2'),
            'Multiple hooks execute in order'
        );

        config()->set('theme.active', $originalTheme);

        // Clean up hooks so they don't leak to subsequent tests
        $this->resetHookManager();
    }

    private function testEmptyTemplate() {
        echo "\nTest: Empty template file\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        file_put_contents($this->themePath . '/templates/empty.php', '');

        config()->set('theme.active', $testThemeName);

        $view = new View();
        $output = $view->fetch('empty', array());

        // Just check that layout wrapper is present
        $hasDoctype = str_contains($output, '<!DOCTYPE html>');
        $hasHtmlTag = str_contains($output, '<html>');
        $hasBodyTag = str_contains($output, '<body>');

        $this->assert(
            $hasDoctype && $hasHtmlTag && $hasBodyTag,
            'Empty template renders with layout'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testTemplateWithOnlyWhitespace() {
        echo "\nTest: Template with only whitespace\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        file_put_contents($this->themePath . '/templates/whitespace.php', "   \n\n   \t  ");

        config()->set('theme.active', $testThemeName);

        $view = new View();
        $output = $view->fetch('whitespace', array());

        $this->assert(
            strlen($output) > 0,
            'Whitespace template produces output'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testRenderMethodEchoes() {
        echo "\nTest: render() method echoes output\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        file_put_contents($this->themePath . '/templates/echo-test.php',
            '<p>Echo test</p>');

        config()->set('theme.active', $testThemeName);

        $view = new View();

        ob_start();
        $view->render('echo-test', array());
        $output = ob_get_clean();

        $this->assert(
            str_contains($output, '<p>Echo test</p>'),
            'render() method echoes output'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testEscapeEdgeCases() {
        echo "\nTest: Escape method edge cases\n";

        $view = new View();

        // Null value
        $escaped = $view->escape(null);
        $this->assert(
            $escaped === '',
            'Null is escaped to empty string'
        );

        // Nested arrays
        $nested = array('a' => array('b' => '<script>'));
        $escapedNested = $view->escape($nested);
        $this->assert(
            $escapedNested['a']['b'] === '&lt;script&gt;',
            'Nested arrays are escaped recursively'
        );

        // Already escaped string (double escaping)
        $alreadyEscaped = '&lt;div&gt;';
        $doubleEscaped = $view->escape($alreadyEscaped);
        $this->assert(
            $doubleEscaped === '&amp;lt;div&amp;gt;',
            'Already escaped strings are double-escaped'
        );

        // Empty string
        $emptyEscaped = $view->escape('');
        $this->assert(
            $emptyEscaped === '',
            'Empty string remains empty'
        );
    }

    private function testAssetUrlEdgeCases() {
        echo "\nTest: Asset URL edge cases\n";

        $originalTheme = config('theme.active');
        $originalUrl = config('site.url');
        $testThemeName = basename($this->themePath);

        config()->set('theme.active', $testThemeName);
        config()->set('site.url', 'http://example.com');

        $view = new View();

        // Empty path
        $url1 = $view->asset('');
        $this->assert(
            !str_contains($url1, '//assets'),
            'Empty path does not create double slash'
        );

        // Path with leading slash
        $url2 = $view->asset('/css/style.css');
        $this->assert(
            !str_contains($url2, '//css'),
            'Leading slash is handled correctly'
        );

        // Path with multiple slashes
        $url3 = $view->asset('css//style.css');
        $this->assert(
            str_contains($url3, 'css//style.css'),
            'Multiple slashes in path are preserved'
        );

        config()->set('theme.active', $originalTheme);
        config()->set('site.url', $originalUrl);
    }

    private function testTemplateWithUndefinedVariable() {
        echo "\nTest: Template with undefined variable\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        // Template references undefined variable
        file_put_contents($this->themePath . '/templates/undefined.php',
            '<p><?php echo isset($undefined) ? $undefined : "default"; ?></p>');

        config()->set('theme.active', $testThemeName);

        $view = new View();
        $output = $view->fetch('undefined', array());

        $this->assert(
            str_contains($output, 'default'),
            'Template handles undefined variables gracefully'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testNestedPartials() {
        echo "\nTest: Nested partials\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        // Partial that includes another partial
        file_put_contents($this->themePath . '/templates/partials/outer.php',
            '<div><?php echo $view->partial("sidebar"); ?></div>');

        config()->set('theme.active', $testThemeName);

        $view = new View();
        $output = $view->partial('outer', array('view' => $view));

        $this->assert(
            str_contains($output, '<div>') && str_contains($output, '<aside>'),
            'Nested partials render correctly'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testThemeOverridesModulePartial() {
        echo "\nTest: Theme overrides module partial\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        $testModuleName = basename($this->modulePath);
        config()->set('theme.active', $testThemeName);

        // Create theme override for module partial
        $overrideDir = $this->themePath . '/templates/partials/' . $testModuleName;
        if (!is_dir($overrideDir)) {
            mkdir($overrideDir, 0755, true);
        }
        file_put_contents($overrideDir . '/menu.php',
            '<nav>Theme Override Menu</nav>');

        $view = new View();

        // Module partial should be overridden by theme
        $output = $view->partial($testModuleName . ':menu');
        $this->assert(
            str_contains($output, 'Theme Override Menu'),
            'Theme partial overrides module partial'
        );
        $this->assert(
            !str_contains($output, '<nav>Menu</nav>'),
            'Original module partial is not used when theme override exists'
        );

        // Clean up override
        unlink($overrideDir . '/menu.php');
        rmdir($overrideDir);

        // Without override, module partial should render
        $output2 = $view->partial($testModuleName . ':menu');
        $this->assert(
            str_contains($output2, '<nav>Menu</nav>'),
            'Module partial renders when no theme override'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testModulePartialNonexistentModule() {
        echo "\nTest: Module partial with nonexistent module\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        config()->set('theme.active', $testThemeName);

        $view = new View();

        $output = $view->partial('nonexistent-module:some-partial');
        $this->assert(
            str_contains($output, '<!-- Partial not found: nonexistent-module:some-partial'),
            'Nonexistent module partial returns not-found comment'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testAbortRendersThemeTemplate() {
        echo "\nTest: abort() renders theme error template\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);

        // Create a 404 template
        file_put_contents($this->themePath . '/templates/404.php',
            '<div class="error"><?php echo $code; ?> - <?php echo e($title); ?></div>');

        config()->set('theme.active', $testThemeName);

        // Capture abort() output
        ob_start();
        abort(404, 'test message');
        $output = ob_get_clean();

        $this->assert(
            str_contains($output, '<div class="error">'),
            'abort() renders theme 404 template'
        );
        $this->assert(
            str_contains($output, '<!DOCTYPE html>'),
            'abort() output is wrapped in layout'
        );
        $this->assert(
            http_response_code() === 404,
            'abort() sets correct HTTP status code'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function testAbortFallbackWithoutTemplate() {
        echo "\nTest: abort() fallback without theme template\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        config()->set('theme.active', $testThemeName);

        // No 403 template exists — should fall back to plain HTML
        ob_start();
        abort(403, 'Access denied');
        $output = ob_get_clean();

        $this->assert(
            str_contains($output, '403'),
            'abort() fallback contains status code'
        );
        $this->assert(
            str_contains($output, 'Forbidden'),
            'abort() fallback contains status title'
        );
        $this->assert(
            str_contains($output, 'Access denied'),
            'abort() fallback contains custom message'
        );
        $this->assert(
            http_response_code() === 403,
            'abort() sets correct HTTP status for fallback'
        );

        config()->set('theme.active', $originalTheme);
    }

    private function printSummary() {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Test Summary\n";
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

        if ($failed > 0) {
            echo "\nFailed tests:\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  - " . $result['message'] . "\n";
                }
            }
        }

        echo "\n";
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new ViewTest();
    $test->run();
}
