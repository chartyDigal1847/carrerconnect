<?php

namespace Tests\Feature;

use App\Http\Middleware\ForceModuleSessionCookies;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Session\Middleware\StartSession;
use Tests\TestCase;

class EmbeddedSessionMiddlewareOrderTest extends TestCase
{
    public function test_module_session_cookie_middleware_runs_before_session_start(): void
    {
        $middleware = app(Kernel::class)->getMiddlewareGroups()['api'];

        $startSessionIndex = array_search(StartSession::class, $middleware, true);
        $forceCookieIndex = array_search(ForceModuleSessionCookies::class, $middleware, true);

        $this->assertNotFalse($startSessionIndex, 'StartSession should be registered on the API group.');
        $this->assertNotFalse($forceCookieIndex, 'ForceModuleSessionCookies should be registered on the API group.');
        $this->assertLessThan($startSessionIndex, $forceCookieIndex, 'Embedded session cookie config must run before Laravel starts the session.');
    }
}
