<?php

namespace App\Http\Middleware;

use App\Models\LicenseQuota;
use App\Services\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuotaMiddleware
{
    public function __construct(
        private QuotaService $quotaService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Token') ?? $request->input('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Token required',
                'code' => 'TOKEN_REQUIRED'
            ], 401);
        }

        $quota = LicenseQuota::getOrCreate($token);

        // Check if quota is available (quotas are now only updated via manual refresh endpoint)
        if ($quota->current_quota_kb <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'No quota available. Please refresh your quota or check your subscription.',
                'code' => 'QUOTA_UNAVAILABLE'
            ], 403);
        }

        if ($request->hasFile('file')) {
            $fileSizeKb = ceil($request->file('file')->getSize() / 1024);
            
            if (!$quota->hasQuotaAvailable($fileSizeKb)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Quota exceeded',
                    'quota_info' => [
                        'limit_mb' => $quota->current_quota_mb,
                        'used_mb' => $quota->used_mb,
                        'remaining_mb' => $quota->remaining_quota_mb,
                        'requested_mb' => round($fileSizeKb / 1024, 2),
                    ],
                    'code' => 'QUOTA_EXCEEDED'
                ], 429);
            }
        }

        $request->merge([
            'token' => $token,
            'quota' => $quota
        ]);

        return $next($request);
    }
}
