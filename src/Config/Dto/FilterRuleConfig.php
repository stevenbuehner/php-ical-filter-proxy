<?php

declare(strict_types=1);

namespace App\Config\Dto;

final readonly class FilterRuleConfig
{
    /**
     * @param array<string, mixed> $match
     * @param list<array<string, mixed>> $transform
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $type,
        public array $match,
        public string $onMatch = 'keep',
        public bool $stopProcessing = false,
        public array $transform = [],
        public array $extra = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $transform = is_array($data['transform'] ?? null) ? array_values($data['transform']) : [];

        return new self(
            type: (string) ($data['type'] ?? 'match'),
            match: is_array($data['match'] ?? null) ? $data['match'] : [],
            onMatch: (string) ($data['on_match'] ?? 'keep'),
            stopProcessing: self::toBool($data['stop_processing'] ?? false),
            transform: $transform,
            extra: self::extra($data, ['type', 'match', 'on_match', 'stop_processing', 'transform']),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'match' => $this->match,
            'on_match' => $this->onMatch,
            'stop_processing' => $this->stopProcessing,
            'transform' => $this->transform,
            'extra' => $this->extra,
        ];
    }

    private static function toBool(mixed $value): bool
    {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? false;
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
