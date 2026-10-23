<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\SessionRecovery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel normally bypasses CSRF in tests. Exercise the real token check.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_stale_native_and_async_writes_are_rejected_without_changing_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['_token' => 'current-token']);

        $this->from('/profile')->patch('/profile', ['_token' => 'stale-token', 'name' => 'Changed'])
            ->assertStatus(419)->assertSee('Your session expired')->assertSee('Sign in again (opens a new tab)')
            ->assertSee('cannot reliably restore')->assertHeader('Cache-Control', 'no-store, private');
        $this->patchJson('/profile', ['_token' => 'stale-token', 'name' => 'Changed'])
            ->assertStatus(419)->assertJsonPath('code', 'session_expired');
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertDatabaseCount('user_activity_logs', 0);
    }

    public function test_missing_tokens_are_rejected_on_guest_forms_and_the_preflight(): void
    {
        $this->post('/login', ['login' => 'someone', 'password' => 'secret-never-echo'])
            ->assertStatus(419)->assertSee('Your session expired')->assertDontSee('secret-never-echo');
        $this->postJson(route('session.check'))->assertStatus(419);
    }

    public function test_stale_async_notification_tokens_cannot_mark_anything_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create(['user_id' => $user->id, 'title' => 'Unread', 'message' => 'Keep unread', 'is_read' => false]);
        $this->actingAs($user)->withSession(['_token' => 'current-token']);
        $this->patchJson(route('notifications.read', $notification), [], ['X-CSRF-TOKEN' => 'stale-token'])
            ->assertStatus(419)->assertJsonPath('code', 'session_expired');
        $this->postJson(route('notifications.read-all'), [], ['X-CSRF-TOKEN' => 'stale-token'])
            ->assertStatus(419)->assertJsonPath('code', 'session_expired');
        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_preflight_checks_existing_token_and_account_without_rotating_token(): void
    {
        config(['session.block' => true, 'session.block_store' => 'array']);
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['_token' => 'current-token'])
            ->postJson(route('session.check'), ['_token' => 'current-token', 'account' => $user->id])
            ->assertNoContent()->assertSessionHas('_token', 'current-token');
        $this->postJson(route('session.check'), ['_token' => 'stale-token', 'account' => $user->id])
            ->assertStatus(419);
        $this->postJson(route('session.check'), ['_token' => 'current-token', 'account' => $user->id + 1])
            ->assertUnauthorized()->assertJsonPath('code', 'session_expired');
    }

    public function test_guest_async_reads_and_writes_return_the_same_expiry_contract(): void
    {
        foreach (['/api/runtime-state', '/api/requisition-catalog', '/notifications'] as $url) {
            $this->getJson($url)->assertUnauthorized()->assertJsonPath('code', 'session_expired');
        }
        $this->get('/requests', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html'])
            ->assertUnauthorized()->assertJsonPath('code', 'session_expired');
        $this->get('/dashboard', ['X-Workspace-Refresh' => 'true'])
            ->assertUnauthorized()->assertJsonPath('code', 'session_expired');
        $this->withSession(['_token' => 'current-token'])
            ->post('/notifications/read-all', ['_token' => 'current-token'])
            ->assertUnauthorized()->assertSee('Your session expired');
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_recovery_preserves_only_validated_application_screens_and_login_revalidates_intended_url(): void
    {
        $this->get(route('session.recover', ['return' => '/requests?status=pending']))
            ->assertRedirect(route('login'))->assertSessionHas('url.intended', '/requests?status=pending');

        $request = Request::create('https://app.test/session/recover');
        foreach (['https://evil.test/requests', '//evil.test/requests', '/\\evil.test', '/%2f%2fevil.test',
            'javascript:alert(1)', '/login', '/logout', '/api/runtime-state', '/not-a-route',
            'https://app.test@evil.test/requests', '/requests%0d%0aLocation:evil', '/requests#fragment'] as $unsafe) {
            $this->assertNull(SessionRecovery::safeReturnUrl($request, $unsafe), $unsafe);
        }
        $this->assertSame('/requests?page=2', SessionRecovery::safeReturnUrl($request, 'https://app.test/requests?page=2'));
        $mounted = Request::create('https://app.test/supply/index.php/session/recover', 'GET', [], [], [], [
            'SCRIPT_NAME' => '/supply/index.php', 'SCRIPT_FILENAME' => '/var/www/supply/index.php',
        ]);
        $this->assertSame('/supply/index.php', $mounted->getBaseUrl());
        $this->assertSame('/supply/index.php/requests', SessionRecovery::safeReturnUrl($mounted, '/supply/index.php/requests'));
        $this->assertNull(SessionRecovery::safeReturnUrl($mounted, '/requests'));

        $this->get(route('session.recover', ['return' => 'https://evil.test']))
            ->assertSessionHas('url.intended', '/dashboard');
        $user = User::factory()->create();
        $this->withSession(['_token' => 'current-token', 'url.intended' => 'https://evil.test'])
            ->post('/login', ['_token' => 'current-token', 'login' => $user->username, 'password' => 'password'])
            ->assertRedirect('/dashboard');
    }

    public function test_idle_background_reads_do_not_renew_database_sessions_or_session_cookies(): void
    {
        config(['session.driver' => 'database', 'session.lottery' => [0, 100]]);
        $this->withCredentials();
        $user = User::factory()->create();
        $id = Str::random(40);
        $lastActivity = now()->subMinutes(5)->timestamp;
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'last_activity' => $lastActivity,
            'payload' => base64_encode(serialize(['_token' => 'stored-token', Auth::guard('web')->getName() => $user->id])),
        ]);

        foreach ([['/api/runtime-state', []], ['/api/system-version', []], ['/api/latest-notification', []],
            ['/dashboard', ['X-Workspace-Refresh' => 'true']], ['/notifications', ['X-Background-Request' => 'true']]] as [$url, $headers]) {
            $this->resetSessionServices();
            $this->withCookie(config('session.cookie'), $id)->getJson($url, $headers)->assertOk()
                ->assertCookieMissing(config('session.cookie'));
            $this->assertSame($lastActivity, DB::table('sessions')->where('id', $id)->value('last_activity'));
        }

        $this->resetSessionServices();
        $this->withCookie(config('session.cookie'), $id)->get('/dashboard')->assertOk()->assertCookie(config('session.cookie'));
        $this->assertGreaterThan($lastActivity, DB::table('sessions')->where('id', $id)->value('last_activity'));

        DB::table('sessions')->where('id', $id)->update(['last_activity' => now()->subMinutes(config('session.lifetime') + 1)->timestamp]);
        $this->resetSessionServices();
        $this->withCookie(config('session.cookie'), $id)->getJson('/api/runtime-state')
            ->assertUnauthorized()->assertJsonPath('code', 'session_expired')->assertCookieMissing(config('session.cookie'));
        $this->resetSessionServices();
        $this->withCookie(config('session.cookie'), $id)->post('/requisitions', ['_token' => 'stored-token'])
            ->assertStatus(419)->assertSee('Your session expired');
        $this->assertDatabaseCount('requisitions', 0);
    }

    private function resetSessionServices(): void
    {
        // Model separate browser requests instead of reusing a loaded test guard/store.
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
