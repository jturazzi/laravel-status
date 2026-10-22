<?php

namespace Tests\Feature;

use App\Models\MonitoredApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'status.token' => 'shared-token',
            'status.auth.username' => 'admin',
            'status.auth.password' => 'secret',
            'status.apps' => 'Alpha|https://alpha.test|alpha-token, Beta|https://beta.test',
        ]);

        MonitoredApp::create(['name' => 'Gamma', 'url' => 'https://gamma.test']);
        MonitoredApp::create(['name' => 'Delta', 'url' => 'https://delta.test', 'token' => 'delta-token']);
        MonitoredApp::create(['name' => 'Epsilon', 'url' => 'https://epsilon.test']);
        MonitoredApp::create(['name' => 'Disabled', 'url' => 'https://disabled.test', 'enabled' => false]);

        $down = $this->fixture('beta');
        $down['status'] = 'down';
        $down['issues'] = [['check' => 'database', 'level' => 'down', 'message' => 'Database unreachable']];
        $down['database'] = ['connection' => 'mysql', 'driver' => 'mysql', 'ok' => false, 'error' => 'QueryException (2002)'];

        Http::fake([
            'alpha.test/*' => Http::response($this->fixture('alpha')),
            'beta.test/*' => Http::response($this->fixture('beta')),
            'gamma.test/*' => Http::response($down, 503),
            'delta.test/*' => Http::response(['message' => 'Unauthenticated.'], 401),
            'epsilon.test/*' => fn () => throw new ConnectionException('timeout'),
            'disabled.test/*' => Http::response($this->fixture('beta')),
        ]);
    }

    private function fixture(string $app): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/system-info-{$app}.json")), true);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Basic '.base64_encode('admin:secret')];
    }

    public function test_requires_basic_auth(): void
    {
        $this->get('/')->assertUnauthorized()->assertHeader('WWW-Authenticate');
        $this->get('/apps')->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:wrong')])->get('/')->assertUnauthorized();
    }

    public function test_is_closed_when_no_credentials_configured(): void
    {
        config(['status.auth.username' => null, 'status.auth.password' => null]);

        $this->withHeaders(['Authorization' => 'Basic '.base64_encode(':')])->get('/')->assertUnauthorized();
    }

    public function test_dashboard_lists_env_and_database_apps(): void
    {
        $this->withHeaders($this->auth())->get('/')
            ->assertOk()
            ->assertSee('4 of 5 applications need attention')
            ->assertSeeInOrder(['Down', 'Unreachable'])
            ->assertSee(['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon'])
            ->assertDontSee('Disabled')
            ->assertSee('2.4.0')
            ->assertSee('is a queue worker running?')
            ->assertSee('oldest ')
            ->assertSee('Token rejected (401)')
            ->assertSee('No response within')
            ->assertSee('Database unreachable');
    }

    public function test_dashboard_shows_empty_state(): void
    {
        config(['status.apps' => '']);
        MonitoredApp::query()->delete();

        $this->withHeaders($this->auth())->get('/')->assertOk()->assertSee('No applications yet')->assertSee('Add an application');
    }

    public function test_json_summary_and_version_drift(): void
    {
        $data = $this->withHeaders($this->auth())->getJson('/status.json')->assertOk()->json();

        $this->assertSame(['env-alpha', 'env-beta', 'db-2', 'db-3', 'db-1'], array_keys($data['apps']));
        $this->assertSame('env', $data['apps']['env-alpha']['source']);
        $this->assertSame('database', $data['apps']['db-1']['source']);
        $this->assertSame('down', $data['apps']['db-1']['status']);
        $this->assertSame(['ok' => 1, 'degraded' => 1, 'down' => 1, 'unreachable' => 2], $data['summary']);
        $this->assertSame('13.38.0', $data['latest']['laravel']);
        // Beta and Gamma (down, but still reporting) run 13.11.1.
        $this->assertSame(2, $data['behind']['laravel']);
    }

    public function test_sends_per_app_token_or_shared_token(): void
    {
        $this->withHeaders($this->auth())->getJson('/status.json')->assertOk();

        $sent = fn (string $host, string $token) => Http::assertSent(fn (Request $r) => str_starts_with($r->url(), "https://{$host}/api/system-info")
            && $r->hasHeader('Authorization', "Bearer {$token}"));

        $sent('alpha.test', 'alpha-token');
        $sent('beta.test', 'shared-token');
        $sent('gamma.test', 'shared-token');
        $sent('delta.test', 'delta-token');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'disabled.test'));
    }

    public function test_results_are_cached_until_refresh(): void
    {
        $this->withHeaders($this->auth())->getJson('/status.json');
        $first = count(Http::recorded());

        $this->withHeaders($this->auth())->getJson('/status.json');
        $this->assertCount($first, Http::recorded());

        $this->withHeaders($this->auth())->getJson('/status.json?refresh=1');
        $this->assertCount($first * 2, Http::recorded());
    }
}
