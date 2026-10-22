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
            ->mapWithKeys(fn (MonitoredApp $app) => ['db-'.$app->id => [
                'name' => $app->name,
                'url' => $app->url,
                'token' => $app->token,
                'source' => 'database',
                'id' => $app->id,
            ]]);

        return $this->fromEnvironment()->merge($database);
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
