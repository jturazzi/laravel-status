<?php

namespace App\Services;

use App\Models\MonitoredApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AppRegistry
{
    /**
     * Every enabled application, from the environment then from the database.
     *
     * @return Collection<string, array{name: string, url: string, token: ?string, source: string, id: ?int}>
     */
    public function enabled(): Collection
    {
        $database = MonitoredApp::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (MonitoredApp $app) => [self::key($app) => $this->fromModel($app)]);

        return $this->fromEnvironment()->merge($database);
    }

    /**
     * Any application by key ("env-<slug>" or "db-<id>"), enabled or not.
     *
     * @return array{name: string, url: string, token: ?string, source: string, id: ?int}|null
     */
    public function find(string $key): ?array
    {
        if (str_starts_with($key, 'db-')) {
            $app = MonitoredApp::find((int) substr($key, 3));

            return $app ? $this->fromModel($app) : null;
        }

        return $this->fromEnvironment()->get($key);
    }

    public static function key(MonitoredApp $app): string
    {
        return 'db-'.$app->id;
    }

    /**
     * @return array{name: string, url: string, token: ?string, source: string, id: int}
     */
    private function fromModel(MonitoredApp $app): array
    {
        return [
            'name' => $app->name,
            'url' => $app->url,
            'token' => $app->token,
            'source' => 'database',
            'id' => $app->id,
        ];
    }

    /**
     * Applications declared in STATUS_APPS ("Name|url|token,...").
     *
     * @return Collection<string, array{name: string, url: string, token: ?string, source: string, id: null}>
     */
    public function fromEnvironment(): Collection
    {
        return Str::of((string) config('status.apps'))
            ->explode(',')
            ->map(fn (string $entry) => array_map('trim', explode('|', $entry)))
            ->filter(fn (array $parts) => count($parts) >= 2 && $parts[0] !== '' && filter_var($parts[1], FILTER_VALIDATE_URL))
            ->mapWithKeys(fn (array $parts) => ['env-'.Str::slug($parts[0]) => [
                'name' => $parts[0],
                'url' => $parts[1],
                'token' => ($parts[2] ?? '') !== '' ? $parts[2] : null,
                'source' => 'env',
                'id' => null,
            ]]);
    }
}
