<?php

declare(strict_types=1);

namespace App\Config;

use App\Config\Dto\AppConfig;
use Symfony\Component\Yaml\Yaml;

final readonly class ConfigLoader
{
    public function __construct(
        private string $configFile,
    ) {
    }

    public function load(): AppConfig
    {
        if (!is_file($this->configFile)) {
            throw new \RuntimeException(sprintf('Config file not found: %s', $this->configFile));
        }

        $parsed = Yaml::parseFile($this->configFile);

        if (!is_array($parsed)) {
            throw new \RuntimeException('Config root must be a YAML mapping.');
        }

        return AppConfig::fromArray($parsed);
    }
}
