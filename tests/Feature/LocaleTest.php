<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.locale' => 'en',
            'status.auth.username' => 'admin',
            'status.auth.password' => 'secret',
            'status.apps' => 'Alpha|https://alpha.test,Locked|https://locked.test',
        ]);

        Http::fake([
            'alpha.test/*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/system-info-alpha.json')), true)),
            'locked.test/*' => Http::response('Unauthorized', 401),
        ]);

        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('admin:secret')]);
    }

    public function test_english_by_default(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('2 of 2 applications need attention')
            ->assertSee('1 failed job(s)');
    }

    public function test_french_from_the_switcher_is_remembered(): void
    {
        $this->get('/?lang=fr')
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSee('2 applications sur 2 demandent votre attention')
            ->assertSee('1 job(s) en échec')
            ->assertSee('Le plus ancien job attend depuis')
            ->assertSee('Jeton refusé (401)')
            ->assertCookie(SetLocale::COOKIE, 'fr');

        $this->withCookie(SetLocale::COOKIE, 'fr')->get('/')->assertSee('Tableau de bord');
    }

    public function test_browser_language_is_used_without_a_choice(): void
    {
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.5')->get('/')
            ->assertOk()
            ->assertSee('Tableau de bord');
    }

    public function test_unsupported_language_falls_back(): void
    {
        $this->get('/?lang=de')->assertOk()->assertSee('Dashboard')->assertCookieMissing(SetLocale::COOKIE);
    }

    public function test_json_stays_in_english(): void
    {
        $data = $this->withCookie(SetLocale::COOKIE, 'fr')->getJson('/status.json')->json();

        $this->assertStringStartsWith('Token rejected (401)', $data['apps']['env-locked']['error']);
    }
}
