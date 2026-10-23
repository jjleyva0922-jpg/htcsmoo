<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;

class StartApplicationSession extends StartSession
{
    public function __construct(SessionManager $manager, Factory $cache)
    {
        parent::__construct($manager, fn () => $cache);
    }

    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        // Background reads must not slide the session's storage TTL or cookie expiry.
        // Use the normal middleware for every write.
        $passive = $request->isMethod('GET') && (
            $request->is('api/runtime-state', 'api/system-version', 'api/latest-notification')
            || $request->header('X-Workspace-Refresh') === 'true'
            || ($request->routeIs('notifications') && $request->header('X-Background-Request') === 'true')
        );

        if (! $passive) {
            return parent::handleStatefulRequest($request, $session, $next);
        }

        $request->setLaravelSession($this->startSession($request, $session));
        $this->collectGarbage($session);

        return $next($request);
    }
}
