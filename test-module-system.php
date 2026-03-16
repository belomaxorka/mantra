#!/usr/bin/env php
<?php
/**
 * Test Module System Integration
 */

require_once __DIR__ . '/core/bootstrap.php';

echo "===========================================\n";
echo "Module System Integration Test\n";
echo "===========================================\n\n";

// Test 1: Module API
echo "Test 1: Module API\n";
echo "-------------------\n";

$admin = app()->modules()->getModule('admin');
if ($admin) {
    echo "✓ Admin module loaded\n";
    echo "  ID: " . $admin->getId() . "\n";
    echo "  Name: " . $admin->getName() . "\n";
    echo "  Type: " . $admin->getType() . "\n";
    echo "  Version: " . $admin->getVersion() . "\n";
    echo "  Disableable: " . ($admin->isDisableable() ? 'Yes' : 'No') . "\n";
    echo "  Deletable: " . ($admin->isDeletable() ? 'Yes' : 'No') . "\n";
    echo "  Has Settings: " . ($admin->hasSettings() ? 'Yes' : 'No') . "\n";
    echo "  Has Translations: " . ($admin->hasTranslations() ? 'Yes' : 'No') . "\n";
} else {
    echo "✗ Admin module not loaded\n";
}

echo "\n";

// Test 2: CORE module protection
echo "Test 2: CORE Module Protection\n";
echo "-------------------------------\n";

$coreModules = app()->modules()->getModulesByType(ModuleType::CORE);
echo "Found " . count($coreModules) . " CORE modules:\n";
foreach ($coreModules as $id => $data) {
    $module = $data['instance'];
    echo "  - {$id}: {$module->getName()}\n";
    echo "    Disableable: " . ($module->isDisableable() ? 'Yes' : 'No') . "\n";
    echo "    Deletable: " . ($module->isDeletable() ? 'Yes' : 'No') . "\n";
}

echo "\n";

// Test 3: Module capabilities
echo "Test 3: Module Capabilities\n";
echo "---------------------------\n";

$settingsModules = app()->modules()->getModulesByCapability(ModuleCapability::SETTINGS);
echo "Modules with SETTINGS capability: " . count($settingsModules) . "\n";
foreach ($settingsModules as $id => $data) {
    echo "  - {$id}\n";
}

echo "\n";

$adminUIModules = app()->modules()->getModulesByCapability(ModuleCapability::ADMIN_UI);
echo "Modules with ADMIN_UI capability: " . count($adminUIModules) . "\n";
foreach ($adminUIModules as $id => $data) {
    echo "  - {$id}\n";
}

echo "\n";

// Test 4: Translation discovery
echo "Test 4: Translation Discovery\n";
echo "-----------------------------\n";

$translations = translator()->discoverModuleTranslations();
echo "Modules with translations: " . count($translations) . "\n";
foreach ($translations as $id => $info) {
    echo "  - {$id}: {$info['name']}\n";
    echo "    Locales: " . implode(', ', $info['locales']) . "\n";
}

echo "\n";

// Test 5: Module discovery
echo "Test 5: Module Discovery\n";
echo "------------------------\n";

$allModules = app()->modules()->discoverModules();
echo "Total modules found: " . count($allModules) . "\n";
echo "Enabled: " . count(array_filter($allModules, function($m) { return $m['enabled']; })) . "\n";
echo "Disabled: " . count(array_filter($allModules, function($m) { return !$m['enabled']; })) . "\n";

echo "\n";

// Test 6: Module validation
echo "Test 6: Module Validation\n";
echo "-------------------------\n";

$results = ModuleValidator::validateAll();
$valid = array_filter($results, function($r) { return $r['valid']; });
$invalid = array_filter($results, function($r) { return !$r['valid']; });

echo "Valid modules: " . count($valid) . "\n";
echo "Invalid modules: " . count($invalid) . "\n";

if (!empty($invalid)) {
    echo "\nInvalid modules:\n";
    foreach ($invalid as $id => $result) {
        echo "  ✗ {$id}:\n";
        foreach ($result['errors'] as $error) {
            echo "    - {$error}\n";
        }
    }
}

echo "\n";

// Test 7: Translation system
echo "Test 7: Translation System\n";
echo "--------------------------\n";

$locale = translator()->getLocale();
echo "Current locale: {$locale}\n";

$testKey = 'admin.title';
$translated = t($testKey);
echo "Translation test: t('{$testKey}') = '{$translated}'\n";

if (translator()->has($testKey)) {
    echo "✓ Translation exists\n";
} else {
    echo "✗ Translation not found\n";
}

echo "\n";

// Test 8: Module type distribution
echo "Test 8: Module Type Distribution\n";
echo "--------------------------------\n";

$types = array();
foreach (app()->modules()->getModules() as $id => $data) {
    $type = $data['instance']->getType();
    if (!isset($types[$type])) {
        $types[$type] = 0;
    }
    $types[$type]++;
}

foreach ($types as $type => $count) {
    echo "  {$type}: {$count}\n";
}

echo "\n";

echo "===========================================\n";
echo "All tests completed!\n";
echo "===========================================\n";
