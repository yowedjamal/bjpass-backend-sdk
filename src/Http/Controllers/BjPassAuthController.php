<?php

namespace BjPass\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use BjPass\Facades\BjPass;
use BjPass\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Log;

class BjPassAuthController extends Controller
{
    /**
     * Start authentication flow - redirect to OIDC provider
     */
    public function start(Request $request): RedirectResponse
    {
        try {
            $authData = BjPass::createAuthorizationUrl();
            
            // Store additional parameters if needed
            if ($request->has('return_url')) {
                session(['bjpass_return_url' => $request->get('return_url')]);
            }

            return redirect()->away($authData['authorization_url']);

        } catch (\Exception $e) {
            Log::error('Failed to start authentication', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return redirect()->route('bjpass.error', [
                'error' => 'auth_start_failed',
                'message' => 'Failed to start authentication'
            ]);
        }
    }

    /**
     * Handle OIDC provider callback
     */
    public function callback(Request $request): \Illuminate\Contracts\View\View
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $errorDescription = $request->query('error_description');

        if ($error) {
            Log::warning('OIDC provider returned error', [
                'error' => $error,
                'description' => $errorDescription
            ]);

            return view('bjpass::error', [
                'error' => $error,
                'message' => $errorDescription ?? 'Authentication failed'
            ]);
        }

        if (!$code || !$state) {
            Log::warning('Missing code or state in callback', [
                'has_code' => !empty($code),
                'has_state' => !empty($state)
            ]);

            return view('bjpass::error', [
                'error' => 'invalid_callback',
                'message' => 'Invalid callback parameters'
            ]);
        }

        try {
            // Exchange code for tokens
            $result = BjPass::exchangeCode($code, $state);

            // Get return URL if stored
            $returnUrl = session('bjpass_return_url', config('bjpass.default_redirect_after_login', '/'));
            session()->forget('bjpass_return_url');

            Log::info('Authentication successful', [
                'user_id' => $result['user']['sub'] ?? null,
                'return_url' => $returnUrl
            ]);

            // Return success view that will communicate with frontend
            return view('bjpass::success', [
                'user' => $result['user'],
                'tokens' => $result['tokens'],
                'return_url' => $returnUrl,
                'frontend_origin' => config('bjpass.frontend_origin', '*')
            ]);

        } catch (AuthenticationException $e) {
            Log::warning('Authentication failed', [
                'error' => $e->getMessage(),
                'state' => $state
            ]);

            return view('bjpass::error', [
                'error' => 'authentication_failed',
                'message' => $e->getMessage()
            ]);

        } catch (\Exception $e) {
            Log::error('Unexpected error during authentication', [
                'error' => $e->getMessage(),
                'state' => $state
            ]);

            return view('bjpass::error', [
                'error' => 'unexpected_error',
                'message' => 'An unexpected error occurred'
            ]);
        }
    }

    /**
     * Check authentication status
     */
    public function status(Request $request): JsonResponse
    {
        try {
            if (!BjPass::isAuthenticated()) {
                return new JsonResponse([
                    'authenticated' => false,
                    'user' => null
                ]);
            }

            $user = BjPass::getUserInfo();
            
            return new JsonResponse([
                'authenticated' => true,
                'user' => $user,
                'session_info' => [
                    'authenticated_at' => session('bjpass_user_session.authenticated_at'),
                    'expires_at' => session('bjpass_user_session.expires_at')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get authentication status', [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'authenticated' => false,
                'user' => null,
                'error' => 'status_check_failed'
            ], 500);
        }
    }

    /**
     * Get current user information
     */
    public function user(Request $request): JsonResponse
    {
        try {
            if (!BjPass::isAuthenticated()) {
                return new JsonResponse([
                    'error' => 'unauthenticated',
                    'message' => 'User is not authenticated'
                ], 401);
            }

            $user = BjPass::getUserInfo();
            
            return new JsonResponse([
                'user' => $user,
                'session_info' => [
                    'authenticated_at' => session('bjpass_user_session.authenticated_at'),
                    'expires_at' => session('bjpass_user_session.expires_at')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user information', [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'user_info_failed',
                'message' => 'Failed to retrieve user information'
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            BjPass::logout();

            Log::info('User logged out successfully');

            return new JsonResponse([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'logout_failed',
                'message' => 'Failed to logout'
            ], 500);
        }
    }

    /**
     * Refresh access token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $result = BjPass::refreshToken('');

            if (!$result) {
                return new JsonResponse([
                    'error' => 'refresh_failed',
                    'message' => 'Failed to refresh token'
                ], 400);
            }

            return new JsonResponse([
                'success' => true,
                'token_info' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'refresh_failed',
                'message' => 'Failed to refresh token'
            ], 500);
        }
    }

    /**
     * Introspect token
     */
    public function introspect(Request $request): JsonResponse
    {
        $token = $request->input('token');
        
        if (!$token) {
            return new JsonResponse([
                'error' => 'missing_token',
                'message' => 'Token parameter is required'
            ], 400);
        }

        try {
            $result = BjPass::introspectToken($token);

            if (!$result) {
                return new JsonResponse([
                    'error' => 'introspection_failed',
                    'message' => 'Token introspection failed'
                ], 400);
            }

            return new JsonResponse([
                'success' => true,
                'introspection' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Token introspection failed', [
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'introspection_failed',
                'message' => 'Failed to introspect token'
            ], 500);
        }
    }

    /**
     * Show error page
     */
    public function error(Request $request): \Illuminate\Contracts\View\View
    {
        $error = $request->get('error', 'unknown_error');
        $message = $request->get('message', 'An unknown error occurred');

        return view('bjpass::error', [
            'error' => $error,
            'message' => $message
        ]);
    }
}
