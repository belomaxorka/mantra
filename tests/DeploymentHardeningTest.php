<?php declare(strict_types=1);

class DeploymentHardeningTest extends MantraTestCase
{
    public function testServerTemplatesRestrictPhpAndModuleAssets(): void
    {
        $nginx = file_get_contents(MANTRA_ROOT . '/docs/server-configs/nginx.conf');
        $caddy = file_get_contents(MANTRA_ROOT . '/docs/server-configs/Caddyfile');

        $this->assertStringContainsString('Never execute or expose arbitrary PHP files', $nginx);
        $this->assertStringContainsString('try_files $uri =404', $nginx);
        $this->assertStringContainsString('@nonEntryPhp', $caddy);
        $this->assertStringContainsString('X-Content-Type-Options nosniff', $caddy);
    }
}
