<?php

declare(strict_types=1);

namespace App\Cache;

final readonly class CacheMaintenance
{
    public function __construct(
        private string $feedsDir,
        private string $exportsDir,
    ) {
    }

    /** @return array{deleted_files:int, deleted_bytes:int, scanned_files:int} */
    public function clear(string $scope): array
    {
        $dirs = $this->dirsForScope($scope);
        return $this->deleteMatching($dirs, static fn (string $path): bool => self::isCacheFile($path));
    }

    /** @return array{deleted_files:int, deleted_bytes:int, scanned_files:int} */
    public function pruneOlderThan(int $ageSeconds, string $scope = 'all'): array
    {
        $threshold = time() - max(0, $ageSeconds);
        $dirs = $this->dirsForScope($scope);

        return $this->deleteMatching(
            $dirs,
            static fn (string $path) => self::isCacheFile($path) && (@filemtime($path) !== false) && (filemtime($path) < $threshold)
        );
    }

    /** @return array<int, string> */
    private function dirsForScope(string $scope): array
    {
        return match ($scope) {
            'feeds' => [$this->feedsDir],
            'exports' => [$this->exportsDir],
            default => [$this->feedsDir, $this->exportsDir],
        };
    }

    /** @param array<int, string> $dirs
     *  @return array{deleted_files:int, deleted_bytes:int, scanned_files:int}
     */
    private function deleteMatching(array $dirs, callable $predicate): array
    {
        $deletedFiles = 0;
        $deletedBytes = 0;
        $scannedFiles = 0;

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $entries = @scandir($dir);
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (!is_file($path)) {
                    continue;
                }

                $scannedFiles++;
                if (!$predicate($path)) {
                    continue;
                }

                $size = filesize($path);
                if (@unlink($path)) {
                    $deletedFiles++;
                    if ($size !== false) {
                        $deletedBytes += $size;
                    }
                }
            }
        }

        return [
            'deleted_files' => $deletedFiles,
            'deleted_bytes' => $deletedBytes,
            'scanned_files' => $scannedFiles,
        ];
    }

    private static function isCacheFile(string $path): bool
    {
        return str_ends_with($path, '.cache') || str_ends_with($path, '.meta.json');
    }
}
