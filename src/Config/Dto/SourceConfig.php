<?php

declare(strict_types=1);

namespace App\Config\Dto;

final readonly class SourceConfig
{
    /**
     * @param list<FilterRuleConfig> $filters
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $url,
        public string $cacheTtl,
        public array $filters = [],
        public array $extra = [],
    ) {
    }

    public static function fromArray(string $id, array $data): self
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
            id: $id,
            label: (string) ($data['label'] ?? $id),
            url: (string) ($data['url'] ?? ''),
            cacheTtl: (string) ($data['cache_ttl'] ?? ''),
            filters: $filters,
            extra: self::extra($data, ['label', 'url', 'cache_ttl', 'filters']),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'url' => $this->url,
            'cache_ttl' => $this->cacheTtl,
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
