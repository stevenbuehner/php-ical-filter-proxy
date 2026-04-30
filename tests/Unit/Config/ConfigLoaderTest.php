<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadReturnsAppConfig(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cfg_');
        self::assertNotFalse($tmp);

        file_put_contents($tmp, <<<'YAML'
sources:
  a:
    url: "https://example.com/a.ics"
exports:
  e:
    title: "Export"
    slug: "export"
    token: "secret"
    include_sources:
      - source: a
YAML);

        $config = (new ConfigLoader($tmp))->load();

        self::assertArrayHasKey('a', $config->sources);
        self::assertArrayHasKey('e', $config->exports);
    }
}
