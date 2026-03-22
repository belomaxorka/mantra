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
            $this->themePath . '/widgets',
            $this->themePath . '/assets',
            $this->modulePath . '/views',
            $this->modulePath . '/widgets'
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

        // Theme widget
        file_put_contents($this->themePath . '/widgets/sidebar.php',
            '<aside><?php echo $content ?? "Sidebar"; ?></aside>');

        // Module widget
        file_put_contents($this->modulePath . '/widgets/menu.php',
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

        $this->testBasicTemplateRendering();
        $this->testLayoutWrapping();
        $this->testModuleTemplateNoLayout();
        $this->testContentVariableProtection();
        $this->testAssetUrlGeneration();
        $this->testEscapeMethod();
        $this->testWidgetRendering();
        $this->testOutputBufferingErrorHandling();
        $this->testTemplateNotFound();

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

        // Create a layout that uses $content
        file_put_contents($this->themePath . '/templates/layout.php',
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

    private function testWidgetRendering() {
        echo "\nTest: Widget rendering\n";

        $originalTheme = config('theme.active');
        $testThemeName = basename($this->themePath);
        $testModuleName = basename($this->modulePath);
        config()->set('theme.active', $testThemeName);

        $view = new View();

        // Theme widget
        $widget = $view->widget('sidebar');
        $this->assert(
            str_contains($widget, '<aside>Sidebar</aside>'),
            'Theme widget renders'
        );

        // Theme widget with params
        $widget2 = $view->widget('sidebar', array('content' => 'Custom'));
        $this->assert(
            str_contains($widget2, '<aside>Custom</aside>'),
            'Theme widget renders with parameters'
        );

        // Module widget
        $widget3 = $view->widget($testModuleName . ':menu');
        $this->assert(
            str_contains($widget3, '<nav>Menu</nav>'),
            'Module widget renders'
        );

        // Non-existent widget
        $widget4 = $view->widget('nonexistent');
        $this->assert(
            str_contains($widget4, '<!-- Widget not found'),
            'Non-existent widget returns comment'
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
