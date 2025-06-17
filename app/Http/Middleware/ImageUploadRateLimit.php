<?php

/**
 * Rate limiting middleware for image uploads.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to limit image uploads per IP address.
 */
class ImageUploadRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request The incoming request
     * @param Closure $next    The next middleware closure
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'image_uploads:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            if ($request->expectsJson()) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Too many uploads. You can upload maximum 10 images per hour.',
                        'retry_after' => $retryAfter,
                    ],
                    429
                );
            }
            
            return redirect()->back()->with('error', 'Too many uploads. You can upload maximum 10 images per hour. Try again in ' . ceil($retryAfter / 60) . ' minutes.');
        }

        RateLimiter::hit($key, 3600); // 1 hour

        return $next($request);
    }
} 