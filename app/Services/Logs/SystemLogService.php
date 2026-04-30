<?php

namespace App\Services\Logs;

use Carbon\Carbon;
use Illuminate\Support\Str;

class SystemLogService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function readEntries(string $path, int $maxEntries = 400): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);
        if (! is_string($contents) || $contents === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $contents);
        if (! is_array($lines)) {
            return [];
        }

        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            $lineValue = is_string($line) ? $line : '';
            if (preg_match('/^\[(?<date>[^\]]+)\]\s+(?<env>[a-zA-Z0-9_]+)\.(?<level>[A-Z]+):\s(?<message>.*)$/', $lineValue, $matches) === 1) {
                if (is_array($current)) {
                    $entries[] = $current;
                }

                $message = trim((string) ($matches['message'] ?? ''));
                $current = [
                    'timestamp' => $this->normalizeTimestamp((string) ($matches['date'] ?? '')),
                    'environment' => strtolower((string) ($matches['env'] ?? 'local')),
                    'level' => strtolower((string) ($matches['level'] ?? 'info')),
                    'message' => $message,
                    'is_key_point' => $this->isKeyPoint(
                        strtolower((string) ($matches['level'] ?? 'info')),
                        $message,
                    ),
                ];

                continue;
            }

            if (is_array($current) && trim($lineValue) !== '' && ! str_starts_with($lineValue, '#')) {
                $current['message'] = trim($current['message'].' '.trim($lineValue));
            }
        }

        if (is_array($current)) {
            $entries[] = $current;
        }

        $entries = array_reverse($entries);

        if (count($entries) > $maxEntries) {
            $entries = array_slice($entries, 0, $maxEntries);
        }

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    public function filterEntries(
        array $entries,
        string $search = '',
        string $level = '',
        bool $keyOnly = false,
        string $from = '',
        string $to = '',
    ): array {
        $searchValue = trim(Str::lower($search));
        $levelValue = trim(Str::lower($level));
        $fromValue = trim($from);
        $toValue = trim($to);
        $fromTimestamp = $fromValue !== '' ? $this->parseFilterTimestamp($fromValue) : null;
        $toTimestamp = $toValue !== '' ? $this->parseFilterTimestamp($toValue) : null;

        return array_values(array_filter($entries, function (array $entry) use ($searchValue, $levelValue, $keyOnly, $fromTimestamp, $toTimestamp): bool {
            $entryLevel = Str::lower((string) ($entry['level'] ?? ''));
            $entryMessage = Str::lower((string) ($entry['message'] ?? ''));
            $entryEnvironment = Str::lower((string) ($entry['environment'] ?? ''));
            $entryTimestamp = $this->parseFilterTimestamp((string) ($entry['timestamp'] ?? ''));

            if ($levelValue !== '' && $entryLevel !== $levelValue) {
                return false;
            }

            if ($fromTimestamp !== null && $entryTimestamp !== null && $entryTimestamp->lt($fromTimestamp)) {
                return false;
            }

            if ($toTimestamp !== null && $entryTimestamp !== null && $entryTimestamp->gt($toTimestamp)) {
                return false;
            }

            if ($keyOnly && ! (bool) ($entry['is_key_point'] ?? false)) {
                return false;
            }

            if ($searchValue !== '' && ! Str::contains($entryMessage.' '.$entryEnvironment, $searchValue)) {
                return false;
            }

            return true;
        }));
    }

    public function clear(string $path): void
    {
        file_put_contents($path, '');
    }

    private function normalizeTimestamp(string $value): string
    {
        $timestamp = trim($value);
        if ($timestamp === '') {
            return '';
        }

        try {
            return Carbon::parse($timestamp)->toDateTimeString();
        } catch (\Throwable) {
            return $timestamp;
        }
    }

    private function isKeyPoint(string $level, string $message): bool
    {
        if (in_array($level, ['error', 'critical', 'alert', 'emergency'], true)) {
            return true;
        }

        $normalizedMessage = Str::lower($message);
        $keywords = [
            'sqlstate',
            'exception',
            'failed',
            'falha',
            'products sync skipped',
            'dispatch skipped',
            'integra',
            'unknown column',
        ];

        foreach ($keywords as $keyword) {
            if (Str::contains($normalizedMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function parseFilterTimestamp(string $value): ?Carbon
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::parse($normalized);
        } catch (\Throwable) {
            return null;
        }
    }
}
