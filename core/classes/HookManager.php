<?php declare(strict_types=1);

/**
 * HookManager - Event/Hook system for extensibility
 * Allows modules to hook into system events
 */
class HookManager
{
    private $hooks = [];
    private $nextId = 1;

    /**
     * Register a hook callback
     *
     * @param string $hookName Hook name (e.g., 'system.init', 'content.save')
     * @param callable $callback Function to execute
     * @param int $priority Lower numbers run first (default: 10)
     * @return int Listener ID (can be passed to unregister())
     */
    public function register($hookName, $callback, $priority = 10)
    {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }

        $id = $this->nextId++;

        $this->hooks[$hookName][] = [
            'id' => $id,
            'callback' => $callback,
            'priority' => $priority,
        ];

        // Sort by priority
        usort($this->hooks[$hookName], fn($a, $b) => $a['priority'] - $b['priority']);

        return $id;
    }

    /**
     * Remove a specific listener by ID
     *
     * @param string $hookName Hook name
     * @param int $id Listener ID returned by register()
     * @return bool True if the listener was found and removed
     */
    public function unregister($hookName, $id)
    {
        if (!isset($this->hooks[$hookName])) {
            return false;
        }

        foreach ($this->hooks[$hookName] as $index => $hook) {
            if ($hook['id'] === $id) {
                array_splice($this->hooks[$hookName], $index, 1);
                return true;
            }
        }

        return false;
    }

    /**
     * Fire a hook
     *
     * @param string $hookName Hook name
     * @param mixed $data Data to pass to callbacks (transformable)
     * @param mixed $context Optional read-only context passed as second argument to callbacks
     * @return mixed Modified data after all callbacks
     */
    public function fire($hookName, $data = null, $context = null)
    {
        if (!isset($this->hooks[$hookName])) {
            return $data;
        }

        foreach ($this->hooks[$hookName] as $hook) {
            if (is_callable($hook['callback'])) {
                $result = ($hook['callback'])($data, $context);
                // Allow hooks to modify data
                if ($result !== null) {
                    $data = $result;
                }
            }
        }

        return $data;
    }

    /**
     * Check if hook has listeners
     */
    public function hasListeners($hookName)
    {
        return isset($this->hooks[$hookName]) && !empty($this->hooks[$hookName]);
    }

    /**
     * Get listener count for a hook
     */
    public function listenerCount($hookName)
    {
        return count($this->hooks[$hookName] ?? []);
    }

    /**
     * Get all hook names that have registered listeners
     */
    public function getActiveHooks()
    {
        return array_keys($this->hooks);
    }

    /**
     * Remove all listeners for a hook
     */
    public function clear($hookName): void
    {
        if (isset($this->hooks[$hookName])) {
            unset($this->hooks[$hookName]);
        }
    }
}
