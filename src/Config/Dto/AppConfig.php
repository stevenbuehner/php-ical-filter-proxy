<?php

declare(strict_types=1);

namespace App\Config\Dto;

final readonly class AppConfig
{
    /**
     * @param array<string, SourceConfig> $sources
     * @param array<string, ExportConfig> $exports
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public array $sources,
        public array $exports,
        public array $extra = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $sourcesRaw = is_array($data['sources'] ?? null) ? $data['sources'] : [];
        $exportsRaw = is_array($data['exports'] ?? null) ? $data['exports'] : [];

        $sources = [];
        foreach ($sourcesRaw as $id => $sourceRaw) {
            if (!is_array($sourceRaw)) {
                continue;
            }
            $sources[(string) $id] = SourceConfig::fromArray((string) $id, $sourceRaw);
        }

        $exports = [];
        foreach ($exportsRaw as $id => $exportRaw) {
            if (!is_array($exportRaw)) {
                continue;
            }
            $exports[(string) $id] = ExportConfig::fromArray((string) $id, $exportRaw);
        }

        return new self(
            sources: $sources,
            exports: $exports,
            extra: self::extra($data, ['sources', 'exports']),
        );
    }

    public function toArray(): array
    {
        return [
            'sources' => array_map(
                static fn (SourceConfig $source): array => $source->toArray(),
                $this->sources
            ),
            'exports' => array_map(
                static fn (ExportConfig $export): array => $export->toArray(),
                $this->exports
            ),
            'extra' => $this->extra,
        ];
    }

    private static function extra(array $data, array $knownKeys): array
    {
        $extra = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, $knownKeys, true)) {
                $extra[(string) $key] = $value;
            }
        }

        return $extra;
    }
}
