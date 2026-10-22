<?php

namespace App\Http\Controllers;

use App\Models\MonitoredApp;
use App\Services\AppRegistry;
use App\Services\StatusCollector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoredAppController extends Controller
{
    public function __construct(private StatusCollector $collector) {}

    public function index(AppRegistry $registry): View
    {
        return view('apps.index', [
            'envApps' => $registry->fromEnvironment(),
            'apps' => MonitoredApp::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        MonitoredApp::create([
            'name' => $data['name'],
            'url' => rtrim($data['url'], '/'),
            'token' => $data['token'] ?? null,
            'enabled' => true,
        ]);

        $this->collector->forget();

        return redirect()->route('apps.index')->with('success', "\"{$data['name']}\" added.");
    }

    public function edit(MonitoredApp $app): View
    {
        return view('apps.edit', ['app' => $app]);
    }

    public function update(Request $request, MonitoredApp $app): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'enabled' => ['boolean'],
            'clear_token' => ['boolean'],
        ]);

        $app->fill([
            'name' => $data['name'],
            'url' => rtrim($data['url'], '/'),
            'enabled' => $request->boolean('enabled'),
        ]);

        // An empty token field keeps the current token.
        if ($request->boolean('clear_token')) {
            $app->token = null;
        } elseif (filled($data['token'] ?? null)) {
            $app->token = $data['token'];
        }

        $app->save();
        $this->collector->forget();

        return redirect()->route('apps.index')->with('success', "\"{$app->name}\" updated.");
    }

    public function destroy(MonitoredApp $app): RedirectResponse
    {
        $app->delete();
        $this->collector->forget();

        return redirect()->route('apps.index')->with('success', "\"{$app->name}\" removed.");
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
