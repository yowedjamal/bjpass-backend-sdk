<?php

namespace BjPass\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use BjPass\Facades\BjPass;
use Illuminate\Support\Facades\Log;

class BjPassAuthMiddleware
{
    public function handle(Request $request, Closure $next, string $guard = null)
    {
        try {
            // Check if user is authenticated
            if (!BjPass::isAuthenticated()) {
                if ($request->expectsJson()) {
                    return new JsonResponse([
                        'error' => 'unauthenticated',
                        'message' => 'User is not authenticated'
                    ], 401);
                }

                // Redirect to login for web requests
                return redirect()->route('bjpass.login');
            }

            // Add user info to request for easy access
            $request->merge(['bjpass_user' => BjPass::getUserInfo()]);

            return $next($request);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'error' => 'authentication_error',
                    'message' => $e->getMessage()
                ], 500);
            }

            // Log error and redirect for web requests
            Log::error('BjPass authentication middleware error', [
                'error' => $e->getMessage(),
                'request_url' => $request->fullUrl()
            ]);

            return redirect()->route('bjpass.login');
        }
    }
}
