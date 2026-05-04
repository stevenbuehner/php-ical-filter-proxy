<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigValidator;
use PHPUnit\Framework\TestCase;

final class ConfigValidatorTest extends TestCase
{
    public function testValidConfigHasNoErrors(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'cfgv_');
        self::assertNotFalse($configFile);

        file_put_contents($configFile, <<<'YAML'
sources:
  s1:
    url: "https://example.com/a.ics"
exports:
  e1:
    title: "Export"
    slug: "e1"
    token: "secret"
    event_migration:
      enabled: true
      gap_tolerance: "5m"
      strategy: "merge_titles_csv"
    include_sources:
      - source: s1
        filters:
          - type: match
            match:
              any: true
            on_match: transform
            transform:
              - type: adjust_times
                start:
                  reference: current_start
                  offset: "-20m"
                end:
                  reference: current_start
                  offset: "10m"
YAML);

        $cacheRoot = sys_get_temp_dir() . '/ical_cache_' . uniqid('', true);
        mkdir($cacheRoot . '/feeds', 0777, true);
        mkdir($cacheRoot . '/exports', 0777, true);

        $errors = (new ConfigValidator())->validateFile($configFile, $cacheRoot . '/feeds', $cacheRoot . '/exports');

        self::assertSame([], $errors);
    }
}
