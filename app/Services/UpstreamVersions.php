<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Latest stable PHP, Laravel and laravel-system-info releases, as published on php.net and Packagist.
 */
class UpstreamVersions
{
    public const CACHE_KEY = 'status.upstream';

    public const PHP_URL = 'https://www.php.net/releases/?json';

    public const LARAVEL_URL = 'https://repo.packagist.org/p2/laravel/framework.json';

    public const SYSTEM_INFO_URL = 'https://repo.packagist.org/p2/jturazzi/laravel-system-info.json';

    // Seconds before retrying a failed lookup, so a network blip does not hide the releases for hours.
    private const RETRY_TTL = 600;

    /**
     * @return array{php: ?string, laravel: ?string, system_info: ?string} null when disabled or unreachable
     */
    public function latest(): array
    {
        if (! config('status.upstream.enabled')) {
            return ['php' => null, 'laravel' => null, 'system_info' => null];
        }

        $cached = Cache::get(self::CACHE_KEY);

        // A cache written before system_info was tracked lacks the key: look it up again.
        if (is_array($cached) && array_key_exists('system_info', $cached)) {
            return $cached;
        }

        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('php')->acceptJson()->connectTimeout(3)->timeout(5)->get(self::PHP_URL),
            $pool->as('laravel')->acceptJson()->connectTimeout(3)->timeout(5)->get(self::LARAVEL_URL),
            $pool->as('system_info')->acceptJson()->connectTimeout(3)->timeout(5)->get(self::SYSTEM_INFO_URL),
        ]);

        $latest = [
            'php' => $this->highest($this->json($responses['php'] ?? null, null)),
            'laravel' => $this->highest($this->json($responses['laravel'] ?? null, 'packages.laravel/framework')),
            'system_info' => $this->highest($this->json($responses['system_info'] ?? null, 'packages.jturazzi/laravel-system-info')),
        ];

        Cache::put(self::CACHE_KEY, $latest, in_array(null, $latest, true) ? self::RETRY_TTL : config('status.upstream.cache_ttl'));

        return $latest;
    }

    /**
     * Entries of a successful JSON response: php.net lists one release per major
     * version, Packagist every tagged version of the package.
     *
     * @return list<mixed>
     */
    private function json(mixed $response, ?string $path): array
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return [];
        }

        $entries = $path === null ? $response->json() : data_get($response->json(), $path);

        return is_array($entries) ? array_values($entries) : [];
    }

    /**
     * Highest stable "x.y.z" version: tags like "v13.38.0" are accepted, pre-releases ignored.
     *
     * @param  list<mixed>  $entries
     */
    private function highest(array $entries): ?string
    {
        return collect($entries)
            ->map(fn (mixed $entry) => is_array($entry) && is_string($entry['version'] ?? null) ? ltrim($entry['version'], 'v') : null)
            ->filter(fn (?string $version) => $version !== null && preg_match('/^\d+\.\d+\.\d+$/', $version))
            ->sort(fn (string $a, string $b) => version_compare($a, $b))
            ->last();
    }
}
