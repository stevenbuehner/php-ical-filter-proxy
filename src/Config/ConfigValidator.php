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
        'contains', 'contains_any', 'contains_all', 'not_contains', 'equals', 'not_equals', 'regex', 'empty',
    ];

    /** @return list<ValidationError> */
    public function validateFile(string $configFile, string $feedCacheDir, string $exportCacheDir): array
    {
        $errors = [];
        $lines = is_file($configFile) ? file($configFile, FILE_IGNORE_NEW_LINES) : [];
        $locator = new YamlLineLocator();

        if (!is_file($configFile)) {
            return [new ValidationError('file_not_found', 'Config file not found.', 'root', $configFile, 'no existing file', 1)];
        }

        try {
            $parsed = Yaml::parseFile($configFile);
        } catch (ParseException $exception) {
            return [new ValidationError('yaml_syntax', 'YAML syntax error.', 'root', 'valid YAML', $exception->getMessage(), $exception->getParsedLine() ?: 1)];
        }

        if (!is_array($parsed)) {
            return [new ValidationError('root_mapping', 'Config root must be a mapping.', 'root', 'mapping', gettype($parsed), 1)];
        }

        $add = function (ValidationError $error) use (&$errors, $locator, $lines): void {
            $line = $error->line ?? $locator->locate($lines, $error->path);
            $errors[] = new ValidationError($error->code, $error->message, $error->path, $error->expected, $error->found, $line);
        };

        foreach ($this->validateRoot($parsed) as $err) { $add($err); }
        foreach ($this->validateSources($parsed['sources'] ?? null) as $err) { $add($err); }
        foreach ($this->validateExports($parsed['exports'] ?? null, $parsed['sources'] ?? null) as $err) { $add($err); }
        foreach ($this->validateCacheDirectory($feedCacheDir, 'var/cache/feeds') as $err) { $add($err); }
        foreach ($this->validateCacheDirectory($exportCacheDir, 'var/cache/exports') as $err) { $add($err); }

        return $errors;
    }

    /** @return list<ValidationError> */
    private function validateRoot(array $root): array
    {
        $errors = [];
        foreach (array_keys($root) as $key) {
            if (!in_array((string) $key, ['sources', 'exports'], true)) {
                $errors[] = new ValidationError('unknown_key', 'Unknown root key.', (string) $key, 'sources|exports', (string) $key);
            }
        }
        if (!array_key_exists('sources', $root)) {
            $errors[] = new ValidationError('missing_key', "Missing required root key.", 'sources', 'present', 'missing');
        }
        if (!array_key_exists('exports', $root)) {
            $errors[] = new ValidationError('missing_key', "Missing required root key.", 'exports', 'present', 'missing');
        }
        return $errors;
    }

    /** @return list<ValidationError> */
    private function validateSources(mixed $sources): array
    {
        $errors = [];
        if (!is_array($sources)) {
            return [new ValidationError('invalid_type', 'Sources must be a mapping.', 'sources', 'mapping', gettype($sources))];
        }
        if ($sources === []) {
            return [new ValidationError('empty_collection', 'Sources must contain at least one source.', 'sources', '>=1 source', 'empty')];
        }

        foreach ($sources as $sourceId => $sourceData) {
            $path = 'sources.' . (string) $sourceId;
            if (!is_array($sourceData)) {
                $errors[] = new ValidationError('invalid_type', 'Source config must be a mapping.', $path, 'mapping', gettype($sourceData));
                continue;
            }
            foreach ($this->validateUnknownKeys($sourceData, self::SOURCE_ALLOWED_KEYS, $path) as $e) { $errors[] = $e; }

            $url = (string) ($sourceData['url'] ?? '');
            if ($url === '') {
                $errors[] = new ValidationError('missing_key', 'Source URL is required.', $path . '.url', 'non-empty string', 'missing/empty');
            }

            if (array_key_exists('cache_ttl', $sourceData)) {
                foreach ($this->validateTtl((string) $sourceData['cache_ttl'], $path . '.cache_ttl') as $e) { $errors[] = $e; }
            }
            if (array_key_exists('filters', $sourceData)) {
                foreach ($this->validateFilters($sourceData['filters'], $path . '.filters') as $e) { $errors[] = $e; }
            }
        }
        return $errors;
    }

    /** @return list<ValidationError> */
    private function validateExports(mixed $exports, mixed $sources): array
    {
        $errors = [];
        if (!is_array($exports)) {
            return [new ValidationError('invalid_type', 'Exports must be a mapping.', 'exports', 'mapping', gettype($exports))];
        }
        if ($exports === []) {
            return [new ValidationError('empty_collection', 'Exports must contain at least one export.', 'exports', '>=1 export', 'empty')];
        }

        $availableSources = is_array($sources) ? array_map('strval', array_keys($sources)) : [];
        $slugMap = [];

        foreach ($exports as $exportId => $exportData) {
            $path = 'exports.' . (string) $exportId;
            if (!is_array($exportData)) {
                $errors[] = new ValidationError('invalid_type', 'Export config must be a mapping.', $path, 'mapping', gettype($exportData));
                continue;
            }

            foreach ($this->validateUnknownKeys($exportData, self::EXPORT_ALLOWED_KEYS, $path) as $e) { $errors[] = $e; }

            foreach (['title', 'slug', 'token', 'include_sources'] as $required) {
                if (!array_key_exists($required, $exportData)) {
                    $errors[] = new ValidationError('missing_key', 'Required export key missing.', $path . '.' . $required, 'present', 'missing');
                }
            }

            $slug = trim((string) ($exportData['slug'] ?? ''));
            if ($slug === '') {
                $errors[] = new ValidationError('invalid_value', 'Slug must not be empty.', $path . '.slug', 'non-empty string', 'empty');
            } elseif (isset($slugMap[$slug])) {
                $errors[] = new ValidationError('duplicate_slug', 'Slug must be unique.', $path . '.slug', 'unique slug', $slug);
            } else {
                $slugMap[$slug] = true;
            }

            if (trim((string) ($exportData['token'] ?? '')) === '') {
                $errors[] = new ValidationError('invalid_value', 'Token must not be empty.', $path . '.token', 'non-empty string', 'empty');
            }

            if (array_key_exists('cache_ttl', $exportData)) {
                foreach ($this->validateTtl((string) $exportData['cache_ttl'], $path . '.cache_ttl') as $e) { $errors[] = $e; }
            }

            $include = $exportData['include_sources'] ?? null;
            if (!is_array($include)) {
                $errors[] = new ValidationError('invalid_type', 'include_sources must be a list.', $path . '.include_sources', 'list', gettype($include));
                continue;
            }
            if ($include === []) {
                $errors[] = new ValidationError('empty_collection', 'include_sources must contain at least one entry.', $path . '.include_sources', '>=1 entry', 'empty');
                continue;
            }

            foreach ($include as $idx => $includeSource) {
                $includePath = $path . '.include_sources[' . (int) $idx . ']';
                if (!is_array($includeSource)) {
                    $errors[] = new ValidationError('invalid_type', 'include_sources entry must be a mapping.', $includePath, 'mapping', gettype($includeSource));
                    continue;
                }

                foreach ($this->validateUnknownKeys($includeSource, self::INCLUDED_SOURCE_ALLOWED_KEYS, $includePath) as $e) { $errors[] = $e; }

                $sourceRef = trim((string) ($includeSource['source'] ?? ''));
                if ($sourceRef === '') {
                    $errors[] = new ValidationError('missing_key', 'Referenced source is required.', $includePath . '.source', 'non-empty source key', 'missing/empty');
                } elseif (!in_array($sourceRef, $availableSources, true)) {
                    $errors[] = new ValidationError('unknown_reference', 'Referenced source does not exist.', $includePath . '.source', 'existing source key', $sourceRef);
                }

                if (array_key_exists('filters', $includeSource)) {
                    foreach ($this->validateFilters($includeSource['filters'], $includePath . '.filters') as $e) { $errors[] = $e; }
                }
            }
        }

        return $errors;
    }

    /** @return list<ValidationError> */
    private function validateFilters(mixed $filters, string $path): array
    {
        $errors = [];
        if (!is_array($filters)) {
            return [new ValidationError('invalid_type', 'Filters must be a list.', $path, 'list', gettype($filters))];
        }

        foreach ($filters as $index => $filter) {
            $filterPath = $path . '[' . (int) $index . ']';
            if (!is_array($filter)) {
                $errors[] = new ValidationError('invalid_type', 'Filter must be a mapping.', $filterPath, 'mapping', gettype($filter));
                continue;
            }

            foreach ($this->validateUnknownKeys($filter, self::FILTER_ALLOWED_KEYS, $filterPath) as $e) { $errors[] = $e; }

            $action = (string) ($filter['action'] ?? 'remove');
            if (!in_array($action, ['keep', 'remove'], true)) {
                $errors[] = new ValidationError('invalid_value', 'Filter action must be keep or remove.', $filterPath . '.action', 'keep|remove', $action);
            }

            $match = $filter['match'] ?? null;
            if (!is_array($match)) {
                $errors[] = new ValidationError('invalid_type', 'Filter match must be a mapping.', $filterPath . '.match', 'mapping', gettype($match));
                continue;
            }

            foreach ($match as $field => $ruleSet) {
                $fieldName = (string) $field;
                $matchPath = $filterPath . '.match.' . $fieldName;

                if (!in_array($fieldName, self::MATCH_FIELDS, true)) {
                    $errors[] = new ValidationError('invalid_value', 'Unsupported match field.', $matchPath, implode('|', self::MATCH_FIELDS), $fieldName);
                    continue;
                }

                if (!is_array($ruleSet)) {
                    $errors[] = new ValidationError('invalid_type', 'Match field must map operators.', $matchPath, 'mapping', gettype($ruleSet));
                    continue;
                }

                foreach ($ruleSet as $operator => $value) {
                    $op = (string) $operator;
                    if (!in_array($op, self::MATCH_OPERATORS, true)) {
                        $errors[] = new ValidationError('invalid_value', 'Unsupported match operator.', $matchPath, implode('|', self::MATCH_OPERATORS), $op);
                        continue;
                    }

                    if ($op === 'regex') {
                        if (!is_string($value) || $value === '') {
                            $errors[] = new ValidationError('invalid_type', 'Regex operator requires non-empty string.', $matchPath . '.regex', 'non-empty regex string', gettype($value));
                        } elseif (@preg_match($value, '') === false) {
                            $errors[] = new ValidationError('invalid_value', 'Regex pattern is invalid.', $matchPath . '.regex', 'valid PCRE pattern', $value);
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /** @return list<ValidationError> */
    private function validateTtl(string $ttl, string $path): array
    {
        if (!preg_match('/^[1-9][0-9]*(s|m|h|d)$/', $ttl)) {
            return [new ValidationError('invalid_value', 'TTL format is invalid.', $path, '30s|15m|1h|1d', $ttl)];
        }
        return [];
    }

    /** @return list<ValidationError> */
    private function validateCacheDirectory(string $directory, string $label): array
    {
        $errors = [];
        if (!is_dir($directory)) {
            $errors[] = new ValidationError('missing_path', 'Cache directory does not exist.', $label, 'existing writable directory', $directory);
            return $errors;
        }
        if (!is_writable($directory)) {
            $errors[] = new ValidationError('not_writable', 'Cache directory is not writable.', $label, 'writable directory', $directory);
        }
        return $errors;
    }

    /** @return list<ValidationError> */
    private function validateUnknownKeys(array $data, array $allowed, string $path): array
    {
        $errors = [];
        foreach (array_keys($data) as $key) {
            $name = (string) $key;
            if (!in_array($name, $allowed, true)) {
                $errors[] = new ValidationError('unknown_key', 'Unknown key.', $path . '.' . $name, implode('|', $allowed), $name);
            }
        }
        return $errors;
    }
}
