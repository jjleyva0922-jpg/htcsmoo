<?php

namespace App\Http\Controllers;

use App\Support\SessionRecovery;
use Illuminate\Http\Request;

class SessionRecoveryController extends Controller
{
    public function check(Request $request)
    {
        // CSRF validation runs before this endpoint. No token refresh or action replay.
        if ($request->input('account') !== null
            && (string) $request->user()?->getAuthIdentifier() !== (string) $request->input('account')) {
            return SessionRecovery::response($request, 401);
        }

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }

    public function recover(Request $request)
    {
        $return = SessionRecovery::safeReturnUrl($request, $request->query('return')) ?? route('dashboard', absolute: false);

        if ($request->user()) {
            return redirect($return)->header('Cache-Control', 'no-store, private');
        }

        $request->session()->put('url.intended', $return);

        return redirect()->route('login')->with('status', 'Your session expired. Sign in again to continue.')
            ->header('Cache-Control', 'no-store, private');
    }
}
