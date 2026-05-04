<?php

declare(strict_types=1);

namespace App\Config\Dto;

final readonly class EventMigrationConfig
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public bool $enabled = false,
        public string $gapTolerance = '0s',
        public string $strategy = 'merge_titles_csv',
        public array $extra = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            enabled: self::toBool($data['enabled'] ?? false),
            gapTolerance: (string) ($data['gap_tolerance'] ?? '0s'),
            strategy: (string) ($data['strategy'] ?? 'merge_titles_csv'),
            extra: self::extra($data, ['enabled', 'gap_tolerance', 'strategy']),
        );
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'gap_tolerance' => $this->gapTolerance,
            'strategy' => $this->strategy,
            'extra' => $this->extra,
        ];
    }

    public function gapToleranceSeconds(): int
    {
        $seconds = self::parseDurationSeconds($this->gapTolerance);
        if ($seconds === null) {
            throw new \InvalidArgumentException(sprintf('Invalid gap tolerance: %s', $this->gapTolerance));
        }

        return $seconds;
    }

    public static function parseDurationSeconds(string $value): ?int
    {
        if (!preg_match('/^([0-9]+)([smhdw])$/', $value, $matches)) {
            return null;
        }

        $amount = (int) $matches[1];
        $unit = $matches[2];

        return match ($unit) {
            's' => $amount,
            'm' => $amount * 60,
            'h' => $amount * 3600,
            'd' => $amount * 86400,
            'w' => $amount * 604800,
            default => null,
        };
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

    private static function toBool(mixed $value): bool
    {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? false;
    }
}
