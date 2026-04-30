<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigValidator;
use PHPUnit\Framework\TestCase;

final class ConfigValidatorFailuresTest extends TestCase
{
    public function testValidatorFindsUnknownKeysAndBadRegexAndBadTtl(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'cfgbad_');
        self::assertNotFalse($configFile);

        file_put_contents($configFile, <<<'YAML'
sources:
  s1:
    url: "https://example.com/a.ics"
    unknown: true
    cache_ttl: "15x"
exports:
  e1:
    title: "Export"
    slug: "e1"
    token: "secret"
    include_sources:
      - source: s1
        filters:
          - name: "R"
            match:
              summary:
                regex: "/*invalid"
YAML);

        $cacheRoot = sys_get_temp_dir() . '/ical_cache_' . uniqid('', true);
        mkdir($cacheRoot . '/feeds', 0777, true);
        mkdir($cacheRoot . '/exports', 0777, true);

        $errors = (new ConfigValidator())->validateFile($configFile, $cacheRoot . '/feeds', $cacheRoot . '/exports');
        $joined = implode("\n", $errors);

        self::assertStringContainsString("Unknown key 'unknown'", $joined);
        self::assertStringContainsString('cache_ttl must match TTL format', $joined);
        self::assertStringContainsString('is not a valid regex pattern', $joined);
    }
}
