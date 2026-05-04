<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use App\Rule\RuleTypeConfigLoader;
use PHPUnit\Framework\TestCase;

final class RuleTypeConfigLoaderTest extends TestCase
{
    public function testLoadReturnsConfiguredTypes(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'ruletypes_');
        self::assertNotFalse($configFile);

        file_put_contents($configFile, <<<'YAML'
filters:
  - match
transformations:
  - prefix_text
  - adjust_times
  - modify_datetime
YAML);

        $config = (new RuleTypeConfigLoader($configFile))->load();

        self::assertSame(['match'], $config->filters);
        self::assertSame(['prefix_text', 'adjust_times', 'modify_datetime'], $config->transformations);
    }

    public function testLoadReturnsEmptyConfigForMissingFile(): void
    {
        $config = (new RuleTypeConfigLoader(sys_get_temp_dir() . '/missing-rule-types.yaml'))->load();

        self::assertSame([], $config->filters);
        self::assertSame([], $config->transformations);
    }
}
