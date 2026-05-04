<?php

declare(strict_types=1);

namespace App\Rule;

use Symfony\Component\Yaml\Yaml;

final readonly class RuleTypeConfigLoader
{
    public function __construct(
        private string $configFile,
    ) {
    }

    public function load(): RuleTypeConfig
    {
        if (!is_file($this->configFile)) {
            return new RuleTypeConfig([], []);
        }

        $parsed = Yaml::parseFile($this->configFile);
        if (!is_array($parsed)) {
            return new RuleTypeConfig([], []);
        }

        return RuleTypeConfig::fromArray($parsed);
    }
}
