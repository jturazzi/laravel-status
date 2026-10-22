<?php

namespace App\Http\Controllers;

use App\Models\MonitoredApp;
use App\Services\AppRegistry;
use App\Services\StatusCollector;
use App\Support\IssueText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoredAppController extends Controller
{
    public function __construct(private StatusCollector $collector, private AppRegistry $registry) {}

    public function index(): View
    {
        return view('apps.index', [
            'envApps' => $this->registry->fromEnvironment(),
            'apps' => MonitoredApp::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $app = MonitoredApp::create([
            'name' => $data['name'],
            'url' => rtrim($data['url'], '/'),
            'token' => $data['token'] ?? null,
            'enabled' => true,
        ]);

        $this->collector->forget();

        return $this->backWithCheck(AppRegistry::key($app), __(':name added.', ['name' => $app->name]));
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

        return $this->backWithCheck(AppRegistry::key($app), __(':name saved.', ['name' => $app->name]));
    }

    public function destroy(MonitoredApp $app): RedirectResponse
    {
        $app->delete();
        $this->collector->forget();

        return redirect()->route('apps.index')->with('success', __(':name removed.', ['name' => $app->name]));
    }

    public function check(string $key): RedirectResponse
    {
        abort_unless($this->registry->find($key), 404);

        return $this->backWithCheck($key);
    }

    /**
     * Query the application right away and report the outcome.
     */
    private function backWithCheck(string $key, ?string $success = null): RedirectResponse
    {
        $app = $this->registry->find($key);
        $result = $this->collector->check($app);

        $message = match (true) {
            $result['report'] === null => __(":name doesn't respond. :error", ['name' => $app['name'], 'error' => IssueText::error($result)]),
            $result['status'] === 'ok' => __(':name responds and reports no issue (Laravel :version).', ['name' => $app['name'], 'version' => $result['report']['runtime']['laravel']]),
            default => __(':name responds but reports: :issues.', [
                'name' => $app['name'],
                'issues' => implode(', ', array_map(IssueText::translate(...), array_column($result['report']['issues'], 'message'))),
            ]),
        };

        return redirect()->route('apps.index')
            ->with('success', $success)
            ->with('check', ['status' => $result['status'], 'message' => $message]);
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
