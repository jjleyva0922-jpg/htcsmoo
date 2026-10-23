<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SessionRecovery
{
    public static function safeReturnUrl(Request $request, mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || preg_match('/[\\\\\x00-\x20\x7f]/', rawurldecode($value))) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            $origin = parse_url($request->getSchemeAndHttpHost());
            if (($parts['scheme'] ?? '') !== $origin['scheme']
                || strtolower($parts['host'] ?? '') !== strtolower($origin['host'])
                || ($parts['port'] ?? null) !== ($origin['port'] ?? null)
                || isset($parts['user']) || isset($parts['pass'])) {
                return null;
            }
        }

        $path = $parts['path'] ?? '';
        if (! str_starts_with($path, '/') || str_starts_with($path, '//')
            || preg_match('/%|\\\\|\/\//', rawurldecode($path))) {
            return null;
        }

        $base = $request->getBaseUrl();
        if ($base !== '' && ! str_starts_with($path, $base.'/')) {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($request->getSchemeAndHttpHost().substr($path, strlen($base)), 'GET'));
        } catch (HttpExceptionInterface) {
            return null;
        }

        // Return only to application screens, never auth links, APIs, or action/download endpoints.
        if (! in_array($route->getName(), [
            'dashboard', 'notifications', 'profile.edit', 'requests.index', 'requests.archive', 'requests.show',
            'inventory.index', 'inventory.create', 'inventory.edit', 'requisitions.edit',
            'admin.approvals', 'admin.users.pending', 'admin.users.manage', 'admin.archive',
            'admin.reports.index', 'admin.reports.builder', 'legal.terms', 'legal.privacy',
        ], true)) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public static function url(Request $request): string
    {
        $return = self::safeReturnUrl($request, $request->isMethod('GET') && ! $request->ajax()
            ? $request->fullUrl() : $request->headers->get('referer'));

        return route('session.recover', $return ? ['return' => $return] : []);
    }

    public static function response(Request $request, int $status)
    {
        $data = [
            'message' => 'Your session expired. Sign in again before continuing. Your action was not submitted.',
            'code' => 'session_expired',
            'recovery_url' => self::url($request),
        ];

        return ($request->expectsJson() || $request->ajax() || $request->hasHeader('X-Workspace-Refresh')
            ? response()->json($data, $status)
            : response()->view('errors.session-expired', $data, $status))
            ->header('Cache-Control', 'no-store, private');
    }
}
