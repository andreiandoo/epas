<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify organizer user status.
 *
 * Ensures the authenticated organizer user:
 * - Belongs to an active organizer
 * - The organizer is not suspended
 * - The organizer is verified (optional)
 */
class VerifyOrganizerStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        $user = auth('organizer')->user();

        if (!$user) {
            return redirect()->route('filament.organizer.auth.login');
        }

        $organizer = $user->organizer;

        if (!$organizer) {
            auth('organizer')->logout();
            return redirect()
                ->route('filament.organizer.auth.login')
                ->withErrors(['email' => 'Your account is not associated with an organizer.']);
        }

        // Check if organizer is suspended
        if ($organizer->status === 'suspended') {
            auth('organizer')->logout();
            return redirect()
                ->route('filament.organizer.auth.login')
                ->withErrors(['email' => 'Your organizer account has been suspended. Please contact support.']);
        }

        // Check if organizer is inactive (not yet approved)
        if ($organizer->status === 'pending') {
            auth('organizer')->logout();
            return redirect()
                ->route('filament.organizer.auth.login')
                ->withErrors(['email' => 'Your organizer registration is pending approval.']);
        }

        // Check if organizer is rejected
        if ($organizer->status === 'rejected') {
            auth('organizer')->logout();
            return redirect()
                ->route('filament.organizer.auth.login')
                ->withErrors(['email' => 'Your organizer registration was not approved.']);
        }

        // Optional: check for verification requirement
        if (in_array('verified', $options) && !$organizer->is_verified) {
            return redirect()
                ->route('filament.organizer.pages.dashboard')
                ->with('warning', 'Some features require account verification.');
        }

        return $next($request);
    }
}
