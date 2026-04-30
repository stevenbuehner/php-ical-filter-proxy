<?php

declare(strict_types=1);

namespace App\Config\Dto;

final readonly class ExportConfig
{
    /**
     * @param list<IncludedSourceConfig> $includeSources
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $token,
        public string $cacheTtl,
        public array $includeSources,
        public array $extra = [],
    ) {
    }

    public static function fromArray(string $id, array $data): self
    {
        $includeRaw = is_array($data['include_sources'] ?? null) ? $data['include_sources'] : [];
        $includeSources = [];

        foreach ($includeRaw as $includedSourceRaw) {
            if (!is_array($includedSourceRaw)) {
                continue;
            }
            $includeSources[] = IncludedSourceConfig::fromArray($includedSourceRaw);
        }

        return new self(
            id: $id,
            title: (string) ($data['title'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            token: (string) ($data['token'] ?? ''),
            cacheTtl: (string) ($data['cache_ttl'] ?? ''),
            includeSources: $includeSources,
            extra: self::extra($data, ['title', 'slug', 'token', 'cache_ttl', 'include_sources']),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'token' => $this->token,
            'cache_ttl' => $this->cacheTtl,
            'include_sources' => array_map(
                static fn (IncludedSourceConfig $include): array => $include->toArray(),
                $this->includeSources
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
