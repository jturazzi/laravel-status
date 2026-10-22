<?php

namespace Tests\Feature;

use App\Services\UpstreamVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpstreamVersionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'status.token' => 'shared-token',
            'status.auth.username' => 'admin',
            'status.auth.password' => 'secret',
            // Alpha: PHP 8.5.12 / Laravel 13.38.0, Beta: PHP 8.5.12 / Laravel 13.11.1
            'status.apps' => 'Alpha|https://alpha.test,Beta|https://beta.test',
            'status.upstream.enabled' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $upstream
     */
    private function fake(array $upstream): void
    {
        Http::fake($upstream + [
            'alpha.test/*' => Http::response($this->fixture('alpha')),
            'beta.test/*' => Http::response($this->fixture('beta')),
        ]);
    }

    private function fixture(string $app): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/system-info-{$app}.json")), true);
    }

    private function releases(string $php, string ...$laravel): array
    {
        return [
            'www.php.net/*' => Http::response([
                '7' => ['version' => '7.4.33'],
                '8' => ['version' => $php],
            ]),
            'repo.packagist.org/*' => Http::response([
                'packages' => ['laravel/framework' => array_map(fn (string $v) => ['version' => $v], $laravel)],
            ]),
        ];
    }

    private function report(): array
    {
        return $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])
            ->getJson('/status.json')->assertOk()->json();
    }

    public function test_compares_with_the_latest_published_releases(): void
    {
        $this->fake($this->releases('8.5.13', 'v14.0.0-beta1', 'v13.39.0', 'v13.38.0', '12.x-dev'));

        $data = $this->report();

        $this->assertSame(['php' => '8.5.13', 'laravel' => '13.39.0'], $data['latest']);
        $this->assertSame(['php' => 'upstream', 'laravel' => 'upstream'], $data['latest_source']);
        $this->assertSame(['php' => 2, 'laravel' => 2], $data['behind']);

        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')])->get('/')
            ->assertSee('is the latest release; 2 applications are behind.');
    }

    public function test_releases_are_cached(): void
    {
        $this->fake($this->releases('8.5.13', 'v13.39.0'));

        $this->report();
        app(UpstreamVersions::class)->latest();

        Http::assertSentCount(4); // 2 applications + php.net + Packagist, once
        Http::assertSent(fn (Request $r) => $r->url() === UpstreamVersions::PHP_URL);
    }

    public function test_an_application_ahead_of_the_release_wins(): void
    {
        $this->fake($this->releases('8.5.11', 'v13.20.0'));

        $data = $this->report();

        $this->assertSame(['php' => '8.5.12', 'laravel' => '13.38.0'], $data['latest']);
        $this->assertSame(['php' => 'apps', 'laravel' => 'apps'], $data['latest_source']);
        $this->assertSame(['php' => 0, 'laravel' => 1], $data['behind']);
    }

    public function test_falls_back_to_the_applications_when_unreachable(): void
    {
        $this->fake([
            'www.php.net/*' => fn () => throw new ConnectionException('timeout'),
            'repo.packagist.org/*' => Http::response('Service Unavailable', 503),
        ]);

        $data = $this->report();

        $this->assertSame(['php' => '8.5.12', 'laravel' => '13.38.0'], $data['latest']);
        $this->assertSame(['php' => 'apps', 'laravel' => 'apps'], $data['latest_source']);
        $this->assertSame(['php' => null, 'laravel' => null], Cache::get(UpstreamVersions::CACHE_KEY));
    }

    public function test_disabled_sends_no_outbound_request(): void
    {
        config(['status.upstream.enabled' => false]);
        $this->fake($this->releases('8.5.13', 'v13.39.0'));

        $this->assertSame('apps', $this->report()['latest_source']['laravel']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'php.net') || str_contains($r->url(), 'packagist'));
    }
}
