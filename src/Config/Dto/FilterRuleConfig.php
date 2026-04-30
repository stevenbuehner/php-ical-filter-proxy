<?php

declare(strict_types=1);

namespace App\Config\Dto;

final readonly class FilterRuleConfig
{
    /**
     * @param array<string, mixed> $match
     * @param array<int, array<string, mixed>> $transforms
     */
    public function __construct(
        public string $name,
        public string $action,
        public array $match,
        public array $transforms = [],
        public array $extra = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $name = (string) ($data['name'] ?? '');
        $action = (string) ($data['action'] ?? 'remove');
        $match = is_array($data['match'] ?? null) ? $data['match'] : [];
        $transforms = is_array($data['transforms'] ?? null) ? array_values($data['transforms']) : [];

        return new self(
            name: $name,
            action: $action,
            match: $match,
            transforms: $transforms,
            extra: self::extra($data, ['name', 'action', 'match', 'transforms']),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'action' => $this->action,
            'match' => $this->match,
            'transforms' => $this->transforms,
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
