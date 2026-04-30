<?php

declare(strict_types=1);

namespace App\Config\Dto;

final readonly class IncludedSourceConfig
{
    /**
     * @param list<FilterRuleConfig> $filters
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $source,
        public array $filters = [],
        public array $extra = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $filtersRaw = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $filters = [];

        foreach ($filtersRaw as $filterRaw) {
            if (!is_array($filterRaw)) {
                continue;
            }
            $filters[] = FilterRuleConfig::fromArray($filterRaw);
        }

        return new self(
            source: (string) ($data['source'] ?? ''),
            filters: $filters,
            extra: self::extra($data, ['source', 'filters']),
        );
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'filters' => array_map(
                static fn (FilterRuleConfig $filter): array => $filter->toArray(),
                $this->filters
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
