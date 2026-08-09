<?php declare(strict_types=1);

class InstallationStateTest extends MantraTestCase
{
    public function testDefaultConfigurationIsProductionSafe(): void
    {
        $defaults = Config::defaults();

        $this->assertFalse(Config::getNested($defaults, 'debug.enabled'));
        $this->assertSame('', Config::getNested($defaults, 'site.url'));
    }

    public function testBaseUrlFallbackDoesNotContainHostHeader(): void
    {
        $originalHost = $_SERVER['HTTP_HOST'] ?? null;
        $originalScript = $_SERVER['SCRIPT_NAME'] ?? null;
        $_SERVER['HTTP_HOST'] = 'attacker.example';
        $_SERVER['SCRIPT_NAME'] = '/mantra/index.php';

        try {
            $this->assertSame('/mantra', Config::detectBasePath());
        } finally {
            if ($originalHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $originalHost;
            }
            if ($originalScript === null) {
                unset($_SERVER['SCRIPT_NAME']);
            } else {
                $_SERVER['SCRIPT_NAME'] = $originalScript;
            }
        }
    }
}
