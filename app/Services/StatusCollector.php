<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class StatusCollector
{
    public const UNREACHABLE = 'unreachable';

    public const CACHE_KEY = 'status.report';

    /** Tracked versions and where each application reports them. */
    public const TRACKED = [
        'php' => 'runtime.php',
        'laravel' => 'runtime.laravel',
        'system_info' => 'packages.jturazzi/laravel-system-info',
    ];

    public function __construct(private AppRegistry $registry, private UpstreamVersions $upstream) {}

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Query every application in parallel.
     *
     * @return array{checked_at: string, apps: array<string, array<string, mixed>>, latest: array<string, ?string>, latest_source: array<string, string>, behind: array<string, int>, summary: array<string, int>}
     */
    public function collect(bool $fresh = false): array
    {
        if ($fresh) {
            $this->forget();
        }

        return Cache::remember(self::CACHE_KEY, config('status.cache_ttl'), fn () => $this->fetch());
    }

    /**
     * @return array{checked_at: string, apps: array<string, array<string, mixed>>, latest: array<string, ?string>, latest_source: array<string, string>, behind: array<string, int>, summary: array<string, int>}
     */
    private function fetch(): array
    {
        $apps = $this->registry->enabled()->all();

        $responses = $apps === [] ? [] : Http::pool(function (Pool $pool) use ($apps) {
            foreach ($apps as $key => $app) {
                $this->send($pool->as($key), $app);
            }
        });

        $results = [];

        foreach ($apps as $key => $app) {
            $results[$key] = $this->parse($app, $responses[$key] ?? null);
        }

        // The latest published release, unless an application already runs a newer one
        // (or the lookup failed): then the newest version among the applications.
        $upstream = $this->upstream->latest();
        $latest = [];
        $latestSource = [];

        foreach (self::TRACKED as $k => $key) {
            $newest = $this->highest($results, $key);
            $fromUpstream = ($upstream[$k] ?? null) !== null && ($newest === null || version_compare($upstream[$k], $newest, '>='));

            $latest[$k] = $fromUpstream ? $upstream[$k] : $newest;
            $latestSource[$k] = $fromUpstream ? 'upstream' : 'apps';
        }

        $behind = [];

        foreach (self::TRACKED as $k => $key) {
            $behind[$k] = $this->countBehind($results, $key, $latest[$k]);
        }

        $summary = ['ok' => 0, 'degraded' => 0, 'down' => 0, self::UNREACHABLE => 0];

        foreach ($results as $result) {
            $summary[$result['status']] = ($summary[$result['status']] ?? 0) + 1;
        }

        return [
            'checked_at' => now()->toIso8601String(),
            'apps' => $results,
            'latest' => $latest,
            'latest_source' => $latestSource,
            'behind' => $behind,
            'summary' => $summary,
        ];
    }

    /**
     * Query a single application, bypassing the cache.
     *
     * @param  array{name: string, url: string, token: ?string, source: string}  $app
     * @return array<string, mixed>
     */
    public function check(array $app): array
    {
        try {
            $response = $this->send(Http::createPendingRequest(), $app);
        } catch (Throwable $e) {
            $response = $e;
        }

        return $this->parse($app, $response);
    }

    /**
     * @param  array{url: string, token: ?string}  $app
     */
    private function send(PendingRequest $request, array $app): mixed
    {
        return $request
            ->withToken((string) ($app['token'] ?: config('status.token')))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(config('status.timeout'))
            ->get(rtrim($app['url'], '/').'/'.ltrim(config('status.path'), '/'));
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
                ...$this->error($response instanceof ConnectionException
                    ? ['No response within :seconds s. Check the URL and that the application is running.', ['seconds' => config('status.timeout')]]
                    : ['The request failed before reaching the application.', []]),
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
                ...$this->error(match ($response->status()) {
                    401 => ['Token rejected (401). It must match SYSTEM_INFO_TOKEN in the application.', []],
                    404 => ['No system-info route (404). Install jturazzi/laravel-system-info and set SYSTEM_INFO_TOKEN.', []],
                    default => ['Unexpected response (HTTP :status). Check the URL points to the application root.', ['status' => $response->status()]],
                }),
                'report' => null,
                'response_ms' => $responseMs,
            ];
        }

        return $base + [
            'status' => $report['status'],
            'error' => null,
            'error_i18n' => null,
            'report' => $report,
            'response_ms' => $responseMs,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function version(array $result, string $key): ?string
    {
        $version = data_get($result, 'report.'.$key);

        return is_string($version) ? ltrim($version, 'v') : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     */
    private function highest(array $results, string $key): ?string
    {
        $versions = collect($results)
            ->map(fn (array $r) => $this->version($r, $key))
            ->filter()
            ->unique()
            ->sort(fn (string $a, string $b) => version_compare($a, $b))
            ->values();

        return $versions->last();
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     */
    private function countBehind(array $results, string $key, ?string $latest): int
    {
        return collect($results)
            ->map(fn (array $r) => $this->version($r, $key))
            ->filter(fn (?string $v) => $v && $latest && version_compare($v, $latest, '<'))
            ->count();
    }

    /**
     * The English message (for /status.json) plus its template and parameters,
     * translated when displayed: cached results are shared by every language.
     *
     * @param  array{0: string, 1: array<string, mixed>}  $message
     * @return array{error: string, error_i18n: array{key: string, params: array<string, mixed>}}
     */
    private function error(array $message): array
    {
        [$key, $params] = $message;

        return [
            'error' => __($key, $params, 'en'),
            'error_i18n' => ['key' => $key, 'params' => $params],
        ];
    }
}
