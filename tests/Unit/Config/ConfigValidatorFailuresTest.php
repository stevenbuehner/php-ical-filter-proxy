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

        $codes = array_map(static fn ($e): string => $e->code, $errors);
        $messages = array_map(static fn ($e): string => $e->message, $errors);
        $paths = array_map(static fn ($e): string => $e->path, $errors);

        self::assertContains('unknown_key', $codes);
        self::assertContains('invalid_value', $codes);
        self::assertContains('sources.s1.unknown', $paths);
        self::assertContains('sources.s1.cache_ttl', $paths);
        self::assertContains('exports.e1.include_sources[0].filters[0].match.summary.regex', $paths);

        self::assertContains('Unknown key.', $messages);
        self::assertContains('TTL format is invalid.', $messages);
        self::assertContains('Regex pattern is invalid.', $messages);
    }

    public function testMatchAnyMustBeTrue(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'cfgany_');
        self::assertNotFalse($configFile);

        file_put_contents($configFile, <<<'YAML'
sources:
  s1:
    url: "https://example.com/a.ics"
    filters:
      - name: "invalid any"
        action: keep
        match:
          any: false
exports:
  e1:
    title: "Export"
    slug: "e1"
    token: "secret"
    include_sources:
      - source: s1
YAML);

        $cacheRoot = sys_get_temp_dir() . '/ical_cache_' . uniqid('', true);
        mkdir($cacheRoot . '/feeds', 0777, true);
        mkdir($cacheRoot . '/exports', 0777, true);

        $errors = (new ConfigValidator())->validateFile($configFile, $cacheRoot . '/feeds', $cacheRoot . '/exports');
        $messages = array_map(static fn ($e): string => $e->message, $errors);
        $paths = array_map(static fn ($e): string => $e->path, $errors);

        self::assertNotSame([], $errors);
        self::assertContains('match.any must be true.', $messages);
        self::assertContains('sources.s1.filters[0].match.any', $paths);
    }
}
