<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class ConfigValidator
{
    private const SOURCE_ALLOWED_KEYS = ['label', 'url', 'cache_ttl', 'filters'];
    private const EXPORT_ALLOWED_KEYS = ['title', 'slug', 'token', 'cache_ttl', 'include_sources'];
    private const INCLUDED_SOURCE_ALLOWED_KEYS = ['source', 'filters'];
    private const FILTER_ALLOWED_KEYS = ['name', 'action', 'match', 'transform', 'transforms'];
    private const MATCH_FIELDS = ['summary', 'description', 'location', 'url', 'categories', 'date'];
    private const MATCH_OPERATORS = [
        'contains',
        'contains_any',
        'contains_all',
        'not_contains',
        'equals',
        'not_equals',
        'regex',
        'empty',
    ];

    public function validateFile(string $configFile, string $feedCacheDir, string $exportCacheDir): array
    {
        $errors = [];

        if (!is_file($configFile)) {
            return [sprintf('Config file not found: %s', $configFile)];
        }

        try {
            $parsed = Yaml::parseFile($configFile);
        } catch (ParseException $exception) {
            return [sprintf('YAML syntax error: %s', $exception->getMessage())];
        }

        if (!is_array($parsed)) {
            return ['Config root must be a YAML mapping.'];
        }

        $errors = array_merge($errors, $this->validateRoot($parsed));
        $errors = array_merge($errors, $this->validateSources($parsed['sources'] ?? null));
        $errors = array_merge($errors, $this->validateExports($parsed['exports'] ?? null, $parsed['sources'] ?? null));
        $errors = array_merge($errors, $this->validateCacheDirectory($feedCacheDir, 'var/cache/feeds'));
        $errors = array_merge($errors, $this->validateCacheDirectory($exportCacheDir, 'var/cache/exports'));

        return $errors;
    }

    private function validateRoot(array $root): array
    {
        $errors = [];
        $allowed = ['sources', 'exports'];

        foreach (array_keys($root) as $key) {
            if (!in_array((string) $key, $allowed, true)) {
                $errors[] = sprintf("Unknown root key '%s'.", (string) $key);
            }
        }

        if (!array_key_exists('sources', $root)) {
            $errors[] = "Missing required root key 'sources'.";
        }

        if (!array_key_exists('exports', $root)) {
            $errors[] = "Missing required root key 'exports'.";
        }

        return $errors;
    }

    private function validateSources(mixed $sources): array
    {
        $errors = [];

        if (!is_array($sources)) {
            return ["Root key 'sources' must be a mapping with at least one source."];
        }

        if ($sources === []) {
            $errors[] = "Root key 'sources' must contain at least one source.";
            return $errors;
        }

        foreach ($sources as $sourceId => $sourceData) {
            $path = sprintf('sources.%s', (string) $sourceId);

            if (!is_array($sourceData)) {
                $errors[] = sprintf("%s must be a mapping.", $path);
                continue;
            }

            $errors = array_merge($errors, $this->validateUnknownKeys($sourceData, self::SOURCE_ALLOWED_KEYS, $path));

            if (!array_key_exists('url', $sourceData) || trim((string) $sourceData['url']) == '') {
                $errors[] = sprintf("%s.url is required and must not be empty.", $path);
            }

            if (array_key_exists('cache_ttl', $sourceData)) {
                $errors = array_merge($errors, $this->validateTtl((string) $sourceData['cache_ttl'], sprintf('%s.cache_ttl', $path)));
            }

            if (array_key_exists('filters', $sourceData)) {
                $errors = array_merge($errors, $this->validateFilters($sourceData['filters'], sprintf('%s.filters', $path)));
            }
        }

        return $errors;
    }

    private function validateExports(mixed $exports, mixed $sources): array
    {
        $errors = [];

        if (!is_array($exports)) {
            return ["Root key 'exports' must be a mapping with at least one export."];
        }

        if ($exports === []) {
            $errors[] = "Root key 'exports' must contain at least one export.";
            return $errors;
        }

        $availableSources = is_array($sources) ? array_map('strval', array_keys($sources)) : [];
        $slugMap = [];

        foreach ($exports as $exportId => $exportData) {
            $path = sprintf('exports.%s', (string) $exportId);

            if (!is_array($exportData)) {
                $errors[] = sprintf("%s must be a mapping.", $path);
                continue;
            }

            $errors = array_merge($errors, $this->validateUnknownKeys($exportData, self::EXPORT_ALLOWED_KEYS, $path));

            foreach (['title', 'slug', 'token', 'include_sources'] as $requiredKey) {
                if (!array_key_exists($requiredKey, $exportData)) {
                    $errors[] = sprintf("%s.%s is required.", $path, $requiredKey);
                }
            }

            $slug = trim((string) ($exportData['slug'] ?? ''));
            if ($slug !== '') {
                if (isset($slugMap[$slug])) {
                    $errors[] = sprintf(
                        "Duplicate slug '%s' found in %s and %s.",
                        $slug,
                        $slugMap[$slug],
                        $path
                    );
                } else {
                    $slugMap[$slug] = $path;
                }
            } else {
                $errors[] = sprintf('%s.slug must not be empty.', $path);
            }

            if (trim((string) ($exportData['token'] ?? '')) === '') {
                $errors[] = sprintf('%s.token must not be empty.', $path);
            }

            if (array_key_exists('cache_ttl', $exportData)) {
                $errors = array_merge($errors, $this->validateTtl((string) $exportData['cache_ttl'], sprintf('%s.cache_ttl', $path)));
            }

            $includeSources = $exportData['include_sources'] ?? null;
            if (!is_array($includeSources)) {
                $errors[] = sprintf('%s.include_sources must be a list with at least one entry.', $path);
                continue;
            }

            if ($includeSources === []) {
                $errors[] = sprintf('%s.include_sources must contain at least one entry.', $path);
                continue;
            }

            foreach ($includeSources as $index => $includeSource) {
                $includePath = sprintf('%s.include_sources[%d]', $path, (int) $index);

                if (!is_array($includeSource)) {
                    $errors[] = sprintf('%s must be a mapping.', $includePath);
                    continue;
                }

                $errors = array_merge($errors, $this->validateUnknownKeys($includeSource, self::INCLUDED_SOURCE_ALLOWED_KEYS, $includePath));

                $sourceRef = trim((string) ($includeSource['source'] ?? ''));
                if ($sourceRef === '') {
                    $errors[] = sprintf('%s.source is required and must not be empty.', $includePath);
                } elseif (!in_array($sourceRef, $availableSources, true)) {
                    $errors[] = sprintf("%s references unknown source '%s'.", $includePath, $sourceRef);
                }

                if (array_key_exists('filters', $includeSource)) {
                    $errors = array_merge($errors, $this->validateFilters($includeSource['filters'], sprintf('%s.filters', $includePath)));
                }
            }
        }

        return $errors;
    }

    private function validateFilters(mixed $filters, string $path): array
    {
        $errors = [];

        if (!is_array($filters)) {
            return [sprintf('%s must be a list.', $path)];
        }

        foreach ($filters as $index => $filter) {
            $filterPath = sprintf('%s[%d]', $path, (int) $index);

            if (!is_array($filter)) {
                $errors[] = sprintf('%s must be a mapping.', $filterPath);
                continue;
            }

            $errors = array_merge($errors, $this->validateUnknownKeys($filter, self::FILTER_ALLOWED_KEYS, $filterPath));

            $action = (string) ($filter['action'] ?? 'remove');
            if (!in_array($action, ['keep', 'remove'], true)) {
                $errors[] = sprintf("%s.action must be 'keep' or 'remove'.", $filterPath);
            }

            $match = $filter['match'] ?? null;
            if (!is_array($match)) {
                $errors[] = sprintf('%s.match must be a mapping.', $filterPath);
            } else {
                foreach ($match as $field => $ruleSet) {
                    $fieldName = (string) $field;
                    $matchPath = sprintf('%s.match.%s', $filterPath, $fieldName);

                    if (!in_array($fieldName, self::MATCH_FIELDS, true)) {
                        $errors[] = sprintf("%s uses unsupported field '%s'.", $filterPath, $fieldName);
                        continue;
                    }

                    if (!is_array($ruleSet)) {
                        $errors[] = sprintf('%s must be a mapping of operators.', $matchPath);
                        continue;
                    }

                    foreach ($ruleSet as $operator => $value) {
                        $operatorName = (string) $operator;
                        if (!in_array($operatorName, self::MATCH_OPERATORS, true)) {
                            $errors[] = sprintf("%s contains unsupported operator '%s'.", $matchPath, $operatorName);
                            continue;
                        }

                        if ($operatorName === 'regex') {
                            $regexPath = sprintf('%s.regex', $matchPath);
                            if (!is_string($value) || $value === '') {
                                $errors[] = sprintf('%s must be a non-empty regex string.', $regexPath);
                                continue;
                            }

                            if (@preg_match($value, '') === false) {
                                $errors[] = sprintf('%s is not a valid regex pattern.', $regexPath);
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private function validateTtl(string $ttl, string $path): array
    {
        if (!preg_match('/^[1-9][0-9]*(s|m|h|d)$/', $ttl)) {
            return [sprintf('%s must match TTL format like 30s, 15m, 1h or 1d.', $path)];
        }

        return [];
    }

    private function validateCacheDirectory(string $directory, string $label): array
    {
        $errors = [];

        if (!is_dir($directory)) {
            $errors[] = sprintf('%s does not exist: %s', $label, $directory);
            return $errors;
        }

        if (!is_writable($directory)) {
            $errors[] = sprintf('%s is not writable: %s', $label, $directory);
        }

        return $errors;
    }

    private function validateUnknownKeys(array $data, array $allowed, string $path): array
    {
        $errors = [];

        foreach (array_keys($data) as $key) {
            $name = (string) $key;
            if (!in_array($name, $allowed, true)) {
                $errors[] = sprintf("Unknown key '%s' at %s.", $name, $path);
            }
        }

        return $errors;
    }
}
