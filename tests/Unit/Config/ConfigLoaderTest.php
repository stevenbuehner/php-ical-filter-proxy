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
    filters:
      - type: match
        match:
          any: true
        on_match: transform
        transform:
          - type: prefix_text
            field: summary
            value: "[Export] "
    include_sources:
      - source: a
YAML);

        $config = (new ConfigLoader($tmp))->load();

        self::assertArrayHasKey('a', $config->sources);
        self::assertArrayHasKey('e', $config->exports);
        self::assertCount(1, $config->exports['e']->filters);
        self::assertSame('match', $config->exports['e']->filters[0]->type);
    }
}
