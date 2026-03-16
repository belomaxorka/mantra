<?php
/**
 * ModuleValidator - Validates module manifests and structure
 */

class ModuleValidator {
    
    /**
     * Validate module manifest
     * @param array $manifest
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateManifest($manifest) {
        $errors = array();
        
        // Required: id
        if (!isset($manifest['id'])) {
            $errors[] = 'Missing required field: id';
        } elseif (!is_string($manifest['id']) || !preg_match('/^[a-z0-9-]+$/', $manifest['id'])) {
            $errors[] = 'Invalid id format (must be kebab-case: lowercase with hyphens)';
        }
        
        // Required: version
        if (!isset($manifest['version'])) {
            $errors[] = 'Missing required field: version';
        } elseif (!is_string($manifest['version']) || !preg_match('/^\d+\.\d+\.\d+$/', $manifest['version'])) {
            $errors[] = 'Invalid version format (must be semantic: MAJOR.MINOR.PATCH)';
        }
        
        // Required: name
        if (!isset($manifest['name'])) {
            $errors[] = 'Missing required field: name';
        } elseif (!is_string($manifest['name']) || trim($manifest['name']) === '') {
            $errors[] = 'Invalid name (must be non-empty string)';
        }
        
        // Optional: type
        if (isset($manifest['type']) && !ModuleType::isValid($manifest['type'])) {
            $errors[] = 'Invalid type (must be one of: ' . implode(', ', ModuleType::all()) . ')';
        }
        
        // Optional: capabilities
        if (isset($manifest['capabilities'])) {
            if (!is_array($manifest['capabilities'])) {
                $errors[] = 'capabilities must be an array';
            } else {
                foreach ($manifest['capabilities'] as $cap) {
                    if (!ModuleCapability::isValid($cap)) {
                        $errors[] = "Invalid capability: {$cap}";
                    }
                }
            }
        }
        
        // Optional: dependencies
        if (isset($manifest['dependencies'])) {
            if (!is_array($manifest['dependencies'])) {
                $errors[] = 'dependencies must be an array';
            } else {
                foreach ($manifest['dependencies'] as $dep) {
                    if (!is_string($dep) || !preg_match('/^[a-z0-9-]+$/', $dep)) {
                        $errors[] = "Invalid dependency format: {$dep}";
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate module structure
     * @param string $moduleId
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateStructure($moduleId) {
        $errors = array();
        $modulePath = MANTRA_MODULES . '/' . $moduleId;
        
        // Check module directory exists
        if (!is_dir($modulePath)) {
            $errors[] = "Module directory not found: {$modulePath}";
            return $errors;
        }
        
        // Check manifest exists
        $manifestPath = $modulePath . '/module.json';
        if (!file_exists($manifestPath)) {
            $errors[] = 'Missing module.json';
        } else {
            try {
                $manifest = JsonFile::read($manifestPath);
                $manifestErrors = self::validateManifest($manifest);
                $errors = array_merge($errors, $manifestErrors);
            } catch (Exception $e) {
                $errors[] = 'Invalid module.json: ' . $e->getMessage();
            }
        }
        
        // Check module class exists
        $parts = explode('-', $moduleId);
        $pascalCase = implode('', array_map('ucfirst', $parts));
        $classFile = $modulePath . '/' . $pascalCase . 'Module.php';
        
        if (!file_exists($classFile)) {
            $errors[] = "Missing module class file: {$pascalCase}Module.php";
        }
        
        return $errors;
    }
    
    /**
     * Validate all modules
     * @return array Validation results keyed by module ID
     */
    public static function validateAll() {
        $results = array();
        
        if (!is_dir(MANTRA_MODULES)) {
            return $results;
        }
        
        $dirs = scandir(MANTRA_MODULES);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            
            if (!is_dir(MANTRA_MODULES . '/' . $dir)) {
                continue;
            }
            
            $errors = self::validateStructure($dir);
            $results[$dir] = array(
                'valid' => empty($errors),
                'errors' => $errors,
            );
        }
        
        return $results;
    }
}
