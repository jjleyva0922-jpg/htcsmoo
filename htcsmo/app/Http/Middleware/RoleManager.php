<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth; // <--- ADD THIS LINE

class RoleManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $userRole = $request->user()->role;

        // Fix: Remove any accidental spaces from the roles list coming from web.php
        $cleanRoles = array_map('trim', $roles);

        // Check if the user's role is in the cleaned list
        if (!in_array($userRole, $cleanRoles)) {
            return redirect('dashboard')->with('error', "Access Denied. Your role is '{$userRole}', but this page requires one of these: " . implode(', ', $cleanRoles));
        }

        return $next($request);
    }
}
