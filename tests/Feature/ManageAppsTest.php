<?php

namespace Tests\Feature;

use App\Models\MonitoredApp;
use App\Services\AppRegistry;
use App\Services\StatusCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManageAppsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'status.auth.username' => 'admin',
            'status.auth.password' => 'secret',
            'status.apps' => 'From Env|https://env.test',
        ]);

        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')]);

        Http::fake([
            'healthy.test/*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/system-info-beta.json')), true)),
            'degraded.test/*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/system-info-alpha.json')), true)),
            '*' => Http::response('Unauthorized', 401),
        ]);
    }

    public function test_adding_an_app_checks_it_right_away(): void
    {
        $this->post('/apps', ['name' => 'Healthy', 'url' => 'https://healthy.test'])
            ->assertSessionHas('success', 'Healthy added.')
            ->assertSessionHas('check', ['status' => 'ok', 'message' => 'Healthy responds and reports no issue (Laravel 13.11.1).']);

        $this->post('/apps', ['name' => 'Degraded', 'url' => 'https://degraded.test'])
            ->assertSessionHas('check', ['status' => 'degraded', 'message' => 'Degraded responds but reports: Oldest pending job has waited 9 days: is a queue worker running?, 1 failed job(s).']);

        $this->post('/apps', ['name' => 'Locked', 'url' => 'https://locked.test'])
            ->assertSessionHas('check.status', 'unreachable')
            ->assertSessionHas('check.message', fn (string $m) => str_starts_with($m, "Locked doesn't respond. Token rejected (401)."));
    }

    public function test_checks_env_and_database_apps_on_demand(): void
    {
        $app = MonitoredApp::create(['name' => 'Healthy', 'url' => 'https://healthy.test', 'enabled' => false]);

        $this->post("/apps/check/db-{$app->id}")->assertRedirect('/apps')->assertSessionHas('check.status', 'ok');
        $this->post('/apps/check/env-from-env')->assertRedirect('/apps')->assertSessionHas('check.status', 'unreachable');
        $this->post('/apps/check/db-999')->assertNotFound();
        $this->post('/apps/check/env-unknown')->assertNotFound();
    }

    public function test_lists_env_and_database_apps(): void
    {
        MonitoredApp::create(['name' => 'From Db', 'url' => 'https://db.test', 'token' => 'hidden-token']);

        $this->get('/apps')
            ->assertOk()
            ->assertSeeInOrder(['From Env', 'Environment', 'From Db', 'This page'])
            ->assertDontSee('hidden-token');
    }

    public function test_adds_an_app_with_an_encrypted_token(): void
    {
        $this->post('/apps', ['name' => 'Billing', 'url' => 'https://billing.test/', 'token' => 'my-token'])
            ->assertRedirect('/apps');

        $app = MonitoredApp::sole();
        $this->assertSame('https://billing.test', $app->url);
        $this->assertSame('my-token', $app->token);
        $this->assertNotSame('my-token', DB::table('monitored_apps')->value('token'));
        $this->assertTrue($app->enabled);
    }

    public function test_validates_input(): void
    {
        $this->post('/apps', ['name' => '', 'url' => 'ftp://nope'])->assertSessionHasErrors(['name', 'url']);
        $this->post('/apps', ['name' => 'X', 'url' => 'not a url'])->assertSessionHasErrors('url');
        $this->assertSame(0, MonitoredApp::count());
    }

    public function test_update_keeps_token_when_left_empty(): void
    {
        $app = MonitoredApp::create(['name' => 'Billing', 'url' => 'https://billing.test', 'token' => 'kept']);

        $this->put("/apps/{$app->id}", ['name' => 'Billing 2', 'url' => 'https://billing2.test', 'token' => '', 'enabled' => '1'])
            ->assertRedirect('/apps');

        $app->refresh();
        $this->assertSame('Billing 2', $app->name);
        $this->assertSame('kept', $app->token);
    }

    public function test_update_can_replace_or_clear_token_and_disable(): void
    {
        $app = MonitoredApp::create(['name' => 'Billing', 'url' => 'https://billing.test', 'token' => 'old']);

        $this->put("/apps/{$app->id}", ['name' => 'Billing', 'url' => 'https://billing.test', 'token' => 'new', 'enabled' => '1']);
        $this->assertSame('new', $app->refresh()->token);

        $this->put("/apps/{$app->id}", ['name' => 'Billing', 'url' => 'https://billing.test', 'clear_token' => '1', 'enabled' => '0']);
        $app->refresh();
        $this->assertNull($app->token);
        $this->assertFalse($app->enabled);
    }

    public function test_removes_an_app(): void
    {
        $app = MonitoredApp::create(['name' => 'Billing', 'url' => 'https://billing.test']);

        $this->delete("/apps/{$app->id}")->assertRedirect('/apps');
        $this->assertSame(0, MonitoredApp::count());
    }

    public function test_changes_clear_the_dashboard_cache(): void
    {
        Cache::put(StatusCollector::CACHE_KEY, ['stale'], 60);

        $this->post('/apps', ['name' => 'Billing', 'url' => 'https://billing.test']);

        $this->assertNull(Cache::get(StatusCollector::CACHE_KEY));
    }

    public function test_env_parsing_ignores_invalid_entries(): void
    {
        config(['status.apps' => ' A | https://a.test | tok ,B|not-a-url,|https://c.test,D,E|https://e.test|']);

        $apps = app(AppRegistry::class)->fromEnvironment();

        $this->assertSame(['env-a', 'env-e'], $apps->keys()->all());
        $this->assertSame('tok', $apps['env-a']['token']);
        $this->assertNull($apps['env-e']['token']);
    }
}
