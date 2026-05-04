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
          - type: match
            match:
              summary:
                regex: "/*invalid"
            on_match: transform
            transform:
              - type: replace_regex
                field: summary
                pattern: "/*invalid"
                replacement: "x"
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
    }

    public function testTimeTransformValidationReportsBadReferenceAndOffset(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'cfgtime_');
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
                  reference: invalid
                  offset: "10x"
                end:
                  reference: current_end
                  offset: "-90s"
YAML);

        $cacheRoot = sys_get_temp_dir() . '/ical_cache_' . uniqid('', true);
        mkdir($cacheRoot . '/feeds', 0777, true);
        mkdir($cacheRoot . '/exports', 0777, true);

        $errors = (new ConfigValidator())->validateFile($configFile, $cacheRoot . '/feeds', $cacheRoot . '/exports');
        $messages = array_map(static fn ($e): string => $e->message, $errors);
        $paths = array_map(static fn ($e): string => $e->path, $errors);

        self::assertContains('Time reference is invalid.', $messages);
        self::assertContains('Time offset is invalid.', $messages);
        self::assertContains('exports.e1.include_sources[0].filters[0].transform[0].start.reference', $paths);
        self::assertContains('exports.e1.include_sources[0].filters[0].transform[0].start.offset', $paths);
    }
}
