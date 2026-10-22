<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class StatusCollector
{
    public const UNREACHABLE = 'unreachable';

    public const CACHE_KEY = 'status.report';

    public function __construct(private AppRegistry $registry) {}

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Query every application in parallel.
     *
     * @return array{checked_at: string, apps: array<string, array<string, mixed>>, latest: array<string, ?string>, summary: array<string, int>}
     */
    public function collect(bool $fresh = false): array
    {
        if ($fresh) {
            $this->forget();
        }

        return Cache::remember(self::CACHE_KEY, config('status.cache_ttl'), fn () => $this->fetch());
    }

    /**
     * @return array{checked_at: string, apps: array<string, array<string, mixed>>, latest: array<string, ?string>, summary: array<string, int>}
     */
    private function fetch(): array
    {
        $apps = $this->registry->enabled()->all();
        $path = '/'.ltrim(config('status.path'), '/');

        $responses = $apps === [] ? [] : Http::pool(function (Pool $pool) use ($apps, $path) {
            foreach ($apps as $key => $app) {
                $pool->as($key)
                    ->withToken((string) ($app['token'] ?: config('status.token')))
                    ->acceptJson()
                    ->connectTimeout(3)
                    ->timeout(config('status.timeout'))
                    ->get(rtrim($app['url'], '/').$path);
            }
        });

        $results = [];

        foreach ($apps as $key => $app) {
            $results[$key] = $this->parse($app, $responses[$key] ?? null);
        }

        $latest = [
            'php' => $this->highest($results, 'runtime.php'),
            'laravel' => $this->highest($results, 'runtime.laravel'),
        ];

        $summary = ['ok' => 0, 'degraded' => 0, 'down' => 0, self::UNREACHABLE => 0];

        foreach ($results as $result) {
            $summary[$result['status']] = ($summary[$result['status']] ?? 0) + 1;
        }

        return [
            'checked_at' => now()->toIso8601String(),
            'apps' => $results,
            'latest' => $latest,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array{name: string, url: string, source: string}  $app
     * @return array<string, mixed>
     */
    private function parse(array $app, Response|Throwable|null $response): array
    {
        $base = ['name' => $app['name'], 'url' => $app['url'], 'source' => $app['source']];

        if (! $response instanceof Response) {
            return $base + [
                'status' => self::UNREACHABLE,
                'error' => $response instanceof ConnectionException ? 'Connection failed or timed out' : 'Unexpected error',
                'report' => null,
                'response_ms' => null,
            ];
        }

        $responseMs = isset($response->transferStats)
            ? (int) round($response->transferStats->getTransferTime() * 1000)
            : null;

        $report = $response->json();

        // A 503 with a valid report means the application is "down" but still responding.
        if (! is_array($report) || ! isset($report['status'], $report['schema'])) {
            return $base + [
                'status' => self::UNREACHABLE,
                'error' => match ($response->status()) {
                    401 => 'Token rejected (401)',
                    404 => 'Missing /api/system-info route or token not configured (404)',
                    default => 'Invalid response (HTTP '.$response->status().')',
                },
                'report' => null,
                'response_ms' => $responseMs,
            ];
        }

        return $base + [
            'status' => $report['status'],
            'error' => null,
            'report' => $report,
            'response_ms' => $responseMs,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     */
    private function highest(array $results, string $key): ?string
    {
        $versions = collect($results)
            ->map(fn (array $r) => data_get($r, 'report.'.$key))
            ->filter()
            ->unique()
            ->sort(fn (string $a, string $b) => version_compare($a, $b))
            ->values();

        return $versions->last();
    }
}
