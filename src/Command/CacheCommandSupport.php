<?php

declare(strict_types=1);

namespace App\Command;

use App\Cache\CacheMaintenance;

trait CacheCommandSupport
{
    private function isValidScope(string $scope): bool
    {
        return in_array($scope, ['feeds', 'exports', 'all'], true);
    }

    private function invalidScopeMessage(): string
    {
        return 'Invalid scope. Allowed values: feeds, exports, all.';
    }

    private function cacheMaintenance(string $projectRoot): CacheMaintenance
    {
        return new CacheMaintenance(
            $projectRoot . '/var/cache/feeds',
            $projectRoot . '/var/cache/exports'
        );
    }
}
