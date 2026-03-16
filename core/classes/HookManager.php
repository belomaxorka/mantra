<?php
/**
 * HookManager - Event/Hook system for extensibility
 * Allows modules to hook into system events
 */

class HookManager {
    private $hooks = array();
    
    /**
     * Register a hook callback
     * 
     * @param string $hookName Hook name (e.g., 'system.init', 'content.save')
     * @param callable $callback Function to execute
     * @param int $priority Lower numbers run first (default: 10)
     */
    public function register($hookName, $callback, $priority = 10) {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = array();
        }
        
        $this->hooks[$hookName][] = array(
            'callback' => $callback,
            'priority' => $priority
        );
        
        // Sort by priority
        usort($this->hooks[$hookName], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }
    
    /**
     * Fire a hook
     * 
     * @param string $hookName Hook name
     * @param mixed $data Data to pass to callbacks
     * @return mixed Modified data after all callbacks
     */
    public function fire($hookName, $data = null) {
        if (!isset($this->hooks[$hookName])) {
            return $data;
        }
        
        foreach ($this->hooks[$hookName] as $hook) {
            if (is_callable($hook['callback'])) {
                $result = call_user_func($hook['callback'], $data);
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
    public function hasListeners($hookName) {
        return isset($this->hooks[$hookName]) && !empty($this->hooks[$hookName]);
    }
    
    /**
     * Remove all listeners for a hook
     */
    public function clear($hookName) {
        if (isset($this->hooks[$hookName])) {
            unset($this->hooks[$hookName]);
        }
    }
}
