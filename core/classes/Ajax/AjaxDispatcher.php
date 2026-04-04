<?php declare(strict_types=1);

namespace Ajax;

/**
 * AjaxDispatcher — unified AJAX action handler for Mantra CMS.
 *
 * Modules register named actions with options (auth, permission).
 * Two endpoints dispatch to registered handlers:
 *   POST|GET /ajax         — public actions
 *   POST|GET /admin/ajax   — admin actions (requireAuth middleware)
 *
 * Action name is read from the query string: ?action=module.action
 *
 * Response envelope:
 *   {"ok": true,  "data": mixed}
 *   {"ok": false, "error": "message"}
 */
class AjaxDispatcher
{
    /** @var array<string, array{handler: callable, method: string, auth: bool, permission: ?string}> */
    private array $actions = [];

    public function __construct()
    {
        $this->registerThemeHooks();
    }

    /**
     * Inject CSRF/base-url meta tags and the JS helper into public themes.
     * Called once from the constructor — since the service is lazy,
     * these hooks only fire when a module actually uses AJAX.
     */
    private function registerThemeHooks(): void
    {
        $hooks = app()->hooks();
        if (!$hooks) {
            return;
        }

        // Meta tags into <head>
        $hooks->register('theme.head', function ($content) {
            $csrf = e(app()->auth()->generateCsrfToken());
            $base = e(rtrim(config('site.url', ''), '/'));
            return $content
                . "\n" . '    <meta name="csrf-token" content="' . $csrf . '">'
                . "\n" . '    <meta name="base-url" content="' . $base . '">';
        }, 5);

        // JS helper before </body>
        $hooks->register('theme.footer', function ($content) {
            $version = MANTRA_PROJECT_INFO['version'] ?? '';
            $url = base_url('modules/admin/assets/js/admin-ajax.js');
            if ($version !== '') {
                $url .= '?v=' . urlencode($version);
            }
            return $content . "\n" . '    <script src="' . e($url) . '"></script>';
        }, 5);
    }

    /**
     * Register an AJAX action.
     *
     * @param string   $name    Unique action name (e.g. 'uploads.upload')
     * @param callable $handler function(\Http\Request $request, bool|string $access): mixed
     * @param array    $options {
     *     method:     'POST'|'GET'|'ANY'  (default 'POST'),
     *     auth:       bool                (default true),
     *     permission: string|null         (default null — auth only, no permission check),
     * }
     */
    public function register(string $name, callable $handler, array $options = []): void
    {
        $method = strtoupper($options['method'] ?? 'POST');

        $this->actions[$name] = [
            'handler' => $handler,
            'method' => $method,
            'auth' => (bool)($options['auth'] ?? true),
            'permission' => $options['permission'] ?? null,
        ];
    }

    /**
     * Dispatch the current request to a registered action.
     */
    public function dispatch(): void
    {
        $request = app()->request();
        $response = app()->response();

        $actionName = trim((string)$request->query('action', ''));

        if ($actionName === '' || !isset($this->actions[$actionName])) {
            $response->json(['ok' => false, 'error' => 'Unknown action'], 404);
        }

        $def = $this->actions[$actionName];

        // Method check
        if ($def['method'] !== 'ANY' && $request->method() !== $def['method']) {
            $response->json(['ok' => false, 'error' => 'Method not allowed'], 405);
        }

        // Auth check
        if ($def['auth'] && !app()->auth()->check()) {
            $response->json(['ok' => false, 'error' => 'Authentication required'], 401);
        }

        // Permission check
        $access = true;
        if ($def['permission'] !== null) {
            $userManager = new \User();
            $user = app()->auth()->user();
            $access = $userManager->hasPermission($user, $def['permission']);
            if ($access === false) {
                $response->json(['ok' => false, 'error' => 'Permission denied'], 403);
            }
        }

        // Hook: allow modules to intercept before dispatch
        $context = [
            'action' => $actionName,
            'access' => $access,
            'definition' => $def,
        ];
        if (app()->hooks()) {
            $context = app()->hooks()->fire('ajax.before', $context);
            if (!empty($context['halt'])) {
                $response->json([
                    'ok' => false,
                    'error' => $context['error'] ?? 'Blocked',
                ], (int)($context['code'] ?? 403));
            }
        }

        // Execute handler
        try {
            $result = ($def['handler'])($request, $access);

            $responseData = ['ok' => true, 'data' => $result];

            // Hook: filter response
            if (app()->hooks()) {
                $responseData = app()->hooks()->fire('ajax.after', $responseData, $context);
            }

            $response->json($responseData);
        } catch (AjaxException $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
            $response->json(['ok' => false, 'error' => $e->getMessage()], $code);
        } catch (\Exception $e) {
            logger()->error('AJAX action failed', [
                'action' => $actionName,
                'exception' => $e->getMessage(),
            ]);
            $response->json([
                'ok' => false,
                'error' => MANTRA_DEBUG ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Check if an action is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->actions[$name]);
    }

    /**
     * Get all registered action names.
     *
     * @return string[]
     */
    public function getRegistered(): array
    {
        return array_keys($this->actions);
    }
}
